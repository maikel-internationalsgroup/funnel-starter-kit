<?php

declare(strict_types=1);

/*
 * Short helpers for templates. Keep templates free of business logic.
 */

use Funnel\App;
use Funnel\Support\Config;
use Funnel\Support\I18n;
use Funnel\Support\View;

/** Escape for HTML output. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Translated string (escape it with e() unless the copy intentionally contains HTML). */
function t(string $key, array $params = []): string
{
    return I18n::t($key, $params);
}

/** Translated list/object, e.g. benefits or FAQ items. */
function t_list(string $key): array
{
    $value = I18n::raw($key);
    return is_array($value) ? $value : [];
}

/** Current language code. */
function lang(): string
{
    return I18n::language();
}

/** URL of a page in the current (or given) language. */
function page_url(string $page, ?string $lang = null): string
{
    return App::url($page, $lang);
}

/** Versioned asset URL (cache busting by file modification time). */
function asset(string $path): string
{
    $base = rtrim((string) Config::get('site.base_path', ''), '/');
    $file = FUNNEL_ROOT . '/public/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return $base . '/' . ltrim($path, '/') . '?v=' . $version;
}

/** Endpoint the forms post to. */
function form_action(): string
{
    if (View::$preview) {
        return '#';
    }
    return rtrim((string) Config::get('site.base_path', ''), '/') . '/submit';
}

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

function partial(string $name, array $data = []): string
{
    return View::render('partials/' . $name, $data);
}
