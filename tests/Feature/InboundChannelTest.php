<?php

declare(strict_types=1);

use GraystackIT\Ahasend\Contracts\InboundScreen;
use GraystackIT\Ahasend\Data\InboundDelivery;
use GraystackIT\Ahasend\Data\ScreenVerdict;
use GraystackIT\Ahasend\Events\InboundMailReceived;
use GraystackIT\Ahasend\Jobs\ProcessInboundDelivery;
use GraystackIT\Ahasend\Support\InboundChannels;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * A screen whose verdict a test sets, and which remembers what it was asked.
 */
final class RecordingScreen implements InboundScreen
{
    /** @var list<InboundDelivery> */
    public array $screened = [];

    /** @var list<InboundDelivery> */
    public array $gaveUp = [];

    public function __construct(public ScreenVerdict $verdict) {}

    public function screen(InboundDelivery $delivery): ScreenVerdict
    {
        $this->screened[] = $delivery;

        return $this->verdict;
    }

    public function gaveUp(InboundDelivery $delivery): void
    {
        $this->gaveUp[] = $delivery;
    }
}

function useScreen(ScreenVerdict $verdict): RecordingScreen
{
    $screen = new RecordingScreen($verdict);
    app()->instance(InboundScreen::class, $screen);

    return $screen;
}

function payslipChannel(?string $secret = 'whsec_payslip', ?string $queue = null): void
{
    app(InboundChannels::class)->register('payslip', 'payslip.graystack.one', $secret, $queue);
}

/**
 * @return array<string, mixed>
 */
function payslipPayload(): array
{
    return routingPayload(['to' => 'acme@payslip.graystack.one', 'recipient' => 'acme@payslip.graystack.one']);
}

it('accepts a delivery signed with the channel secret', function (): void {
    Event::fake([InboundMailReceived::class]);
    payslipChannel();
    $payload = payslipPayload();

    $this->withHeaders(signedHeaders($payload, 'whsec_payslip'))
        ->postJson('/ahasend/inboundmail/payslip', $payload)
        ->assertOk();

    Event::assertDispatched(InboundMailReceived::class, fn (InboundMailReceived $event): bool => $event->deliveredForDomain === 'payslip.graystack.one'
        && $event->deliveryId === 'whmsg_1');
});

it('rejects a delivery signed with another channel secret', function (): void {
    Event::fake([InboundMailReceived::class]);
    payslipChannel();
    app(InboundChannels::class)->register('postbox', 'postbox.graystack.one', 'whsec_postbox');
    $payload = payslipPayload();

    $this->withHeaders(signedHeaders($payload, 'whsec_postbox'))
        ->postJson('/ahasend/inboundmail/payslip', $payload)
        ->assertUnauthorized();

    Event::assertNotDispatched(InboundMailReceived::class);
});

it('answers an unknown channel the same way as a bad signature', function (): void {
    $payload = payslipPayload();

    $this->withHeaders(signedHeaders($payload, 'whsec_payslip'))
        ->postJson('/ahasend/inboundmail/nothing', $payload)
        ->assertUnauthorized()
        ->assertJson(['error' => 'Invalid signature']);
});

it('refuses a channel without any secret', function (): void {
    config()->set('ahasend.inbound.secrets', []);
    payslipChannel(secret: null);
    $payload = payslipPayload();

    $this->withHeaders(signedHeaders($payload, 'whsec_payslip'))
        ->postJson('/ahasend/inboundmail/payslip', $payload)
        ->assertStatus(503);
});

it('falls back to the configured secret of the channel domain', function (): void {
    Event::fake([InboundMailReceived::class]);
    config()->set('ahasend.inbound.secrets', ['payslip.graystack.one' => 'whsec_shared']);
    payslipChannel(secret: '');
    $payload = payslipPayload();

    $this->withHeaders(signedHeaders($payload, 'whsec_shared'))
        ->postJson('/ahasend/inboundmail/payslip', $payload)
        ->assertOk();

    Event::assertDispatched(InboundMailReceived::class);
});

it('lets the legacy shared endpoint verify with channel secrets', function (): void {
    Event::fake([InboundMailReceived::class]);
    config()->set('ahasend.inbound.secrets', []);
    payslipChannel();
    $payload = payslipPayload();

    $this->withHeaders(signedHeaders($payload, 'whsec_payslip'))
        ->postJson('/ahasend/inboundmail', $payload)
        ->assertOk();

    Event::assertDispatched(InboundMailReceived::class);
});

it('throttles per channel and caller', function (): void {
    Event::fake([InboundMailReceived::class]);
    config()->set('ahasend.inbound.channel_throttle', 2);
    payslipChannel();
    app(InboundChannels::class)->register('postbox', 'postbox.graystack.one', 'whsec_postbox');

    $this->postJson('/ahasend/inboundmail/payslip', [])->assertUnauthorized();
    $this->postJson('/ahasend/inboundmail/payslip', [])->assertUnauthorized();
    $this->postJson('/ahasend/inboundmail/payslip', [])->assertTooManyRequests();

    // Another channel keeps its own budget.
    $payload = routingPayload(['to' => 'x@postbox.graystack.one']);
    $this->withHeaders(signedHeaders($payload, 'whsec_postbox'))
        ->postJson('/ahasend/inboundmail/postbox', $payload)
        ->assertOk();
});

it('hands off to the queue without announcing anything inside the request', function (): void {
    Event::fake([InboundMailReceived::class]);
    Queue::fake();
    Storage::fake('handoff');
    config()->set('ahasend.inbound.handoff.disk', 'handoff');
    config()->set('ahasend.inbound.handoff.queue', 'inbound-mail');
    payslipChannel();
    $payload = payslipPayload();

    $this->withHeaders(signedHeaders($payload, 'whsec_payslip'))
        ->postJson('/ahasend/inboundmail/payslip', $payload)
        ->assertOk();

    Event::assertNotDispatched(InboundMailReceived::class);
    Queue::assertPushedOn('inbound-mail', ProcessInboundDelivery::class, function (ProcessInboundDelivery $job) use ($payload): bool {
        return $job->channel === 'payslip'
            && $job->deliveryId === 'whmsg_1'
            && $job->deliveredForDomain === 'payslip.graystack.one'
            && json_decode(Storage::disk('handoff')->get($job->path), true) === $payload;
    });
});

it('uses the queue of the channel over the default one', function (): void {
    Queue::fake();
    Storage::fake('handoff');
    config()->set('ahasend.inbound.handoff.disk', 'handoff');
    config()->set('ahasend.inbound.handoff.queue', 'inbound-mail');
    payslipChannel(queue: 'payslip-mail');
    $payload = payslipPayload();

    $this->withHeaders(signedHeaders($payload, 'whsec_payslip'))
        ->postJson('/ahasend/inboundmail/payslip', $payload)
        ->assertOk();

    Queue::assertPushedOn('payslip-mail', ProcessInboundDelivery::class);
});

/**
 * Store a body the way the controller does and build the job for it.
 */
function handoffJob(?array $payload = null): ProcessInboundDelivery
{
    Storage::fake('handoff');
    Storage::disk('handoff')->put('ahasend-inbound/2026-10-12/a.json', json_encode($payload ?? payslipPayload()));

    return new ProcessInboundDelivery('handoff', 'ahasend-inbound/2026-10-12/a.json', 'whmsg_1', 'payslip.graystack.one', null, 'payslip');
}

it('announces an accepted delivery from the worker and deletes its body', function (): void {
    Event::fake([InboundMailReceived::class]);
    $screen = useScreen(ScreenVerdict::accept());
    $job = handoffJob();

    $job->handle($screen);

    expect($screen->screened)->toHaveCount(1)
        ->and($screen->screened[0]->channel)->toBe('payslip')
        ->and($screen->screened[0]->message->messageId)->toBe('msg-abc');
    Event::assertDispatched(InboundMailReceived::class, fn (InboundMailReceived $event): bool => $event->deliveryId === 'whmsg_1');
    Storage::disk('handoff')->assertMissing('ahasend-inbound/2026-10-12/a.json');
});

it('drops a discarded delivery without any listener seeing it', function (): void {
    Event::fake([InboundMailReceived::class]);
    $screen = useScreen(ScreenVerdict::discard());
    $job = handoffJob();

    $job->handle($screen);

    Event::assertNotDispatched(InboundMailReceived::class);
    Storage::disk('handoff')->assertMissing('ahasend-inbound/2026-10-12/a.json');
});

it('releases a deferred delivery and keeps its body', function (): void {
    Event::fake([InboundMailReceived::class]);
    $screen = useScreen(ScreenVerdict::defer(300));
    $job = handoffJob()->withFakeQueueInteractions();

    $job->handle($screen);

    $job->assertReleased(300);
    Event::assertNotDispatched(InboundMailReceived::class);
    Storage::disk('handoff')->assertExists('ahasend-inbound/2026-10-12/a.json');
});

it('tells the screen it gave up once the retry window has closed', function (): void {
    $screen = useScreen(ScreenVerdict::defer(300));
    $job = handoffJob();

    $job->failed(new MaxAttemptsExceededException('too long'));

    expect($screen->gaveUp)->toHaveCount(1);
    Storage::disk('handoff')->assertMissing('ahasend-inbound/2026-10-12/a.json');
});

it('keeps the body after repeated listener exceptions', function (): void {
    $screen = useScreen(ScreenVerdict::accept());
    $job = handoffJob();

    $job->failed(new RuntimeException('listener broke'));

    expect($screen->gaveUp)->toBe([]);
    Storage::disk('handoff')->assertExists('ahasend-inbound/2026-10-12/a.json');
});

it('keeps the body when a listener throws so the retry can run', function (): void {
    useScreen(ScreenVerdict::accept());
    Event::listen(InboundMailReceived::class, fn () => throw new RuntimeException('listener broke'));
    $job = handoffJob();

    expect(fn () => $job->handle(app(InboundScreen::class)))->toThrow(RuntimeException::class);
    Storage::disk('handoff')->assertExists('ahasend-inbound/2026-10-12/a.json');
});

it('bounds deferrals by the configured retry window', function (): void {
    config()->set('ahasend.inbound.handoff.retry_hours', 24);
    $this->travelTo(now()->startOfMinute());

    expect(handoffJob()->retryUntil()->getTimestamp())->toBe(now()->addHours(24)->getTimestamp());
});

it('screens inline when no handoff queue is configured', function (): void {
    Event::fake([InboundMailReceived::class]);
    $screen = useScreen(ScreenVerdict::discard());
    payslipChannel();
    $payload = payslipPayload();

    $this->withHeaders(signedHeaders($payload, 'whsec_payslip'))
        ->postJson('/ahasend/inboundmail/payslip', $payload)
        ->assertOk();

    expect($screen->screened)->toHaveCount(1);
    Event::assertNotDispatched(InboundMailReceived::class);
});

it('asks Ahasend to retry an inline deferral later', function (): void {
    Event::fake([InboundMailReceived::class]);
    useScreen(ScreenVerdict::defer(120));
    payslipChannel();
    $payload = payslipPayload();

    $this->withHeaders(signedHeaders($payload, 'whsec_payslip'))
        ->postJson('/ahasend/inboundmail/payslip', $payload)
        ->assertStatus(503)
        ->assertHeader('Retry-After', '120');

    Event::assertNotDispatched(InboundMailReceived::class);
});

it('refuses a channel name that cannot be a URL segment', function (): void {
    app(InboundChannels::class)->register('Pay Slip', 'payslip.graystack.one');
})->throws(InvalidArgumentException::class);

it('prunes handoff bodies past the cut-off and keeps recent ones', function (): void {
    Storage::fake('handoff');
    config()->set('ahasend.inbound.handoff.disk', 'handoff');
    $this->travelTo('2026-10-12 08:00:00');
    Storage::disk('handoff')->put('ahasend-inbound/2026-10-08/old.json', '{}');
    Storage::disk('handoff')->put('ahasend-inbound/2026-10-11/new.json', '{}');

    $this->artisan('ahasend:prune-inbound-handoffs', ['--days' => 3])->assertSuccessful();

    Storage::disk('handoff')->assertMissing('ahasend-inbound/2026-10-08/old.json');
    Storage::disk('handoff')->assertExists('ahasend-inbound/2026-10-11/new.json');
});
