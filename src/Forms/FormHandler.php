<?php

declare(strict_types=1);

namespace Funnel\Forms;

use Funnel\App;
use Funnel\Integrations\Runner;
use Funnel\Mail\Mailer;
use Funnel\Security\RateLimiter;
use Funnel\Security\SignedToken;
use Funnel\Support\Config;
use Funnel\Support\I18n;
use Funnel\Support\Log;
use Funnel\Support\View;

/**
 * POST /submit
 *
 * 1. spam checks (honeypot, signed token + minimum fill time, rate limit)
 * 2. validation against config/funnel.json
 * 3. store the submission (safety net)
 * 4. run the form's integrations (mailbox, webhook, hubspot, blinqx, ...)
 * 5. optional confirmation e-mail to the visitor
 * 6. respond: JSON for fetch() requests, redirect for plain form posts
 */
final class FormHandler
{
    public function handle(): void
    {
        $formId = (string) ($_POST['form_id'] ?? '');
        $form = Config::get("forms.{$formId}");
        $lang = (string) ($_POST['lang'] ?? I18n::default());
        I18n::setLanguage($lang);
        $lang = I18n::language();

        if (!is_array($form)) {
            $this->fail(400, ['_form' => 'unknown_form']);
            return;
        }

        // 1. Spam checks -----------------------------------------------------
        if (!empty($_POST['website'])) {
            // Honeypot filled: pretend success, do nothing.
            Log::info('Honeypot triggered', ['form' => $formId]);
            $this->success($formId, $form, null, $lang); // no lead token -> no conversion counted
            return;
        }

        $token = (string) ($_POST['_token'] ?? '');
        $minSeconds = (int) Config::get('security.min_fill_seconds', 3);
        $payload = SignedToken::verify($token, 'form', 86400, $minSeconds);
        if ($payload === null || ($payload['f'] ?? null) !== $formId) {
            $this->fail(400, ['_form' => 'expired'], $formId, $form, $lang);
            return;
        }

        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        if (!RateLimiter::hit('submit:' . $formId, $ip)) {
            $this->fail(429, ['_form' => 'rate_limited'], $formId, $form, $lang);
            return;
        }

        // 2. Validation --------------------------------------------------------
        [$fields, $errors] = Validator::validate($form['fields'] ?? [], $_POST);
        if ($errors) {
            $this->fail(422, $errors, $formId, $form, $lang, $_POST);
            return;
        }

        // 3. Store --------------------------------------------------------------
        $submission = new Submission(
            id: self::uuid(),
            formId: $formId,
            lang: $lang,
            fields: $fields,
            attribution: self::attribution($_POST['attribution'] ?? []),
            meta: [
                'page_url' => substr((string) ($_POST['page_url'] ?? ($_SERVER['HTTP_REFERER'] ?? '')), 0, 500),
                'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300),
                'ip_anonymized' => self::anonymizeIp($ip),
                'funnel' => Config::get('funnel.slug'),
                'funnel_version' => Config::get('funnel.version'),
            ],
            createdAt: date('c'),
        );
        SubmissionStore::append($submission);

        // 4. Integrations -----------------------------------------------------
        $results = Runner::run($submission, $form['integrations'] ?? ['mailbox']);

        // 5. Confirmation mail ------------------------------------------------
        if (!empty($form['confirmation_email']) && $submission->email()) {
            try {
                Mailer::send(
                    to: [$submission->email()],
                    subject: I18n::t('emails.confirmation.subject', ['name' => $fields['first_name'] ?? ''], $lang),
                    html: View::render('emails/confirmation', ['submission' => $submission, 'lang' => $lang]),
                );
            } catch (\Throwable $e) {
                Log::error('Confirmation mail failed', ['id' => $submission->id, 'error' => $e->getMessage()]);
            }
        }

        Log::info('Submission processed', ['id' => $submission->id, 'form' => $formId, 'integrations' => $results]);

        // 6. Respond ------------------------------------------------------------
        $this->success($formId, $form, $submission->id, $lang);
    }

    private function success(string $formId, array $form, ?string $id, string $lang): void
    {
        $redirect = App::url($form['success_page'] ?? 'thank-you', $lang);
        if ($id !== null) {
            $redirect .= '?lead=' . rawurlencode(SignedToken::create(['form' => $formId, 'id' => $id], 'lead'));
        }

        if (self::wantsJson()) {
            self::json(200, ['ok' => true, 'redirect' => $redirect, 'event_id' => $id ?? '']);
            return;
        }
        App::redirect($redirect);
    }

    private function fail(int $status, array $errors, string $formId = '', array $form = [], string $lang = '', array $old = []): void
    {
        $messages = [];
        foreach ($errors as $field => $code) {
            $messages[$field] = I18n::t('forms.errors.' . $code);
        }

        if (self::wantsJson() || $form === []) {
            self::json($status, ['ok' => false, 'errors' => $messages]);
            return;
        }

        // No-JS fallback: re-render the page that holds the form, with errors and old input.
        http_response_code($status);
        echo App::renderPage($form['page'] ?? 'home', $lang, [
            'formErrors' => [$formId => $messages],
            'formOld' => [$formId => $old],
        ]);
    }

    private static function attribution(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }
        $allowed = array_merge(
            Config::get('tracking.attribution_params', []),
            ['landing_page', 'referrer', 'fbc', 'fbp', 'ga_client_id', 'hubspotutk']
        );
        $clean = [];
        foreach ($allowed as $key) {
            if (isset($input[$key]) && is_string($input[$key]) && $input[$key] !== '') {
                $clean[$key] = substr((string) preg_replace('/\p{C}/u', '', $input[$key]), 0, 500);
            }
        }
        if (!isset($clean['hubspotutk']) && isset($_COOKIE['hubspotutk'])) {
            $clean['hubspotutk'] = substr((string) $_COOKIE['hubspotutk'], 0, 100);
        }
        return $clean;
    }

    private static function anonymizeIp(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return (string) preg_replace('/\.\d+$/', '.0', $ip);
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);
            return (string) inet_ntop(substr($packed, 0, 6) . str_repeat("\0", 10));
        }
        return '';
    }

    private static function wantsJson(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
    }

    private static function json(int $status, array $data): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
