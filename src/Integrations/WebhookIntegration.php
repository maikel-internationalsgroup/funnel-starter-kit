<?php

declare(strict_types=1);

namespace Funnel\Integrations;

use Funnel\Forms\Submission;
use Funnel\Support\Env;
use Funnel\Support\Http;

/**
 * Generic connector: POSTs the full submission as JSON to any URL.
 * Works with Zapier, Make, n8n, Power Automate, or a custom endpoint, which can
 * then route the lead to any CRM (including tools without a native integration here).
 *
 * If a secret is configured, the request carries
 *   X-Funnel-Signature: sha256=<hex HMAC of the raw body>
 * so the receiver can verify the request came from the funnel.
 */
final class WebhookIntegration implements Integration
{
    public function send(Submission $submission, array $config): void
    {
        $url = Env::get($config['url_env'] ?? 'WEBHOOK_URL') ?? ($config['url'] ?? null);
        if (!$url) {
            throw new \RuntimeException('No webhook URL configured');
        }
        $payload = $submission->toArray();
        $headers = [];
        $secret = Env::get($config['secret_env'] ?? 'WEBHOOK_SECRET');
        if ($secret) {
            $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $headers[] = 'X-Funnel-Signature: sha256=' . hash_hmac('sha256', (string) $body, $secret);
        }
        Http::postJson($url, $payload, $headers);
    }
}
