<?php

declare(strict_types=1);

namespace Funnel\Security;

use Funnel\Support\Env;

/**
 * Stateless HMAC-signed tokens (no PHP sessions needed, works behind caches).
 * Used for the form token (CSRF + minimum fill time) and the thank-you lead token.
 */
final class SignedToken
{
    public static function create(array $payload, string $purpose): string
    {
        $payload['t'] = time();
        $data = self::b64(json_encode($payload, JSON_UNESCAPED_SLASHES));
        return $data . '.' . self::b64(hash_hmac('sha256', $purpose . '|' . $data, self::key(), true));
    }

    /**
     * @return array|null payload, or null when the signature is wrong or the age is out of range
     */
    public static function verify(string $token, string $purpose, int $maxAge, int $minAge = 0): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }
        [$data, $sig] = $parts;
        $expected = self::b64(hash_hmac('sha256', $purpose . '|' . $data, self::key(), true));
        if (!hash_equals($expected, $sig)) {
            return null;
        }
        $payload = json_decode((string) self::unb64($data), true);
        if (!is_array($payload) || !isset($payload['t'])) {
            return null;
        }
        $age = time() - (int) $payload['t'];
        if ($age < $minAge || $age > $maxAge) {
            return null;
        }
        return $payload;
    }

    private static function key(): string
    {
        $key = Env::get('APP_KEY');
        if ($key === null || $key === 'change-me-to-a-long-random-string') {
            if (Env::get('APP_ENV') === 'production') {
                throw new \RuntimeException('APP_KEY is not set. Run: php tools/funnel.php key');
            }
            return hash('sha256', FUNNEL_ROOT . '|local-dev-key');
        }
        return $key;
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function unb64(string $data): string|false
    {
        return base64_decode(strtr($data, '-_', '+/'), true);
    }
}
