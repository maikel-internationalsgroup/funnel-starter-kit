<?php

declare(strict_types=1);

namespace Funnel\Support;

/**
 * Translations live in lang/{code}.json as nested objects.
 * t('home.hero.title') -> string, with {placeholder} replacement.
 * Missing keys fall back to the default language, then to the key itself.
 */
final class I18n
{
    private static string $lang = 'nl';
    private static array $cache = [];
    private static array $missing = [];

    public static function languages(): array
    {
        return Config::get('i18n.languages', ['nl']);
    }

    public static function default(): string
    {
        return Config::get('i18n.default', self::languages()[0]);
    }

    public static function isSupported(string $lang): bool
    {
        return in_array($lang, self::languages(), true);
    }

    public static function setLanguage(string $lang): void
    {
        self::$lang = self::isSupported($lang) ? $lang : self::default();
    }

    public static function language(): string
    {
        return self::$lang;
    }

    /** Pick a language from the Accept-Language header. */
    public static function detect(?string $header): string
    {
        if (!Config::get('i18n.detect_browser_language', true) || !$header) {
            return self::default();
        }
        foreach (explode(',', $header) as $part) {
            $code = strtolower(substr(trim(explode(';', $part)[0]), 0, 2));
            if (self::isSupported($code)) {
                return $code;
            }
        }
        return self::default();
    }

    public static function strings(string $lang): array
    {
        if (!isset(self::$cache[$lang])) {
            $file = FUNNEL_ROOT . "/lang/{$lang}.json";
            $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
            if (!is_array($data)) {
                throw new \RuntimeException("lang/{$lang}.json is not valid JSON: " . json_last_error_msg());
            }
            self::$cache[$lang] = $data;
        }
        return self::$cache[$lang];
    }

    public static function raw(string $key, ?string $lang = null): mixed
    {
        foreach (array_unique([$lang ?? self::$lang, self::default()]) as $code) {
            $value = self::strings($code);
            foreach (explode('.', $key) as $part) {
                if (!is_array($value) || !array_key_exists($part, $value)) {
                    $value = null;
                    break;
                }
                $value = $value[$part];
            }
            if ($value !== null) {
                return $value;
            }
        }
        self::$missing[$key] = true;
        return null;
    }

    public static function t(string $key, array $params = [], ?string $lang = null): string
    {
        $value = self::raw($key, $lang);
        if (!is_string($value)) {
            return Env::bool('APP_DEBUG') ? "[{$key}]" : '';
        }
        foreach ($params as $name => $replacement) {
            $value = str_replace('{' . $name . '}', (string) $replacement, $value);
        }
        return $value;
    }

    public static function has(string $key, ?string $lang = null): bool
    {
        return self::raw($key, $lang) !== null;
    }

    public static function missing(): array
    {
        return array_keys(self::$missing);
    }
}
