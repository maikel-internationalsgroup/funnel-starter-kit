<?php

declare(strict_types=1);

namespace Funnel\Forms;

use Funnel\Support\I18n;

/**
 * One validated form submission. Integrations receive this object.
 */
final class Submission
{
    public function __construct(
        public readonly string $id,
        public readonly string $formId,
        public readonly string $lang,
        public readonly array $fields,
        public readonly array $attribution,
        public readonly array $meta,
        public readonly string $createdAt,
    ) {
    }

    public function get(string $field, mixed $default = null): mixed
    {
        return $this->fields[$field] ?? $this->attribution[$field] ?? $default;
    }

    public function email(): ?string
    {
        $email = $this->fields['email'] ?? null;
        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /** Field label in a given language (for e-mails). */
    public function label(string $field, ?string $lang = null): string
    {
        $key = "forms.{$this->formId}.fields.{$field}.label";
        return I18n::has($key, $lang ?? $this->lang) ? I18n::t($key, [], $lang ?? $this->lang) : $field;
    }

    /** Human readable value (select options and checkboxes translated). */
    public function display(string $field, ?string $lang = null): string
    {
        $value = $this->fields[$field] ?? '';
        $lang ??= $this->lang;
        if (is_bool($value)) {
            return I18n::t($value ? 'common.yes' : 'common.no', [], $lang);
        }
        $optionKey = "forms.{$this->formId}.fields.{$field}.options.{$value}";
        if ($value !== '' && I18n::has($optionKey, $lang)) {
            return I18n::t($optionKey, [], $lang);
        }
        return (string) $value;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'form_id' => $this->formId,
            'language' => $this->lang,
            'created_at' => $this->createdAt,
            'fields' => $this->fields,
            'attribution' => $this->attribution,
            'meta' => $this->meta,
        ];
    }
}
