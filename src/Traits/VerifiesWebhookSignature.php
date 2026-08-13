<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Verifies a Standard Webhooks signature.
 *
 * Ahasend signs both event webhooks and inbound routing requests this way, but
 * with different secrets: event webhooks use the account-wide webhook secret,
 * while every inbound route has its own secret, handed out once at creation.
 */
trait VerifiesWebhookSignature
{
    /**
     * Check the request against the given secret.
     *
     * The signed content is "{webhook-id}.{webhook-timestamp}.{raw-body}". The
     * `webhook-signature` header holds one or more space-separated
     * "v1,{base64}" signatures; any match is accepted.
     *
     * A null or empty secret disables verification — only ever appropriate in
     * local development.
     */
    protected function signatureIsValid(Request $request, ?string $secret): bool
    {
        if ($secret === null || $secret === '') {
            return true;
        }

        $messageId = (string) $request->header('webhook-id', '');
        $timestamp = (string) $request->header('webhook-timestamp', '');
        $signature = (string) $request->header('webhook-signature', '');

        if ($messageId === '' || $timestamp === '' || $signature === '') {
            Log::warning('Ahasend: signature headers missing', [
                'has_id'        => $messageId !== '',
                'has_timestamp' => $timestamp !== '',
                'has_signature' => $signature !== '',
            ]);

            return false;
        }

        if (! $this->timestampIsFresh($timestamp)) {
            Log::warning('Ahasend: signed timestamp outside the tolerance window', [
                'timestamp' => $timestamp,
                'now'       => time(),
                'tolerance' => (int) config('ahasend.inbound.tolerance', 300),
            ]);

            return false;
        }

        $signed   = "{$messageId}.{$timestamp}.{$request->getContent()}";
        $expected = base64_encode(hash_hmac('sha256', $signed, $secret, true));

        foreach (explode(' ', $signature) as $candidate) {
            $parts = explode(',', $candidate, 2);

            if (count($parts) === 2 && hash_equals($expected, $parts[1])) {
                return true;
            }
        }

        // The secret is wrong for this delivery — the most common cause being a
        // route pointed at the event-webhook endpoint, where it is checked
        // against the account secret instead of its own.
        Log::warning('Ahasend: signature does not match the secret used', [
            'webhook_id' => $messageId,
        ]);

        return false;
    }

    /**
     * Whether the signed timestamp is close enough to now.
     *
     * The timestamp is part of the signed content, so without this check a
     * captured request stays replayable forever — the signature never expires
     * on its own. A tolerance of `0` disables the check.
     */
    protected function timestampIsFresh(string $timestamp): bool
    {
        $tolerance = (int) config('ahasend.inbound.tolerance', 300);

        if ($tolerance <= 0) {
            return true;
        }

        if (! is_numeric($timestamp)) {
            return false;
        }

        return abs(time() - (int) $timestamp) <= $tolerance;
    }

    /**
     * The delivery id Ahasend assigns, stable across retries of one event.
     */
    protected function webhookDeliveryId(Request $request): string
    {
        return (string) $request->header('webhook-id', '');
    }
}
