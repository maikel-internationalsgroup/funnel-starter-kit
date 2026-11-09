<?php

declare(strict_types=1);

namespace Funnel\Integrations;

use Funnel\Forms\Submission;

/**
 * Contract for every destination a submission can be sent to.
 *
 * Add a new one:
 *   1. create src/Integrations/MyToolIntegration.php implementing this interface
 *   2. register it in Runner::MAP
 *   3. add its settings under "integrations.mytool" in config/funnel.json (secrets in .env)
 *   4. list "mytool" in a form's "integrations" array
 */
interface Integration
{
    /** Throw an exception on failure; the Runner logs it and continues with the next integration. */
    public function send(Submission $submission, array $config): void;
}
