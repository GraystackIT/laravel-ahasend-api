<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Data;

/**
 * Represents a domain object returned by the Ahasend API.
 */
final class Domain
{
    /**
     * @param  list<DnsRecord>  $dnsRecords
     */
    public function __construct(
        public readonly string $id,
        public readonly string $domain,
        public readonly array $dnsRecords = [],
        public readonly bool $dnsValid = false,
        public readonly ?string $lastDnsCheckAt = null,
        public readonly ?string $accountId = null,
        public readonly ?string $trackingSubdomain = null,
        public readonly ?string $returnPathSubdomain = null,
        public readonly ?string $subscriptionSubdomain = null,
        public readonly ?string $mediaSubdomain = null,
        public readonly ?string $dkimSelector = null,
        public readonly ?int $dkimRotationIntervalDays = null,
        public readonly bool $rotationReady = false,
        public readonly ?string $dsnRecipient = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var list<array<string, mixed>> $records */
        $records = is_array($data['dns_records'] ?? null) ? $data['dns_records'] : [];

        return new self(
            id:                       (string) ($data['id'] ?? ''),
            domain:                   (string) ($data['domain'] ?? ''),
            dnsRecords:               array_values(array_map(
                static fn (array $record): DnsRecord => DnsRecord::fromArray($record),
                $records,
            )),
            dnsValid:                 (bool) ($data['dns_valid'] ?? false),
            lastDnsCheckAt:           isset($data['last_dns_check_at']) ? (string) $data['last_dns_check_at'] : null,
            accountId:                isset($data['account_id']) ? (string) $data['account_id'] : null,
            trackingSubdomain:        isset($data['tracking_subdomain']) ? (string) $data['tracking_subdomain'] : null,
            returnPathSubdomain:      isset($data['return_path_subdomain']) ? (string) $data['return_path_subdomain'] : null,
            subscriptionSubdomain:    isset($data['subscription_subdomain']) ? (string) $data['subscription_subdomain'] : null,
            mediaSubdomain:           isset($data['media_subdomain']) ? (string) $data['media_subdomain'] : null,
            dkimSelector:             isset($data['dkim_selector']) ? (string) $data['dkim_selector'] : null,
            dkimRotationIntervalDays: isset($data['dkim_rotation_interval_days']) ? (int) $data['dkim_rotation_interval_days'] : null,
            rotationReady:            (bool) ($data['rotation_ready'] ?? false),
            dsnRecipient:             isset($data['dsn_recipient']) ? (string) $data['dsn_recipient'] : null,
            createdAt:                isset($data['created_at']) ? (string) $data['created_at'] : null,
            updatedAt:                isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }

    /**
     * Records that still need to be created or corrected in DNS.
     *
     * @return list<DnsRecord>
     */
    public function outstandingRecords(): array
    {
        return array_values(array_filter(
            $this->dnsRecords,
            static fn (DnsRecord $record): bool => $record->isOutstanding(),
        ));
    }

    /**
     * Whether the domain carries an MX record, i.e. whether it can receive mail.
     */
    public function canReceiveMail(): bool
    {
        foreach ($this->dnsRecords as $record) {
            if ($record->type?->isInbound() === true && $record->propagated) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'                          => $this->id,
            'domain'                      => $this->domain,
            'dns_records'                 => array_map(
                static fn (DnsRecord $record): array => $record->toArray(),
                $this->dnsRecords,
            ),
            'dns_valid'                   => $this->dnsValid,
            'last_dns_check_at'           => $this->lastDnsCheckAt,
            'account_id'                  => $this->accountId,
            'tracking_subdomain'          => $this->trackingSubdomain,
            'return_path_subdomain'       => $this->returnPathSubdomain,
            'subscription_subdomain'      => $this->subscriptionSubdomain,
            'media_subdomain'             => $this->mediaSubdomain,
            'dkim_selector'               => $this->dkimSelector,
            'dkim_rotation_interval_days' => $this->dkimRotationIntervalDays,
            'rotation_ready'              => $this->rotationReady,
            'dsn_recipient'               => $this->dsnRecipient,
            'created_at'                  => $this->createdAt,
            'updated_at'                  => $this->updatedAt,
        ];
    }
}
