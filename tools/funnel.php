<?php

declare(strict_types=1);

/**
 * Funnel CLI. Run from the project root:
 *
 *   php tools/funnel.php init --name="Spring Campaign" --slug=spring-campaign \
 *        --url=https://spring.yourcompany.nl --languages=nl,en,de --gtm=GTM-ABC1234
 *   php tools/funnel.php key            Generate APP_KEY and write it to .env
 *   php tools/funnel.php check          Validate config, translations and templates
 *   php tools/funnel.php serve [port]   Local dev server (default 8080)
 *   php tools/funnel.php preview        Export dist/preview.html (single file for a Claude artifact)
 *   php tools/funnel.php submissions [file.csv]   Export stored submissions as CSV
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

require dirname(__DIR__) . '/src/bootstrap.php';

use Funnel\App;
use Funnel\Forms\SubmissionStore;
use Funnel\Support\Config;
use Funnel\Support\I18n;
use Funnel\Support\View;

$command = $argv[1] ?? 'help';
$options = [];
$args = [];
foreach (array_slice($argv, 2) as $arg) {
    if (preg_match('/^--([^=]+)=(.*)$/', $arg, $m)) {
        $options[$m[1]] = $m[2];
    } else {
        $args[] = $arg;
    }
}

function out(string $line = ''): void
{
    fwrite(STDOUT, $line . PHP_EOL);
}

function fail(string $line): never
{
    fwrite(STDERR, "✗ {$line}" . PHP_EOL);
    exit(1);
}

function write_json(string $file, array $data): void
{
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
}

/** @return string[] dot keys of all leaves */
function flatten(array $data, string $prefix = ''): array
{
    $keys = [];
    foreach ($data as $key => $value) {
        $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
        if (is_array($value) && !array_is_list($value)) {
            $keys = array_merge($keys, flatten($value, $path));
        } else {
            $keys[] = $path;
        }
    }
    return $keys;
}

function lookup(array $data, string $key): mixed
{
    foreach (explode('.', $key) as $part) {
        if (!is_array($data) || !array_key_exists($part, $data)) {
            return null;
        }
        $data = $data[$part];
    }
    return $data;
}

switch ($command) {
    // ------------------------------------------------------------------
    case 'init':
        $file = FUNNEL_ROOT . '/config/funnel.json';
        $config = Config::all();
        if (isset($options['name'])) {
            $config['funnel']['name'] = $options['name'];
        }
        if (isset($options['slug'])) {
            $config['funnel']['slug'] = $options['slug'];
        } elseif (isset($options['name'])) {
            $config['funnel']['slug'] = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($options['name'])), '-');
        }
        if (isset($options['url'])) {
            $config['site']['base_url'] = rtrim($options['url'], '/');
        }
        if (isset($options['gtm'])) {
            $config['tracking']['gtm_id'] = $options['gtm'];
        }
        if (isset($options['color'])) {
            $config['site']['brand_color'] = $options['color'];
        }
        if (isset($options['languages'])) {
            $langs = array_values(array_filter(array_map('trim', explode(',', $options['languages']))));
            $default = $options['default'] ?? $langs[0];
            $config['i18n']['languages'] = $langs;
            $config['i18n']['default'] = $default;
            $source = FUNNEL_ROOT . '/lang/' . I18n::default() . '.json';
            foreach ($langs as $code) {
                $target = FUNNEL_ROOT . "/lang/{$code}.json";
                if (!is_file($target)) {
                    copy($source, $target);
                    out("+ lang/{$code}.json created from " . basename($source) . ' (translate it!)');
                }
                foreach ($config['pages'] as $key => $page) {
                    if (is_array($page['slug'] ?? null) && !isset($page['slug'][$code])) {
                        $config['pages'][$key]['slug'][$code] = reset($page['slug']);
                    }
                }
                if (is_array($config['site']['privacy_url'] ?? null) && !isset($config['site']['privacy_url'][$code])) {
                    $config['site']['privacy_url'][$code] = reset($config['site']['privacy_url']);
                }
            }
        }
        write_json($file, $config);
        out('✓ config/funnel.json updated');
        if (!is_file(FUNNEL_ROOT . '/.env')) {
            copy(FUNNEL_ROOT . '/.env.example', FUNNEL_ROOT . '/.env');
            out('+ .env created from .env.example');
        }
        out('Next: php tools/funnel.php key && php tools/funnel.php check');
        break;

    // ------------------------------------------------------------------
    case 'key':
        $key = bin2hex(random_bytes(32));
        $env = FUNNEL_ROOT . '/.env';
        if (!is_file($env)) {
            copy(FUNNEL_ROOT . '/.env.example', $env);
        }
        $content = (string) file_get_contents($env);
        $content = preg_match('/^APP_KEY=.*$/m', $content)
            ? (string) preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, $content)
            : $content . "\nAPP_KEY={$key}\n";
        file_put_contents($env, $content);
        out('✓ APP_KEY written to .env (use a different key per environment)');
        break;

    // ------------------------------------------------------------------
    case 'check':
        $errors = [];
        $warnings = [];
        $langs = I18n::languages();

        if (!in_array(I18n::default(), $langs, true)) {
            $errors[] = 'i18n.default is not in i18n.languages';
        }
        if (\Funnel\Tracking::gtmId() === null) {
            $warnings[] = 'tracking.gtm_id is not set (or still GTM-XXXXXXX) — no tags will load';
        }
        if (str_contains((string) Config::get('site.base_url'), 'example.')) {
            $warnings[] = 'site.base_url still points to an example domain';
        }

        // Translation files exist and have the same keys
        $strings = [];
        foreach ($langs as $code) {
            $file = FUNNEL_ROOT . "/lang/{$code}.json";
            if (!is_file($file)) {
                $errors[] = "lang/{$code}.json is missing";
                continue;
            }
            $data = json_decode((string) file_get_contents($file), true);
            if (!is_array($data)) {
                $errors[] = "lang/{$code}.json is not valid JSON";
                continue;
            }
            $strings[$code] = $data;
        }
        $reference = $strings[I18n::default()] ?? [];
        $refKeys = flatten($reference);
        foreach ($strings as $code => $data) {
            if ($code === I18n::default()) {
                continue;
            }
            $keys = flatten($data);
            foreach (array_diff($refKeys, $keys) as $k) {
                $errors[] = "lang/{$code}.json is missing key '{$k}'";
            }
            foreach (array_diff($keys, $refKeys) as $k) {
                $warnings[] = "lang/{$code}.json has extra key '{$k}' (not in " . I18n::default() . ')';
            }
            if ($data === $reference) {
                $warnings[] = "lang/{$code}.json is identical to " . I18n::default() . '.json — not translated yet?';
            }
        }

        // Keys used in templates exist
        $used = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(FUNNEL_ROOT . '/templates'));
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                preg_match_all('/\bt(?:_list)?\(\s*[\'"]([a-z0-9_.\-]+)[\'"]/i', (string) file_get_contents($f->getPathname()), $m);
                foreach ($m[1] as $key) {
                    $used[$key][] = substr($f->getPathname(), strlen(FUNNEL_ROOT) + 1);
                }
            }
        }
        $optionalKeys = ['/^pages\./'];
        foreach ($used as $key => $files) {
            foreach ($optionalKeys as $pattern) {
                if (preg_match($pattern, $key)) {
                    continue 2;
                }
            }
            foreach ($strings as $code => $data) {
                if (lookup($data, $key) === null) {
                    $errors[] = "lang/{$code}.json: key '{$key}' used in " . implode(', ', array_unique($files)) . ' is missing';
                }
            }
        }

        // Pages
        foreach (Config::get('pages', []) as $key => $page) {
            $tpl = FUNNEL_ROOT . '/templates/pages/' . ($page['template'] ?? $key) . '.php';
            if (!is_file($tpl)) {
                $errors[] = "pages.{$key}: template " . basename($tpl) . ' not found';
            }
        }
        if (!Config::get('pages.home')) {
            $errors[] = 'a page with key "home" is required';
        }

        // Forms
        $validTypes = ['text', 'email', 'tel', 'textarea', 'select', 'radio', 'checkbox', 'number', 'date', 'hidden'];
        foreach (Config::get('forms', []) as $id => $form) {
            if (!Config::get('pages.' . ($form['success_page'] ?? 'thank-you'))) {
                $errors[] = "forms.{$id}.success_page does not exist";
            }
            foreach (['submit', 'note'] as $k) {
                foreach ($strings as $code => $data) {
                    if (lookup($data, "forms.{$id}.{$k}") === null) {
                        $errors[] = "lang/{$code}.json: forms.{$id}.{$k} missing";
                    }
                }
            }
            foreach ($form['fields'] ?? [] as $field) {
                $name = $field['name'] ?? '?';
                if (!in_array($field['type'] ?? 'text', $validTypes, true)) {
                    $errors[] = "forms.{$id}.{$name}: unknown type '{$field['type']}'";
                }
                if (($field['type'] ?? '') === 'hidden') {
                    continue;
                }
                foreach ($strings as $code => $data) {
                    if (lookup($data, "forms.{$id}.fields.{$name}.label") === null) {
                        $errors[] = "lang/{$code}.json: forms.{$id}.fields.{$name}.label missing";
                    }
                    foreach ($field['options'] ?? [] as $opt) {
                        if (lookup($data, "forms.{$id}.fields.{$name}.options.{$opt}") === null) {
                            $errors[] = "lang/{$code}.json: option label forms.{$id}.fields.{$name}.options.{$opt} missing";
                        }
                    }
                }
            }
            foreach ($form['integrations'] ?? [] as $integration) {
                if (!isset(\Funnel\Integrations\Runner::MAP[$integration])) {
                    $errors[] = "forms.{$id}: unknown integration '{$integration}'";
                }
                $envChecks = [
                    'mailbox' => [],
                    'webhook' => ['WEBHOOK_URL'],
                    'hubspot' => ['HUBSPOT_PORTAL_ID', 'HUBSPOT_FORM_GUID'],
                    'blinqx' => ['BLINQX_ENDPOINT', 'BLINQX_TOKEN'],
                ];
                foreach ($envChecks[$integration] ?? [] as $var) {
                    if (\Funnel\Support\Env::get($var) === null) {
                        $warnings[] = "forms.{$id} uses '{$integration}' but {$var} is not set in .env";
                    }
                }
                if ($integration === 'mailbox' && !Config::get('integrations.mailbox.to') && !\Funnel\Support\Env::get('MAIL_TO')) {
                    $warnings[] = "forms.{$id} uses 'mailbox' but no recipient (MAIL_TO or integrations.mailbox.to)";
                }
            }
        }

        foreach ($warnings as $w) {
            out("⚠ {$w}");
        }
        foreach ($errors as $e) {
            out("✗ {$e}");
        }
        if ($errors) {
            out(sprintf('%d error(s), %d warning(s)', count($errors), count($warnings)));
            exit(1);
        }
        out(sprintf('✓ All checks passed (%d warning(s))', count($warnings)));
        break;

    // ------------------------------------------------------------------
    case 'serve':
        $port = (int) ($args[0] ?? 8080);
        out("Funnel running on http://localhost:{$port}  (Ctrl+C to stop)");
        passthru(sprintf(
            '%s -S localhost:%d -t %s %s',
            escapeshellarg(PHP_BINARY),
            $port,
            escapeshellarg(FUNNEL_ROOT . '/public'),
            escapeshellarg(FUNNEL_ROOT . '/tools/router.php')
        ));
        break;

    // ------------------------------------------------------------------
    case 'preview':
        View::$preview = true;
        $sections = [];
        foreach (I18n::languages() as $code) {
            foreach (Config::get('pages', []) as $key => $page) {
                I18n::setLanguage($code);
                App::$page = $key;
                $html = partial('header', ['page' => $key])
                    . "<main>\n" . View::render('pages/' . ($page['template'] ?? $key)) . "</main>\n"
                    . partial('footer');
                $sections[] = ['id' => "{$code}/{$key}", 'label' => strtoupper($code) . ' · ' . $key, 'html' => $html];
            }
        }
        I18n::setLanguage(I18n::default());
        $out = View::render('preview', [
            'sections' => $sections,
            'css' => (string) file_get_contents(FUNNEL_ROOT . '/public/assets/css/main.css'),
            'js' => (string) file_get_contents(FUNNEL_ROOT . '/public/assets/js/funnel.js'),
        ]);
        $target = $options['out'] ?? FUNNEL_ROOT . '/dist/preview.html';
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0775, true);
        }
        file_put_contents($target, $out);
        out('✓ Preview written to ' . $target . ' (' . round(strlen($out) / 1024) . ' KB)');
        out('  Share it with Claude: "Publish dist/preview.html as an artifact".');
        break;

    // ------------------------------------------------------------------
    case 'submissions':
        $rows = SubmissionStore::all();
        $fieldNames = [];
        $attrNames = [];
        foreach ($rows as $row) {
            $fieldNames = array_unique(array_merge($fieldNames, array_keys($row['fields'] ?? [])));
            $attrNames = array_unique(array_merge($attrNames, array_keys($row['attribution'] ?? [])));
        }
        $handle = isset($args[0]) ? fopen($args[0], 'w') : STDOUT;
        fputcsv($handle, array_merge(['id', 'created_at', 'form_id', 'language'], $fieldNames, $attrNames), ';', '"', '');
        foreach ($rows as $row) {
            $line = [$row['id'], $row['created_at'], $row['form_id'], $row['language']];
            foreach ($fieldNames as $n) {
                $v = $row['fields'][$n] ?? '';
                $line[] = is_bool($v) ? ($v ? 'yes' : 'no') : $v;
            }
            foreach ($attrNames as $n) {
                $line[] = $row['attribution'][$n] ?? '';
            }
            fputcsv($handle, $line, ';', '"', '');
        }
        if (isset($args[0])) {
            fclose($handle);
            out('✓ ' . count($rows) . " submission(s) written to {$args[0]}");
        }
        break;

    // ------------------------------------------------------------------
    default:
        out((string) preg_replace('/^ \* ?/m', '', explode('*/', explode('/**', (string) file_get_contents(__FILE__), 2)[1])[0]));
}
