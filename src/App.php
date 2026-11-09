<?php

declare(strict_types=1);

namespace Funnel;

use Funnel\Forms\FormHandler;
use Funnel\Support\Config;
use Funnel\Support\I18n;
use Funnel\Support\Log;
use Funnel\Support\View;

/**
 * Front controller. URL scheme:
 *   /                  -> redirect to /{detected-lang}/
 *   /{lang}/           -> "home" page (slug "")
 *   /{lang}/{slug}     -> page whose slug matches (slugs may differ per language)
 *   POST /submit       -> form handler
 */
final class App
{
    public static ?string $page = null;

    public static function run(): void
    {
        try {
            self::dispatch();
        } catch (\Throwable $e) {
            Log::error('Unhandled exception', ['message' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
            http_response_code(500);
            echo \Funnel\Support\Env::bool('APP_DEBUG')
                ? '<pre>' . e((string) $e) . '</pre>'
                : 'Something went wrong. Please try again later.';
        }
    }

    private static function dispatch(): void
    {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $base = rtrim((string) Config::get('site.base_path', ''), '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        $segments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($segments === ['submit']) {
            if ($method !== 'POST') {
                self::redirect(self::url('home', I18n::default()), 302);
            }
            (new FormHandler())->handle();
            return;
        }

        if ($segments === []) {
            $lang = I18n::detect($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null);
            $query = $_SERVER['QUERY_STRING'] ?? '';
            self::redirect(self::url('home', $lang) . ($query !== '' ? '?' . $query : ''), 302);
        }

        $lang = $segments[0];
        $slug = $segments[1] ?? '';
        if (!I18n::isSupported($lang) || count($segments) > 2) {
            self::notFound();
            return;
        }

        $page = self::findPage($slug, $lang);
        if ($page === null) {
            I18n::setLanguage($lang);
            self::notFound();
            return;
        }

        echo self::renderPage($page, $lang);
    }

    public static function findPage(string $slug, string $lang): ?string
    {
        foreach (Config::get('pages', []) as $key => $page) {
            if (self::slug($key, $lang) === $slug) {
                return $key;
            }
        }
        return null;
    }

    public static function slug(string $page, string $lang): string
    {
        $slug = Config::get("pages.{$page}.slug", $page);
        return is_array($slug) ? (string) ($slug[$lang] ?? reset($slug)) : (string) $slug;
    }

    public static function url(string $page, ?string $lang = null): string
    {
        $lang ??= I18n::language();
        if (View::$preview) {
            return '#' . $lang . '/' . $page;
        }
        $base = rtrim((string) Config::get('site.base_path', ''), '/');
        $slug = self::slug($page, $lang);
        return $base . '/' . $lang . '/' . ($slug === '' ? '' : $slug);
    }

    public static function absoluteUrl(string $page, ?string $lang = null): string
    {
        $origin = rtrim((string) Config::get('site.base_url', ''), '/');
        $saved = View::$preview;
        View::$preview = false;
        $url = $origin . self::url($page, $lang);
        View::$preview = $saved;
        return $url;
    }

    public static function renderPage(string $page, string $lang, array $data = []): string
    {
        I18n::setLanguage($lang);
        self::$page = $page;
        $template = (string) Config::get("pages.{$page}.template", $page);
        $content = View::render('pages/' . $template, $data);

        return View::render('layout', [
            'content' => $content,
            'page' => $page,
            'lang' => $lang,
        ]);
    }

    public static function redirect(string $url, int $status = 303): never
    {
        header('Location: ' . $url, true, $status);
        exit;
    }

    private static function notFound(): void
    {
        http_response_code(404);
        self::$page = '404';
        echo View::render('layout', [
            'content' => View::render('pages/404'),
            'page' => '404',
            'lang' => I18n::language(),
        ]);
    }
}
