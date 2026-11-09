<?php
/**
 * Confirmation to the visitor, in the visitor's language.
 * @var Funnel\Forms\Submission $submission
 * @var string $lang
 */
use Funnel\Support\I18n;

$tt = static fn (string $key, array $p = []) => I18n::t($key, $p, $lang);
$name = (string) ($submission->fields['first_name'] ?? '');
$brand = e(config('site.brand_color', '#1f5eff'));
?>
<!doctype html>
<html lang="<?= e($lang) ?>">
<body style="margin:0;padding:24px;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;color:#1d2433;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px;border-top:4px solid <?= $brand ?>;">
    <tr><td style="padding:28px 28px 8px;">
        <h1 style="margin:0 0 12px;font-size:22px;"><?= e($tt('emails.confirmation.heading', ['name' => $name])) ?></h1>
        <p style="margin:0 0 12px;font-size:15px;line-height:1.55;"><?= e($tt('emails.confirmation.body')) ?></p>
        <p style="margin:0 0 20px;font-size:15px;line-height:1.55;"><?= e($tt('emails.confirmation.closing')) ?></p>
        <p style="margin:0 0 28px;font-size:15px;"><?= e($tt('emails.confirmation.signature')) ?></p>
    </td></tr>
    <tr><td style="padding:16px 28px;border-top:1px solid #e6e8ec;font-size:12px;color:#8a92a3;"><?= e($tt('emails.confirmation.footer')) ?></td></tr>
</table>
</body>
</html>
