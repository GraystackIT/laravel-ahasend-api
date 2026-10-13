<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Support;

/**
 * The named inbound endpoints an application receives mail on.
 *
 * One endpoint per receiving domain instead of one for all of them: each is
 * verified against its own secret only, carries its own rate limit and can be
 * handed to its own queue, so a flood on one domain neither throttles nor
 * delays another, and each shows up as its own route in monitoring.
 *
 * Registered in code, not config: the domain and its secret belong to whatever
 * part of the application receives that mail, and that is where they are read.
 */
final class InboundChannels
{
    /**
     * @var array<string, InboundChannel>
     */
    private array $channels = [];

    /**
     * Register a channel, replacing one of the same name.
     *
     * @param  string  $name  URL segment: lower-case letters, digits and dashes.
     * @param  string  $domain  The receiving domain the channel's route is bound to.
     * @param  string|null  $secret  The route's signing secret; null or empty falls back to `ahasend.inbound.secrets`.
     * @param  string|null  $queue  Queue to hand deliveries to; null uses the configured default.
     *
     * @throws \InvalidArgumentException on a name that cannot be a URL segment
     */
    public function register(string $name, string $domain, ?string $secret = null, ?string $queue = null): void
    {
        if (preg_match('/^[a-z0-9-]+$/', $name) !== 1) {
            throw new \InvalidArgumentException("Inbound channel names are lower-case letters, digits and dashes; got \"{$name}\".");
        }

        $secret = trim((string) $secret);
        $queue = trim((string) $queue);

        $this->channels[$name] = new InboundChannel(
            name: $name,
            domain: InboundSecrets::normaliseDomain($domain),
            secret: $secret === '' ? null : $secret,
            queue: $queue === '' ? null : $queue,
        );
    }

    /**
     * The channel of this name, if one is registered.
     */
    public function get(string $name): ?InboundChannel
    {
        return $this->channels[$name] ?? null;
    }

    /**
     * Every registered channel, keyed by name.
     *
     * @return array<string, InboundChannel>
     */
    public function all(): array
    {
        return $this->channels;
    }

    /**
     * The secret a delivery on this channel must be signed with.
     *
     * The channel's own secret, else the one configured for its domain. Null
     * when neither exists, which the controller answers with 503 rather than
     * verifying against nothing.
     */
    public function secretFor(InboundChannel $channel): ?string
    {
        if ($channel->secret !== null) {
            return $channel->secret;
        }

        if ($channel->domain === '') {
            return null;
        }

        /** @var array<string, string> $secrets */
        $secrets = (array) config('ahasend.inbound.secrets', []);
        $secret = trim((string) ($secrets[$channel->domain] ?? ''));

        return $secret === '' ? null : $secret;
    }

    /**
     * The channel receiving for a domain, if any.
     */
    public function forDomain(string $domain): ?InboundChannel
    {
        $domain = InboundSecrets::normaliseDomain($domain);

        foreach ($this->channels as $channel) {
            if ($domain !== '' && $channel->domain === $domain) {
                return $channel;
            }
        }

        return null;
    }

    /**
     * Channel secrets as a domain map, for the legacy shared endpoint.
     *
     * Lets that endpoint keep verifying with the very same values while the
     * routes in the dashboard are moved over one by one.
     *
     * @return array<string, string>
     */
    public function secretsByDomain(): array
    {
        $secrets = [];

        foreach ($this->channels as $channel) {
            if ($channel->secret !== null && $channel->domain !== '') {
                $secrets[$channel->domain] = $channel->secret;
            }
        }

        return $secrets;
    }
}
