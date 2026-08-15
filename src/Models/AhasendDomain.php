<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Models;

use Carbon\CarbonInterface;
use GraystackIT\Ahasend\Data\DnsRecord;
use GraystackIT\Ahasend\Data\Domain as DomainData;
use GraystackIT\Ahasend\Enums\DomainVerifyState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A domain registered on the Ahasend account and tracked locally.
 *
 * The optional polymorphic owner lets a consuming application associate a
 * domain with one of its own records (a tenant, say) without this package
 * knowing anything about that concept.
 *
 * @property int                    $id
 * @property string                 $ahasend_domain_id
 * @property string                 $domain
 * @property array<int, array<string, mixed>>|null $dns_records
 * @property bool                   $dns_valid
 * @property DomainVerifyState      $verify_state
 * @property CarbonInterface|null   $last_dns_check_at
 * @property string|null            $tracking_subdomain
 * @property string|null            $return_path_subdomain
 * @property string|null            $subscription_subdomain
 * @property string|null            $media_subdomain
 * @property string|null            $dkim_selector
 * @property int|null               $dkim_rotation_interval_days
 * @property bool                   $rotation_ready
 * @property string|null            $dsn_recipient
 * @property string|null            $owner_type
 * @property string|null            $owner_id
 * @property CarbonInterface        $created_at
 * @property CarbonInterface        $updated_at
 */
class AhasendDomain extends Model
{
    protected $table = 'ahasend_domains';

    protected $fillable = [
        'ahasend_domain_id',
        'domain',
        'dns_records',
        'dns_valid',
        'verify_state',
        'last_dns_check_at',
        'tracking_subdomain',
        'return_path_subdomain',
        'subscription_subdomain',
        'media_subdomain',
        'dkim_selector',
        'dkim_rotation_interval_days',
        'rotation_ready',
        'dsn_recipient',
        'owner_type',
        'owner_id',
    ];

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'dns_records'                 => 'array',
            'dns_valid'                   => 'boolean',
            'rotation_ready'              => 'boolean',
            'verify_state'                => DomainVerifyState::class,
            'last_dns_check_at'           => 'datetime',
            'dkim_rotation_interval_days' => 'integer',
        ];
    }

    /**
     * The application record this domain belongs to, if any.
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Inbound routes provisioned for this domain.
     */
    public function routes(): HasMany
    {
        return $this->hasMany(AhasendRoute::class);
    }

    /**
     * Domains that have not completed verification.
     */
    public function scopeUnverified(Builder $query): Builder
    {
        return $query->whereIn('verify_state', array_column(DomainVerifyState::unverified(), 'value'));
    }

    /**
     * Domains belonging to the given owner record.
     */
    public function scopeForOwner(Builder $query, Model $owner): Builder
    {
        return $query
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', (string) $owner->getKey());
    }

    /**
     * Domains with no owner — the application's own shared domains.
     */
    public function scopeShared(Builder $query): Builder
    {
        return $query->whereNull('owner_type');
    }

    /**
     * Whether Ahasend accepts this domain for sending and receiving.
     */
    public function isVerified(): bool
    {
        return $this->verify_state === DomainVerifyState::Verified;
    }

    /**
     * The DNS records as value objects.
     *
     * @return list<DnsRecord>
     */
    public function dnsRecords(): array
    {
        return array_values(array_map(
            static fn (array $record): DnsRecord => DnsRecord::fromArray($record),
            $this->dns_records ?? [],
        ));
    }

    /**
     * Records the owner still has to create or correct in DNS.
     *
     * @return list<DnsRecord>
     */
    public function outstandingRecords(): array
    {
        return array_values(array_filter(
            $this->dnsRecords(),
            static fn (DnsRecord $record): bool => $record->isOutstanding(),
        ));
    }

    /**
     * When an unverified domain will be removed automatically.
     */
    public function expiresAt(): ?CarbonInterface
    {
        if ($this->isVerified()) {
            return null;
        }

        $days = (int) config('ahasend.domains.pending_expiry_days', 14);

        return $this->created_at?->copy()->addDays($days);
    }

    /**
     * Copy the API representation onto this record without saving.
     */
    public function fillFromApi(DomainData $data): self
    {
        $this->fill([
            'ahasend_domain_id'           => $data->id,
            'domain'                      => $data->domain,
            'dns_records'                 => array_map(
                static fn (DnsRecord $record): array => $record->toArray(),
                $data->dnsRecords,
            ),
            'dns_valid'                   => $data->dnsValid,
            'tracking_subdomain'          => $data->trackingSubdomain,
            'return_path_subdomain'       => $data->returnPathSubdomain,
            'subscription_subdomain'      => $data->subscriptionSubdomain,
            'media_subdomain'             => $data->mediaSubdomain,
            'dkim_selector'               => $data->dkimSelector,
            'dkim_rotation_interval_days' => $data->dkimRotationIntervalDays,
            'rotation_ready'              => $data->rotationReady,
            'dsn_recipient'               => $data->dsnRecipient,
        ]);

        return $this;
    }
}
