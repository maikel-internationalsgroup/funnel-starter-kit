<?php

declare(strict_types=1);

namespace Funnel\Support;

/**
 * Plain PHP templates in templates/. No template engine needed.
 */
final class View
{
    /** True while exporting the static Claude-artifact preview. */
    public static bool $preview = false;

    public static function render(string $template, array $data = []): string
    {
        $file = FUNNEL_ROOT . '/templates/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Template not found: {$template}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
