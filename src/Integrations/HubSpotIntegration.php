<?php

declare(strict_types=1);

namespace Funnel\Integrations;

use Funnel\Forms\Submission;
use Funnel\Support\Env;
use Funnel\Support\Http;

/**
 * Submits to a HubSpot form via the Forms Submissions API (v3).
 * - Without a token: public endpoint (the HubSpot form must allow it).
 * - With HUBSPOT_TOKEN (private app with "forms" scope): secure endpoint.
 *
 * field_map maps funnel field names (and attribution keys like utm_source)
 * to HubSpot contact property names. The hubspotutk cookie, if present,
 * is passed as context so the lead is tied to the visitor's HubSpot history.
 */
final class HubSpotIntegration implements Integration
{
    public function send(Submission $submission, array $config): void
    {
        $portal = Env::get($config['portal_id_env'] ?? 'HUBSPOT_PORTAL_ID') ?? ($config['portal_id'] ?? null);
        $guid = Env::get($config['form_guid_env'] ?? 'HUBSPOT_FORM_GUID') ?? ($config['form_guid'] ?? null);
        if (!$portal || !$guid) {
            throw new \RuntimeException('HubSpot portal id / form guid not configured');
        }
        $token = Env::get($config['token_env'] ?? 'HUBSPOT_TOKEN');

        $fields = [];
        foreach ($config['field_map'] ?? [] as $ours => $theirs) {
            $value = $submission->get($ours);
            if ($value === null || $value === '') {
                continue;
            }
            $fields[] = [
                'objectTypeId' => '0-1', // contact
                'name' => $theirs,
                'value' => is_bool($value) ? ($value ? 'true' : 'false') : (string) $value,
            ];
        }

        $context = array_filter([
            'hutk' => $submission->attribution['hubspotutk'] ?? null,
            'pageUri' => $submission->meta['page_url'] ?? null,
            'pageName' => $submission->meta['funnel'] ?? null,
        ]);

        $payload = ['fields' => $fields, 'context' => (object) $context];
        if (!empty($submission->fields['consent'])) {
            $payload['legalConsentOptions'] = [
                'consent' => [
                    'consentToProcess' => true,
                    'text' => 'Consent given via funnel form ' . $submission->formId,
                ],
            ];
        }

        $base = 'https://api.hsforms.com/submissions/v3/integration';
        $url = $token
            ? "{$base}/secure/submit/{$portal}/{$guid}"
            : "{$base}/submit/{$portal}/{$guid}";

        Http::postJson($url, $payload, $token ? ['Authorization: Bearer ' . $token] : []);
    }
}
