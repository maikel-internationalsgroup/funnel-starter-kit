<?php

declare(strict_types=1);

/**
 * Single entry point for everything (web requests, tools, tests).
 */

define('FUNNEL_ROOT', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'Funnel\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = FUNNEL_ROOT . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

if (is_file(FUNNEL_ROOT . '/vendor/autoload.php')) {
    require FUNNEL_ROOT . '/vendor/autoload.php';
}

require FUNNEL_ROOT . '/src/helpers.php';

Funnel\Support\Env::load(FUNNEL_ROOT . '/.env');
Funnel\Support\Config::load(FUNNEL_ROOT . '/config/funnel.json');

$debug = Funnel\Support\Env::bool('APP_DEBUG', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
date_default_timezone_set('Europe/Amsterdam');
