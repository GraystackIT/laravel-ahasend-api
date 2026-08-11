<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Console;

use GraystackIT\Ahasend\Services\DomainManager;
use Illuminate\Console\Command;

/**
 * Removes unverified domains that have exceeded the configured pending age.
 *
 * Without this an abandoned domain would occupy its owner's only slot forever
 * and turn the one-open-domain rule into a support queue.
 */
class ExpireDomainsCommand extends Command
{
    protected $signature = 'ahasend:domains:expire';

    protected $description = 'Delete Ahasend domains that stayed unverified past the configured age';

    public function handle(DomainManager $domains): int
    {
        $expired = $domains->expirePending();

        $this->components->info("{$expired} unverified domain(s) expired.");

        return self::SUCCESS;
    }
}
