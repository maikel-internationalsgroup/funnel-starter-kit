<?php

declare(strict_types=1);

namespace Funnel\Integrations;

use Funnel\Forms\Submission;
use Funnel\Mail\Mailer;
use Funnel\Support\Config;
use Funnel\Support\Env;
use Funnel\Support\I18n;
use Funnel\Support\View;

/**
 * Sends a notification with all fields + attribution to an internal mailbox.
 * Recipients: integrations.mailbox.to in config, or MAIL_TO in .env (comma-separated).
 */
final class MailboxIntegration implements Integration
{
    public function send(Submission $submission, array $config): void
    {
        $to = $config['to'] ?? [];
        if ($to === [] || $to === null) {
            $to = explode(',', (string) Env::get('MAIL_TO', ''));
        }
        $lang = (string) Config::get('mail.internal_language', I18n::default());
        $prefix = trim((string) ($config['subject_prefix'] ?? ''));

        $subject = trim($prefix . ' ' . I18n::t('emails.notification.subject', [
            'funnel' => Config::get('funnel.name'),
            'form' => $submission->formId,
            'name' => trim(($submission->fields['first_name'] ?? '') . ' ' . ($submission->fields['last_name'] ?? '')),
        ], $lang));

        Mailer::send(
            to: (array) $to,
            subject: $subject,
            html: View::render('emails/notification', ['submission' => $submission, 'lang' => $lang]),
            replyTo: $submission->email(),
        );
    }
}
