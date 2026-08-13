<?php

declare(strict_types=1);

use GraystackIT\Ahasend\Support\PayloadCapture;
use Illuminate\Http\Request;

beforeEach(function (): void {
    $this->capturePath = sys_get_temp_dir() . '/ahasend-capture-' . bin2hex(random_bytes(4));
    config()->set('ahasend.inbound.capture_path', $this->capturePath);
});

afterEach(function (): void {
    if (! is_dir($this->capturePath)) {
        return;
    }

    array_map('unlink', glob($this->capturePath . '/*.json') ?: []);
    rmdir($this->capturePath);
});

function capturedFiles(string $path): array
{
    return glob($path . '/*.json') ?: [];
}

function inboundRequest(): Request
{
    return Request::create(
        '/ahasend/inboundmail',
        'POST',
        server: ['HTTP_WEBHOOK_ID' => 'whmsg_1', 'CONTENT_TYPE' => 'application/json'],
        content: json_encode(['type' => 'message.routing', 'data' => ['message_id' => 'msg-1']], JSON_THROW_ON_ERROR),
    );
}

it('writes a payload when a path is configured', function (): void {
    PayloadCapture::write(inboundRequest(), 'tickets.example.com', ['delivered_for_domain' => 'tickets.example.com']);

    $files = capturedFiles($this->capturePath);

    expect($files)->toHaveCount(1);

    $written = json_decode((string) file_get_contents($files[0]), true, 512, JSON_THROW_ON_ERROR);

    expect($written['delivered_for_domain'])->toBe('tickets.example.com')
        ->and($written['payload']['data']['message_id'])->toBe('msg-1')
        ->and($written['headers']['webhook-id'])->toBe('whmsg_1');
});

it('writes nothing without a configured path', function (): void {
    config()->set('ahasend.inbound.capture_path', null);

    PayloadCapture::write(inboundRequest(), 'tickets.example.com');

    expect(is_dir($this->capturePath))->toBeFalse();
});

it('refuses to capture outside local and testing', function (): void {
    // Payloads carry personal data and attachment bytes, and nothing prunes
    // them — a configured path must not be enough to fill a production disk.
    app()->detectEnvironment(fn (): string => 'production');

    PayloadCapture::write(inboundRequest(), 'tickets.example.com');

    expect(capturedFiles($this->capturePath))->toBeEmpty();
});

it('captures in local', function (): void {
    app()->detectEnvironment(fn (): string => 'local');

    PayloadCapture::write(inboundRequest(), 'tickets.example.com');

    expect(capturedFiles($this->capturePath))->toHaveCount(1);
});
