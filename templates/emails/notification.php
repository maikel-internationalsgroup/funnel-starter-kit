<?php
/**
 * Internal notification to the mailbox. Language: config mail.internal_language.
 * @var Funnel\Forms\Submission $submission
 * @var string $lang
 */
use Funnel\Support\I18n;

$tt = static fn (string $key, array $p = []) => I18n::t($key, $p, $lang);
?>
<!doctype html>
<html lang="<?= e($lang) ?>">
<body style="margin:0;padding:24px;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;color:#1d2433;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:8px;">
    <tr><td style="padding:24px 24px 8px;">
        <h1 style="margin:0 0 4px;font-size:20px;"><?= e($tt('emails.notification.heading')) ?></h1>
        <p style="margin:0;color:#5b6475;font-size:14px;">
            <?= e(config('funnel.name')) ?> · <?= e($submission->formId) ?> · <?= e(strtoupper($submission->lang)) ?> · <?= e(date('d-m-Y H:i', strtotime($submission->createdAt))) ?>
        </p>
    </td></tr>
    <tr><td style="padding:16px 24px;">
        <table role="presentation" width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;font-size:14px;">
<?php foreach ($submission->fields as $name => $value): ?>
            <tr style="border-bottom:1px solid #e6e8ec;">
                <th align="left" valign="top" style="width:35%;color:#5b6475;font-weight:normal;"><?= e(strip_tags($submission->label($name, $lang))) ?></th>
                <td valign="top" style="white-space:pre-wrap;"><?= e($submission->display($name, $lang)) ?></td>
            </tr>
<?php endforeach; ?>
        </table>
    </td></tr>
<?php if ($submission->attribution): ?>
    <tr><td style="padding:8px 24px 24px;">
        <h2 style="margin:0 0 8px;font-size:15px;"><?= e($tt('emails.notification.attribution')) ?></h2>
        <table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:13px;color:#5b6475;">
<?php foreach ($submission->attribution as $key => $value): ?>
            <tr><td style="width:35%;"><?= e($key) ?></td><td style="word-break:break-all;"><?= e($value) ?></td></tr>
<?php endforeach; ?>
        </table>
    </td></tr>
<?php endif; ?>
    <tr><td style="padding:0 24px 24px;font-size:12px;color:#8a92a3;">ID: <?= e($submission->id) ?></td></tr>
</table>
</body>
</html>
