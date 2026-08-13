<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Console;

use GraystackIT\Ahasend\Exceptions\AhasendException;
use GraystackIT\Ahasend\Services\DomainManager;
use Illuminate\Console\Command;

/**
 * Re-checks DNS for every domain that is still waiting to be verified.
 *
 * Ahasend sends no event when a domain becomes valid, so without this a domain
 * would stay pending until somebody pressed a button.
 */
class PollDomainsCommand extends Command
{
    protected $signature = 'ahasend:domains:poll';

    protected $description = 'Re-run the DNS check for all unverified Ahasend domains';

    public function handle(DomainManager $domains): int
    {
        $pending  = $domains->pollable();
        $verified = 0;
        $failed   = 0;

        foreach ($pending as $domain) {
            try {
                $result = $domains->refresh($domain);

                $result->isVerified() ? $verified++ : $failed++;
            } catch (AhasendException $e) {
                $failed++;

                $this->components->warn("{$domain->domain}: {$e->getMessage()}");
            }
        }

        $this->components->info(sprintf(
            '%d domain(s) checked, %d verified, %d still outstanding.',
            $pending->count(),
            $verified,
            $failed,
        ));

        return self::SUCCESS;
    }
}
