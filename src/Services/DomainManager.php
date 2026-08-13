<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Services;

use GraystackIT\Ahasend\Enums\DomainVerifyState;
use GraystackIT\Ahasend\Events\DomainCreated;
use GraystackIT\Ahasend\Events\DomainDeleted;
use GraystackIT\Ahasend\Events\DomainExpired;
use GraystackIT\Ahasend\Events\DomainVerificationFailed;
use GraystackIT\Ahasend\Events\DomainVerified;
use GraystackIT\Ahasend\Exceptions\AhasendException;
use GraystackIT\Ahasend\Exceptions\DomainAlreadyExistsException;
use GraystackIT\Ahasend\Exceptions\DomainLimitExceededException;
use GraystackIT\Ahasend\Models\AhasendDomain;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Owns the lifecycle of a locally tracked Ahasend domain.
 *
 * Ahasend has no "domain verified" webhook — only `domain.dns_error` — so a
 * domain only ever moves forward through an explicit DNS check, either on
 * demand or from the scheduled poll.
 */
class DomainManager
{
    public function __construct(
        private readonly DomainService $domains,
        private readonly RouteManager $routes,
    ) {}

    /**
     * Register a domain and start its verification.
     *
     * @param  Model|null  $owner  Application record the domain belongs to.
     *                             Owner-less domains are the application's own
     *                             shared domains and bypass the limit below.
     *
     * @throws DomainAlreadyExistsException
     * @throws DomainLimitExceededException
     * @throws AhasendException
     */
    public function add(string $domain, ?Model $owner = null): AhasendDomain
    {
        $domain = self::normalise($domain);

        if (AhasendDomain::query()->where('domain', $domain)->exists()) {
            throw DomainAlreadyExistsException::for($domain);
        }

        if ($owner instanceof Model) {
            $this->guardUnverifiedLimit($owner);
        }

        $data = $this->domains->create($domain, idempotencyKey: (string) Str::uuid());

        $record = (new AhasendDomain)
            ->fillFromApi($data)
            ->fill([
                'verify_state'      => $data->dnsValid ? DomainVerifyState::Verified : DomainVerifyState::Pending,
                'last_dns_check_at' => $data->lastDnsCheckAt,
            ]);

        if ($owner instanceof Model) {
            $record->owner_type = $owner->getMorphClass();
            $record->owner_id   = (string) $owner->getKey();
        }

        try {
            $record->save();
        } catch (QueryException $e) {
            // The partial unique index settles two concurrent adds for one owner.
            if ($owner instanceof Model && $this->isUnverifiedUniqueViolation($e)) {
                $this->rollbackRemote($domain);

                throw DomainLimitExceededException::forOwner(
                    $this->unverifiedDomainsFor($owner),
                    $this->limit(),
                );
            }

            $this->rollbackRemote($domain);

            throw $e;
        }

        DomainCreated::dispatch($record);

        return $record;
    }

    /**
     * Run a DNS check and move the domain forward.
     *
     * On the transition to verified the inbound route is provisioned, which is
     * what actually makes the domain able to receive mail.
     *
     * @throws AhasendException
     */
    public function refresh(AhasendDomain $domain): AhasendDomain
    {
        // The scheduled poll and a "check DNS" click can land at the same moment.
        // Without the lock both would see an unverified domain, both would
        // provision a route, and every mail would arrive twice.
        return DB::transaction(function () use ($domain): AhasendDomain {
            /** @var AhasendDomain $domain */
            $domain = AhasendDomain::query()
                ->whereKey($domain->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $wasVerified = $domain->isVerified();

            // A failing API call throws out of the transaction, so nothing is
            // written at all and the domain keeps the state it had. Marking it
            // "failed" would claim a DNS check happened that never did.
            $data = $this->domains->checkDns($domain->domain);

            $domain->fillFromApi($data)->fill([
                'verify_state'      => $data->dnsValid ? DomainVerifyState::Verified : DomainVerifyState::Failed,
                'last_dns_check_at' => $data->lastDnsCheckAt ?? Carbon::now(),
            ]);

            $domain->save();

            if (! $domain->isVerified()) {
                DomainVerificationFailed::dispatch($domain, $domain->outstandingRecords());

                return $domain;
            }

            // Shared domains keep their dashboard-managed route; provisioning
            // one here would give them a second.
            if ($domain->owner_type !== null) {
                $this->routes->provisionFor($domain);
            }

            if (! $wasVerified) {
                DomainVerified::dispatch($domain);
            }

            return $domain;
        });
    }

    /**
     * Remove a domain: its routes, the remote object and the local record.
     *
     * @throws AhasendException
     */
    public function delete(AhasendDomain $domain): void
    {
        $name      = $domain->domain;
        $ownerType = $domain->owner_type;
        $ownerId   = $domain->owner_id;

        $this->routes->retireFor($domain);
        $this->domains->delete($name);

        $domain->delete();

        DomainDeleted::dispatch($name, $ownerType, $ownerId);
    }

    /**
     * Delete unverified domains older than the configured age.
     *
     * Without this a typo'd domain would occupy an owner's only slot forever.
     *
     * @return int  Number of domains removed.
     */
    public function expirePending(): int
    {
        $days   = (int) config('ahasend.domains.pending_expiry_days', 14);
        $cutoff = Carbon::now()->subDays($days);

        $expired = 0;

        $stale = AhasendDomain::query()
            ->unverified()
            ->whereNotNull('owner_type')
            ->where('created_at', '<', $cutoff)
            ->get();

        foreach ($stale as $domain) {
            $name      = $domain->domain;
            $ownerType = $domain->owner_type;
            $ownerId   = $domain->owner_id;

            try {
                $this->routes->retireFor($domain);
                $this->domains->delete($name);
            } catch (AhasendException $e) {
                Log::warning('Ahasend: failed to delete expired domain remotely, removing locally anyway', [
                    'domain' => $name,
                    'error'  => $e->getMessage(),
                ]);
            }

            $domain->delete();
            $expired++;

            DomainExpired::dispatch($name, $ownerType, $ownerId);
        }

        return $expired;
    }

    /**
     * Domains the scheduled poll should re-check.
     *
     * Verified domains need no polling, and domains past the poll horizon are
     * left to expiry so a dead domain is not retried indefinitely.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, AhasendDomain>
     */
    public function pollable(): \Illuminate\Database\Eloquent\Collection
    {
        $maxAge = (int) config('ahasend.domains.poll_max_age_days', 14);

        return AhasendDomain::query()
            ->unverified()
            // Owner-less domains are the application's own: their routes live in
            // the dashboard. Polling one would verify it and provision a second
            // route beside the dashboard one, doubling every delivery.
            ->whereNotNull('owner_type')
            ->where('created_at', '>=', Carbon::now()->subDays($maxAge))
            ->orderBy('last_dns_check_at')
            ->get();
    }

    /**
     * Degrade a verified domain after Ahasend reported a DNS error for it.
     */
    public function markDnsError(string $domain): ?AhasendDomain
    {
        $record = AhasendDomain::query()->where('domain', self::normalise($domain))->first();

        if (! $record instanceof AhasendDomain) {
            return null;
        }

        $record->forceFill([
            'verify_state' => DomainVerifyState::Failed,
            'dns_valid'    => false,
        ])->save();

        DomainVerificationFailed::dispatch($record, $record->outstandingRecords());

        return $record;
    }

    /**
     * Reject the add when the owner already holds unverified domains.
     *
     * @throws DomainLimitExceededException
     */
    private function guardUnverifiedLimit(Model $owner): void
    {
        $blocking = $this->unverifiedDomainsFor($owner);

        if (count($blocking) >= $this->limit()) {
            throw DomainLimitExceededException::forOwner($blocking, $this->limit());
        }
    }

    /**
     * Names of the owner's domains that have not been verified.
     *
     * @return list<string>
     */
    private function unverifiedDomainsFor(Model $owner): array
    {
        return AhasendDomain::query()
            ->forOwner($owner)
            ->unverified()
            ->pluck('domain')
            ->all();
    }

    /**
     * How many unverified domains one owner may hold at a time.
     */
    private function limit(): int
    {
        return max(1, (int) config('ahasend.domains.max_unverified_per_owner', 1));
    }

    /**
     * Whether the failure came from the partial unique index on unverified domains.
     */
    private function isUnverifiedUniqueViolation(QueryException $e): bool
    {
        return str_contains($e->getMessage(), 'ahasend_domains_owner_unverified_unique');
    }

    /**
     * Undo the remote create when the local write failed.
     *
     * Leaving the domain behind in Ahasend would block a later retry, since the
     * remote create would then collide instead.
     */
    private function rollbackRemote(string $domain): void
    {
        try {
            $this->domains->delete($domain);
        } catch (AhasendException $e) {
            Log::error('Ahasend: could not roll back a remotely created domain', [
                'domain' => $domain,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Normalise a domain to the form it is stored and compared in.
     */
    public static function normalise(string $domain): string
    {
        return strtolower(trim(rtrim(trim($domain), '.')));
    }
}
