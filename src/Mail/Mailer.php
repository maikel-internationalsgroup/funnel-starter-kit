<?php

declare(strict_types=1);

namespace Funnel\Mail;

use Funnel\Support\Config;
use Funnel\Support\Env;

/**
 * MAIL_DRIVER=log   -> writes the mail to storage/logs/mail.log (local development)
 * MAIL_DRIVER=smtp  -> PHPMailer over SMTP (recommended in production; run `composer install`)
 * MAIL_DRIVER=mail  -> PHP mail() (only if the hosting partner supports it and SPF/DKIM are set)
 */
final class Mailer
{
    /**
     * @param string[] $to
     */
    public static function send(array $to, string $subject, string $html, ?string $replyTo = null): void
    {
        $to = array_values(array_filter(array_map('trim', $to), static fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL)));
        if ($to === []) {
            throw new \InvalidArgumentException('No valid recipient');
        }
        $from = Env::get('MAIL_FROM', 'no-reply@localhost');
        $fromName = (string) Config::get('mail.from_name', '');
        $text = self::toText($html);

        switch (Env::get('MAIL_DRIVER', 'log')) {
            case 'smtp':
                self::smtp($to, $subject, $html, $text, $from, $fromName, $replyTo);
                break;
            case 'mail':
                self::phpMail($to, $subject, $html, $text, $from, $fromName, $replyTo);
                break;
            default:
                self::log($to, $subject, $text, $replyTo);
        }
    }

    private static function smtp(array $to, string $subject, string $html, string $text, string $from, string $fromName, ?string $replyTo): void
    {
        if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            throw new \RuntimeException('PHPMailer missing: run `composer install --no-dev` before deploying.');
        }
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = Env::require('SMTP_HOST');
        $mail->Port = (int) Env::get('SMTP_PORT', '587');
        $user = Env::get('SMTP_USER');
        if ($user !== null) {
            $mail->SMTPAuth = true;
            $mail->Username = $user;
            $mail->Password = (string) Env::get('SMTP_PASS', '');
        }
        $secure = Env::get('SMTP_SECURE', 'tls');
        $mail->SMTPSecure = $secure === 'none' ? '' : $secure;
        $mail->SMTPAutoTLS = $secure !== 'none';
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($from, $fromName);
        foreach ($to as $address) {
            $mail->addAddress($address);
        }
        if ($replyTo) {
            $mail->addReplyTo($replyTo);
        }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = $text;
        $mail->send();
    }

    private static function phpMail(array $to, string $subject, string $html, string $text, string $from, string $fromName, ?string $replyTo): void
    {
        $boundary = 'b' . bin2hex(random_bytes(8));
        $headers = [
            'MIME-Version: 1.0',
            'From: ' . ($fromName !== '' ? '=?UTF-8?B?' . base64_encode($fromName) . '?= ' : '') . '<' . $from . '>',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        if ($replyTo) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        $body = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text))
            . "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html))
            . "--{$boundary}--";

        $ok = mail(implode(',', $to), '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers), '-f' . $from);
        if (!$ok) {
            throw new \RuntimeException('mail() returned false');
        }
    }

    private static function log(array $to, string $subject, string $text, ?string $replyTo): void
    {
        $dir = FUNNEL_ROOT . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $entry = sprintf(
            "==== %s\nTo: %s\nReply-To: %s\nSubject: %s\n\n%s\n\n",
            date('c'),
            implode(', ', $to),
            $replyTo ?? '-',
            $subject,
            $text
        );
        file_put_contents($dir . '/mail.log', $entry, FILE_APPEND | LOCK_EX);
    }

    private static function toText(string $html): string
    {
        $html = (string) preg_replace('~<(style|script)[^>]*>.*?</\1>~si', '', $html);
        $html = (string) preg_replace('~<br\s*/?>|</(p|div|tr|h[1-6]|li)>~i', "\n", $html);
        $html = (string) preg_replace('~</t[dh]>~i', "\t", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace("/[ \t]+\n/", "\n", $text);
        $text = (string) preg_replace('/^[ \t]+/m', '', $text);
        return trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
    }
}
