<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Writes verified Ahasend payloads out as files.
 *
 * Real payloads are the only reliable source for what Ahasend actually sends,
 * and writing them down once turns them into test fixtures.
 *
 * Only ever active in local and testing environments. Payloads carry personal
 * data and base64 attachment bytes, and nothing here caps or prunes what it
 * writes — on a production system that is a disk filling with personal data.
 * A configured path is therefore not enough; the environment gate makes it
 * impossible rather than merely switched off by default.
 */
final class PayloadCapture
{
    /** Environments the capture may run in. */
    private const ALLOWED_ENVIRONMENTS = ['local', 'testing'];


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

        if (! app()->environment(self::ALLOWED_ENVIRONMENTS)) {
            Log::warning('Ahasend: payload capture is configured but only runs locally', [
                'environment' => app()->environment(),
            ]);

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
