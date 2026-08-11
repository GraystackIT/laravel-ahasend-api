<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Traits;

use Illuminate\Http\Request;

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

        return false;
    }

    /**
     * The delivery id Ahasend assigns, stable across retries of one event.
     */
    protected function webhookDeliveryId(Request $request): string
    {
        return (string) $request->header('webhook-id', '');
    }
}
