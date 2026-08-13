<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Writes verified Ahasend payloads out as files.
 *
 * Real payloads are the only reliable source for what Ahasend actually sends,
 * and writing them down once turns them into test fixtures. They carry personal
 * data and attachment bytes, so nothing is written unless a path is configured.
 */
final class PayloadCapture
{
    /**
     * Store a payload under the configured capture path.
     *
     * @param  string  $label  Goes into the file name, e.g. the delivery domain
     *                         or the event type.
     * @param  array<string, mixed>  $context  Extra keys stored alongside the payload.
     */
    public static function write(Request $request, string $label, array $context = []): void
    {
        $path = trim((string) config('ahasend.inbound.capture_path', ''));

        if ($path === '') {
            return;
        }

        if (! is_dir($path) && ! @mkdir($path, 0o755, true) && ! is_dir($path)) {
            Log::warning('Ahasend: capture path is not writable', ['path' => $path]);

            return;
        }

        $name = sprintf(
            '%s-%s-%s.json',
            date('Ymd-His'),
            preg_replace('/[^a-z0-9.]+/i', '-', $label) ?: 'payload',
            substr(hash('sha256', (string) $request->header('webhook-id') . microtime()), 0, 8),
        );

        file_put_contents(
            rtrim($path, '/') . '/' . $name,
            json_encode([
                'headers' => [
                    'webhook-id'        => $request->header('webhook-id'),
                    'webhook-timestamp' => $request->header('webhook-timestamp'),
                ],
                ...$context,
                'payload' => $request->json()->all(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
    }
}
