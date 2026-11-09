<?php
/**
 * Shell for the static preview that is shared as a Claude artifact.
 * Produced by: php tools/funnel.php preview
 *
 * @var array  $sections [['id' => 'nl/home', 'label' => 'NL · home', 'html' => '...'], ...]
 * @var string $css
 * @var string $js
 */
?>
<!doctype html>
<html lang="<?= e(config('i18n.default')) ?>" data-preview="1"
      data-funnel="<?= e(config('funnel.slug')) ?>"
      data-attribution-storage="session"
      data-attribution-params="<?= e(implode(',', config('tracking.attribution_params', []))) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e(config('funnel.name')) ?> preview</title>
    <!--
      FUNNEL PREVIEW (generated — do not hand-edit structure)
      funnel:  <?= e(config('funnel.slug')) ?> v<?= e(config('funnel.version')) ?>

      exported: <?= date('c') ?>

      Each <div data-preview-page="{lang}/{page}"> maps to templates/pages/{page}.php
      rendered with lang/{lang}.json. Edit copy and layout freely; Claude ports the
      changes back into the templates and language files.
    -->
    <style>
<?= $css ?>

/* Preview bar (not part of the funnel) */
.preview-bar { position: sticky; top: 0; z-index: 50; display: flex; flex-wrap: wrap; align-items: center; gap: 6px 12px; padding: 8px 16px; background: #1d2433; color: #fff; font: 600 13px/1.4 system-ui, sans-serif; }
.preview-bar strong { margin-right: 8px; }
.preview-bar a { color: #c9d3e6; text-decoration: none; padding: 3px 8px; border-radius: 6px; }
.preview-bar a[aria-current="true"] { background: #fff; color: #1d2433; }
:root { --brand: <?= e(config('site.brand_color', '#1f5eff')) ?>; }
    </style>
</head>
<body>
<nav class="preview-bar" data-preview-nav aria-label="Preview pages">
    <strong><?= e(config('funnel.name')) ?> · preview</strong>
<?php foreach ($sections as $section): ?>
    <a href="#<?= e($section['id']) ?>"><?= e($section['label']) ?></a>
<?php endforeach; ?>
</nav>
<?php foreach ($sections as $section): ?>
<div data-preview-page="<?= e($section['id']) ?>" hidden>
<?= $section['html'] ?>
</div>
<?php endforeach; ?>
<script>window.dataLayer = window.dataLayer || [];</script>
<script>
<?= $js ?>
</script>
</body>
</html>
