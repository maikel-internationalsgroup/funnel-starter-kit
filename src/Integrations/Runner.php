<?php

declare(strict_types=1);

namespace Funnel\Integrations;

use Funnel\Forms\Submission;
use Funnel\Support\Config;
use Funnel\Support\Log;

final class Runner
{
    public const MAP = [
        'mailbox' => MailboxIntegration::class,
        'webhook' => WebhookIntegration::class,
        'hubspot' => HubSpotIntegration::class,
        'blinqx' => BlinqxElementsIntegration::class,
    ];

    /**
     * @return array<string, string> integration => "ok" | "failed: ..."
     */
    public static function run(Submission $submission, array $names): array
    {
        $results = [];
        foreach ($names as $name) {
            $class = self::MAP[$name] ?? null;
            if ($class === null) {
                $results[$name] = 'failed: unknown integration';
                Log::error("Unknown integration '{$name}'", ['form' => $submission->formId]);
                continue;
            }
            try {
                (new $class())->send($submission, (array) Config::get("integrations.{$name}", []));
                $results[$name] = 'ok';
            } catch (\Throwable $e) {
                $results[$name] = 'failed: ' . $e->getMessage();
                Log::error("Integration '{$name}' failed", ['id' => $submission->id, 'error' => $e->getMessage()]);
            }
        }
        return $results;
    }
}
