<?php
/**
 * Landing page. All copy comes from lang/*.json -> home.*
 * @var array $formErrors (only set when a no-JS submit failed)
 * @var array $formOld
 */
?>
<section class="hero">
    <div class="container hero-grid">
        <div class="hero-copy">
            <p class="eyebrow"><?= e(t('home.hero.eyebrow')) ?></p>
            <h1><?= e(t('home.hero.title')) ?></h1>
            <p class="lead"><?= e(t('home.hero.subtitle')) ?></p>
            <ul class="checklist">
<?php foreach (t_list('home.benefits') as $benefit): ?>
                <li><?= e($benefit) ?></li>
<?php endforeach; ?>
            </ul>
        </div>
        <div class="card form-card" id="form">
            <h2><?= e(t('home.form.title')) ?></h2>
            <p class="muted"><?= e(t('home.form.intro')) ?></p>
            <?= partial('form', ['id' => 'lead', 'formErrors' => $formErrors ?? [], 'formOld' => $formOld ?? []]) ?>
        </div>
    </div>
</section>

<section class="section">
    <div class="container">
        <h2 class="section-title"><?= e(t('home.steps.title')) ?></h2>
        <ol class="steps">
<?php foreach (t_list('home.steps.items') as $i => $step): ?>
            <li class="card">
                <span class="step-no"><?= $i + 1 ?></span>
                <h3><?= e($step['title'] ?? '') ?></h3>
                <p><?= e($step['text'] ?? '') ?></p>
            </li>
<?php endforeach; ?>
        </ol>
        <p class="center">
            <a class="btn btn-primary" href="#form" data-track="cta_click" data-track-label="steps_cta"><?= e(t('home.steps.cta')) ?></a>
        </p>
    </div>
</section>

<section class="section section-alt">
    <div class="container narrow">
        <h2 class="section-title"><?= e(t('home.faq.title')) ?></h2>
<?php foreach (t_list('home.faq.items') as $i => $item): ?>
        <details class="faq" data-track="faq_open" data-track-label="faq_<?= $i + 1 ?>">
            <summary><?= e($item['q'] ?? '') ?></summary>
            <p><?= e($item['a'] ?? '') ?></p>
        </details>
<?php endforeach; ?>
    </div>
</section>
