<?php
/** @var string $page */

use Funnel\Support\I18n;

$logo = (string) config('site.logo', '');
$hasLogo = $logo !== '' && is_file(FUNNEL_ROOT . '/public/' . $logo);
?>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(page_url('home')) ?>">
<?php if ($hasLogo): ?>
            <img src="<?= e(asset($logo)) ?>" alt="<?= e(t('site.brand')) ?>" height="32">
<?php else: ?>
            <span class="brand-mark" aria-hidden="true"></span><span><?= e(t('site.brand')) ?></span>
<?php endif; ?>
        </a>
<?php if (count(I18n::languages()) > 1 && $page !== '404'): ?>
        <nav class="lang-switch" aria-label="<?= e(t('common.language')) ?>">
<?php foreach (I18n::languages() as $code): ?>
            <a href="<?= e(page_url($page, $code)) ?>"
               hreflang="<?= e($code) ?>"
               lang="<?= e($code) ?>"
               data-track="language_switch" data-track-label="<?= e($code) ?>"
               <?= $code === lang() ? 'aria-current="true"' : '' ?>><?= e(strtoupper($code)) ?></a>
<?php endforeach; ?>
        </nav>
<?php endif; ?>
    </div>
</header>
