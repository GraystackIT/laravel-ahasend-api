<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Jobs;

use GraystackIT\Ahasend\Contracts\InboundScreen;
use GraystackIT\Ahasend\Data\InboundDelivery;
use GraystackIT\Ahasend\Data\InboundMessage;
use GraystackIT\Ahasend\Events\InboundMailReceived;
use GraystackIT\Ahasend\Models\AhasendRoute;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Takes a verified delivery off the handoff disk and announces it.
 *
 * What the webhook used to do inside the request, now in a worker: the request
 * only verifies, stores the raw body and queues this job, so Ahasend gets its
 * 200 at once however slow screening and the listeners are.
 *
 * The body travels on a disk rather than in the job, because attachments are
 * megabytes of base64 and a queue dashboard keeps job payloads in memory for
 * hours.
 *
 * Two kinds of retry, deliberately kept apart:
 *
 * - **A deferral** — the screen cannot judge yet, e.g. the virus scanner is
 *   down — releases the job without counting as a failure, until
 *   `handoff.retry_hours` have passed. Then the screen is told it gave up.
 * - **An exception** in a listener is retried with backoff, at most
 *   {@see self::$maxExceptions} times. Safe, because every listener has to be
 *   idempotent on the delivery id anyway: Ahasend itself redelivers.
 */
class ProcessInboundDelivery implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * Unlimited attempts; {@see self::retryUntil()} bounds them instead.
     */
    public int $tries = 0;

    /**
     * Listener exceptions tolerated before the job fails for good.
     */
    public int $maxExceptions = 3;

    /**
     * @param  string  $disk  Handoff disk the raw body was written to.
     * @param  string  $path  Path of the raw body on that disk.
     * @param  string  $deliveryId  Stable across Ahasend retries.
     * @param  string  $deliveredForDomain  The domain whose secret verified the body.
     * @param  int|null  $routeId  Key of the provisioned route it arrived on, if any.
     * @param  string|null  $channel  The named channel it arrived on, if any.
     */
    public function __construct(
        public readonly string $disk,
        public readonly string $path,
        public readonly string $deliveryId,
        public readonly string $deliveredForDomain,
        public readonly ?int $routeId = null,
        public readonly ?string $channel = null,
    ) {}

    /**
     * When deferrals stop.
     *
     * Evaluated once, when the job is dispatched.
     */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(max(1, (int) config('ahasend.inbound.handoff.retry_hours', 24)));
    }

    /**
     * Seconds to wait after a listener exception.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    /**
     * Screen the delivery, then hand it to the listeners or drop it.
     *
     * @param  InboundScreen  $screen  The application's screen.
     */
    public function handle(InboundScreen $screen): void
    {
        $delivery = $this->delivery();

        if ($delivery === null) {
            // Already handled by an earlier attempt, or pruned. Either way
            // there is nothing left to announce.
            Log::warning('Ahasend inbound: handoff payload is gone, nothing to process', [
                'delivery_id' => $this->deliveryId,
                'path' => $this->path,
            ]);

            return;
        }

        $verdict = $screen->screen($delivery);

        if ($verdict->defers()) {
            $this->release($verdict->delaySeconds);

            return;
        }

        if ($verdict->accepts()) {
            InboundMailReceived::dispatch(
                $delivery->message,
                $delivery->deliveryId,
                $delivery->deliveredForDomain,
                $delivery->route,
            );
        }

        $this->storage()->delete($this->path);
    }

    /**
     * The job failed for good.
     *
     * Past the retry window the screen is told it gave up and the body is
     * deleted. After repeated listener exceptions the body stays for whoever
     * investigates, until the prune command removes it.
     *
     * @param  Throwable  $exception  Why it failed.
     */
    public function failed(Throwable $exception): void
    {
        if (! $exception instanceof MaxAttemptsExceededException) {
            Log::error('Ahasend inbound: delivery failed, payload kept for inspection', [
                'delivery_id' => $this->deliveryId,
                'path' => $this->path,
                'exception' => $exception->getMessage(),
            ]);

            return;
        }

        $delivery = $this->delivery();

        if ($delivery !== null) {
            app(InboundScreen::class)->gaveUp($delivery);
        }

        $this->storage()->delete($this->path);
    }

    /**
     * The delivery, rebuilt from the stored body.
     */
    public function delivery(): ?InboundDelivery
    {
        $body = $this->storage()->get($this->path);

        if (! is_string($body) || $body === '') {
            return null;
        }

        /** @var array<string, mixed> $payload */
        $payload = (array) json_decode($body, true, flags: JSON_THROW_ON_ERROR);

        return new InboundDelivery(
            message: InboundMessage::fromPayload($payload),
            deliveryId: $this->deliveryId,
            deliveredForDomain: $this->deliveredForDomain,
            route: $this->routeId === null ? null : AhasendRoute::query()->find($this->routeId),
            channel: $this->channel,
        );
    }

    /**
     * The handoff disk.
     */
    private function storage(): Filesystem
    {
        return Storage::disk($this->disk);
    }
}
