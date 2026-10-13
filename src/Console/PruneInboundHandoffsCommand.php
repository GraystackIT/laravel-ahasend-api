<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes handoff bodies nothing will pick up any more.
 *
 * A processed delivery deletes its own body. What is left behind belongs to a
 * job that failed for good, or to one lost with its queue — and every body is
 * personal data with base64 attachments, so it is not kept indefinitely.
 */
class PruneInboundHandoffsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ahasend:prune-inbound-handoffs {--days=3 : Delete bodies stored this many days ago or earlier}';

    /**
     * @var string
     */
    protected $description = 'Delete inbound handoff payloads left behind by failed or lost jobs';

    /**
     * Delete every day directory older than the cut-off.
     */
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days)->startOfDay();
        $disk = Storage::disk((string) config('ahasend.inbound.handoff.disk', 'local'));
        $deleted = 0;

        foreach ($disk->directories('ahasend-inbound') as $directory) {
            $day = basename($directory);

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) !== 1) {
                continue;
            }

            if (Carbon::createFromFormat('Y-m-d', $day)->startOfDay()->greaterThan($cutoff)) {
                continue;
            }

            $deleted += count($disk->files($directory));
            $disk->deleteDirectory($directory);
        }

        $this->info("Deleted {$deleted} inbound handoff payload(s).");

        return self::SUCCESS;
    }
}
