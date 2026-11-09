<?php

declare(strict_types=1);

namespace Funnel\Support;

/**
 * Reads config/funnel.json. Access with dot notation: Config::get('tracking.gtm_id').
 */
final class Config
{
    private static array $data = [];

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            throw new \RuntimeException("Config file not found: {$file}");
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            throw new \RuntimeException("Config file is not valid JSON: {$file} (" . json_last_error_msg() . ')');
        }
        self::$data = $data;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$data;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    public static function all(): array
    {
        return self::$data;
    }
}
