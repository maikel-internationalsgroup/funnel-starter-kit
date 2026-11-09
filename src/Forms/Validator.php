<?php

declare(strict_types=1);

namespace Funnel\Forms;

/**
 * Validates input against the field definitions in config/funnel.json.
 * Returns clean values and error message keys (translated in lang/*.json under forms.errors).
 *
 * Supported types: text, email, tel, textarea, select, radio, checkbox, number, date, hidden
 */
final class Validator
{
    /**
     * @return array{0: array<string, mixed>, 1: array<string, string>} [clean, errors]
     */
    public static function validate(array $fields, array $input): array
    {
        $clean = [];
        $errors = [];

        foreach ($fields as $field) {
            $name = $field['name'];
            $type = $field['type'] ?? 'text';
            $required = (bool) ($field['required'] ?? false);
            $raw = $input[$name] ?? null;

            if ($type === 'checkbox') {
                $checked = in_array($raw, ['1', 'on', 'true', 'yes'], true);
                if ($required && !$checked) {
                    $errors[$name] = 'required';
                }
                $clean[$name] = $checked;
                continue;
            }

            $value = is_string($raw) ? self::sanitize($raw, $type === 'textarea') : '';

            if ($value === '') {
                if ($required) {
                    $errors[$name] = 'required';
                }
                $clean[$name] = '';
                continue;
            }

            $max = (int) ($field['max'] ?? ($type === 'textarea' ? 5000 : 255));
            if (mb_strlen($value) > $max) {
                $errors[$name] = 'too_long';
            }

            switch ($type) {
                case 'email':
                    $value = strtolower($value);
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[$name] = 'email';
                    }
                    break;
                case 'tel':
                    if (!preg_match('/^\+?[0-9\s\-().]{6,20}$/', $value)) {
                        $errors[$name] = 'phone';
                    }
                    break;
                case 'number':
                    if (!is_numeric($value)) {
                        $errors[$name] = 'number';
                    }
                    break;
                case 'date':
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                        $errors[$name] = 'date';
                    }
                    break;
                case 'select':
                case 'radio':
                    if (!in_array($value, array_map('strval', $field['options'] ?? []), true)) {
                        $errors[$name] = 'invalid_option';
                    }
                    break;
            }

            if (isset($field['pattern']) && !isset($errors[$name]) && !preg_match('~' . str_replace('~', '\~', $field['pattern']) . '~u', $value)) {
                $errors[$name] = 'invalid';
            }

            $clean[$name] = $value;
        }

        return [$clean, $errors];
    }

    private static function sanitize(string $value, bool $multiline): string
    {
        $value = str_replace("\0", '', $value);
        $value = $multiline
            ? (string) preg_replace('/[^\P{C}\n\t]/u', '', $value)  // keep newlines/tabs
            : (string) preg_replace('/\p{C}/u', '', $value);       // strip all control chars (header injection)
        return trim($value);
    }
}
