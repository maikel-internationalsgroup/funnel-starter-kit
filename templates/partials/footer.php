<?php
$privacy = config('site.privacy_url');
$privacyUrl = is_array($privacy) ? ($privacy[lang()] ?? reset($privacy)) : $privacy;
?>
<footer class="site-footer">
    <div class="container footer-inner">
        <p>&copy; <?= date('Y') ?> <?= e(t('site.brand')) ?></p>
<?php if ($privacyUrl): ?>
        <a href="<?= e($privacyUrl) ?>" target="_blank" rel="noopener"><?= e(t('common.privacy')) ?></a>
<?php endif; ?>
    </div>
</footer>
