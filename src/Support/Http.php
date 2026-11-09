<?php

declare(strict_types=1);

namespace Funnel\Support;

final class Http
{
    /**
     * POST JSON and return [status, body]. Throws on network errors and HTTP >= 400.
     */
    public static function postJson(string $url, array $payload, array $headers = [], int $timeout = 8): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers = array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 4,
            ]);
            $response = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if ($response === false) {
                throw new \RuntimeException("HTTP request failed: {$error}");
            }
        } else {
            $context = stream_context_create(['http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ]]);
            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                throw new \RuntimeException('HTTP request failed');
            }
            preg_match('~HTTP/\S+\s+(\d{3})~', $http_response_header[0] ?? '', $m);
            $status = (int) ($m[1] ?? 0);
        }

        if ($status >= 400 || $status === 0) {
            throw new \RuntimeException("HTTP {$status}: " . substr((string) $response, 0, 300));
        }
        return [$status, (string) $response];
    }
}
