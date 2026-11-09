<?php
/**
 * Shared layout for every page.
 * @var string $content rendered page template
 * @var string $page    page key from config (or "404")
 * @var string $lang
 */

use Funnel\App;
use Funnel\Support\I18n;
use Funnel\Support\View;
use Funnel\Tracking;

$isPage = $page !== '404';
$noindex = config('site.noindex') || config("pages.{$page}.noindex") || !$isPage;
$title = I18n::has("pages.{$page}.title") ? t("pages.{$page}.title") : t('site.title');
$description = I18n::has("pages.{$page}.description") ? t("pages.{$page}.description") : t('site.description');
?>
<!doctype html>
<html lang="<?= e($lang) ?>"
      data-funnel="<?= e(config('funnel.slug')) ?>"
      data-page="<?= e($page) ?>"
      data-attribution-storage="<?= e(config('tracking.attribution_storage', 'session')) ?>"
      data-attribution-params="<?= e(implode(',', config('tracking.attribution_params', []))) ?>"
      <?= View::$preview ? 'data-preview="1"' : '' ?>>
<head>
<?= Tracking::head($page) ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e($description) ?>">
<?php if ($noindex): ?>
    <meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<?php if ($isPage && !View::$preview): ?>
    <link rel="canonical" href="<?= e(App::absoluteUrl($page, $lang)) ?>">
<?php foreach (I18n::languages() as $code): ?>
    <link rel="alternate" hreflang="<?= e($code) ?>" href="<?= e(App::absoluteUrl($page, $code)) ?>">
<?php endforeach; ?>
    <link rel="alternate" hreflang="x-default" href="<?= e(App::absoluteUrl($page, I18n::default())) ?>">
<?php endif; ?>
    <meta property="og:title" content="<?= e($title) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:locale" content="<?= e($lang) ?>">
    <meta name="theme-color" content="<?= e(config('site.brand_color', '#1f5eff')) ?>">
    <style>:root{--brand:<?= e(config('site.brand_color', '#1f5eff')) ?>}</style>
<?php if (!View::$preview): ?>
    <link rel="stylesheet" href="<?= e(asset('assets/css/main.css')) ?>">
<?php endif; ?>
</head>
<body class="page-<?= e($page) ?>">
<?= Tracking::body() ?>
<?= partial('header', ['page' => $page]) ?>
<main id="main">
<?= $content ?>
</main>
<?= partial('footer') ?>
<?php if (!View::$preview): ?>
<script src="<?= e(asset('assets/js/funnel.js')) ?>" defer></script>
<?php endif; ?>
</body>
</html>
