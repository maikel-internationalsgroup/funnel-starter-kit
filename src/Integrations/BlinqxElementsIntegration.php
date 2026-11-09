<?php

declare(strict_types=1);

namespace Funnel\Integrations;

use Funnel\Forms\Submission;
use Funnel\Support\Env;
use Funnel\Support\Http;

/**
 * Blinqx Elements connector — PREPARED, NOT YET VERIFIED.
 *
 * The exact Blinqx Elements API (endpoint, authentication, payload shape) still has
 * to be confirmed with Blinqx. Until then this sends a mapped JSON payload with a
 * bearer token to BLINQX_ENDPOINT. Once the API docs are available, adjust
 * buildPayload() and the headers below; the rest of the funnel does not change.
 *
 * Alternative that works today: use the "webhook" integration and let
 * Make/Zapier/n8n (or a Blinqx-side endpoint) create the record in Elements.
 */
final class BlinqxElementsIntegration implements Integration
{
    public function send(Submission $submission, array $config): void
    {
        $endpoint = Env::get($config['endpoint_env'] ?? 'BLINQX_ENDPOINT');
        $token = Env::get($config['token_env'] ?? 'BLINQX_TOKEN');
        if (!$endpoint || !$token) {
            throw new \RuntimeException('Blinqx endpoint/token not configured');
        }
        Http::postJson($endpoint, $this->buildPayload($submission, $config), ['Authorization: Bearer ' . $token]);
    }

    private function buildPayload(Submission $submission, array $config): array
    {
        $mapped = [];
        foreach ($config['field_map'] ?? [] as $ours => $theirs) {
            $value = $submission->get($ours);
            if ($value !== null && $value !== '') {
                $mapped[$theirs] = $value;
            }
        }
        return [
            'source' => $submission->meta['funnel'] ?? 'funnel',
            'externalId' => $submission->id,
            'language' => $submission->lang,
            'contact' => $mapped,
            'attribution' => $submission->attribution,
            'createdAt' => $submission->createdAt,
        ];
    }
}
