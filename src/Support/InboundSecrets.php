<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Support;

/**
 * The signing secrets of inbound routes managed in the Ahasend dashboard.
 *
 * A route's `recipient` pattern is bound to one domain and Ahasend issues a
 * separate secret per route, so the configuration is a map of domain to secret.
 * The canonical form is a PHP array — an application that publishes the config
 * writes it directly. Without a published config the same map is parsed from
 * `AHASEND_INBOUND_SECRETS`, because `.env` values are always strings.
 */
final class InboundSecrets
{
    /**
     * Parse the `domain:secret,domain:secret` environment format.
     *
     * @return array<string, string>  Lower-cased domain => secret.
     *
     * @throws \InvalidArgumentException on an entry without a domain part
     */
    public static function parse(?string $raw): array
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return [];
        }

        $secrets = [];

        foreach (explode(',', $raw) as $entry) {
            $entry = trim($entry);

            if ($entry === '') {
                continue;
            }

            // Only the first colon separates domain from secret: the secret may
            // contain colons, the domain never does.
            $parts = explode(':', $entry, 2);

            if (count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
                throw new \InvalidArgumentException(
                    'AHASEND_INBOUND_SECRETS must be "domain:secret" pairs separated by commas; '
                    . "got \"{$entry}\". A bare secret cannot be matched to a domain."
                );
            }

            $secrets[self::normaliseDomain($parts[0])] = trim($parts[1]);
        }

        return $secrets;
    }

    /**
     * The configured secret for the first of the given domains that has one.
     *
     * @param  list<string>  $domains  Candidate domains, most specific first.
     * @param  array<string, string>  $secrets
     * @return array{0: string, 1: string}|null  [domain, secret], or null when none matches.
     */
    public static function match(array $domains, array $secrets): ?array
    {
        foreach ($domains as $domain) {
            $domain = self::normaliseDomain($domain);

            if (isset($secrets[$domain])) {
                return [$domain, $secrets[$domain]];
            }
        }

        return null;
    }

    /**
     * The domain part of an email address, or the input if it is already one.
     */
    public static function domainOf(string $address): ?string
    {
        $position = strrpos($address, '@');
        $domain   = $position === false ? $address : substr($address, $position + 1);
        $domain   = self::normaliseDomain($domain);

        return $domain !== '' ? $domain : null;
    }

    /**
     * Lower-case and strip stray whitespace, brackets and trailing dots.
     */
    private static function normaliseDomain(string $domain): string
    {
        return strtolower(trim(rtrim(trim($domain, " \t\n\r\0\x0B<>"), '.')));
    }
}
