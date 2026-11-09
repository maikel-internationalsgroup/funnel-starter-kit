<section class="section thank-you">
    <div class="container narrow center">
        <div class="success-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
        </div>
        <h1><?= e(t('thank_you.title')) ?></h1>
        <p class="lead"><?= e(t('thank_you.text')) ?></p>
        <ul class="next-steps">
<?php foreach (t_list('thank_you.next') as $item): ?>
            <li><?= e($item) ?></li>
<?php endforeach; ?>
        </ul>
        <a class="btn btn-secondary" href="<?= e(page_url('home')) ?>" data-track="cta_click" data-track-label="back_home"><?= e(t('thank_you.back')) ?></a>
    </div>
</section>
