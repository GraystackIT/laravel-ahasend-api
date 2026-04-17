<?php

declare(strict_types=1);

use GraystackIT\Ahasend\Connectors\AhasendConnector;
use GraystackIT\Ahasend\Data\SmtpCredential;
use GraystackIT\Ahasend\Exceptions\AhasendException;
use GraystackIT\Ahasend\Requests\SmtpCredentials\CreateSmtpCredentialRequest;
use GraystackIT\Ahasend\Requests\SmtpCredentials\DeleteSmtpCredentialRequest;
use GraystackIT\Ahasend\Requests\SmtpCredentials\GetSmtpCredentialRequest;
use GraystackIT\Ahasend\Requests\SmtpCredentials\ListSmtpCredentialsRequest;
use GraystackIT\Ahasend\Services\SmtpCredentialService;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

// ─── Container resolution ─────────────────────────────────────────────────

it('resolves SmtpCredentialService from the container', function (): void {
    expect(app(SmtpCredentialService::class))->toBeInstanceOf(SmtpCredentialService::class);
});

// ─── create() ─────────────────────────────────────────────────────────────

it('creates an SMTP credential and returns the DTO with password', function (): void {
    $mockClient = new MockClient([
        CreateSmtpCredentialRequest::class => MockResponse::make([
            'id'       => 'cred-001',
            'name'     => 'My App',
            'username' => 'smtp_my_app',
            'host'     => 'smtp.ahasend.com',
            'port'     => 587,
            'password' => 'generated-secret',
        ], 201),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service    = new SmtpCredentialService($connector);
    $credential = $service->create('My App');

    expect($credential)->toBeInstanceOf(SmtpCredential::class)
        ->and($credential->id)->toBe('cred-001')
        ->and($credential->name)->toBe('My App')
        ->and($credential->password)->toBe('generated-secret')
        ->and($credential->port)->toBe(587);
});

it('throws InvalidArgumentException when credential name is empty', function (): void {
    $service = new SmtpCredentialService(app(AhasendConnector::class));
    $service->create('');
})->throws(\InvalidArgumentException::class);

it('throws AhasendException on API error during create', function (): void {
    $mockClient = new MockClient([
        CreateSmtpCredentialRequest::class => MockResponse::make(
            ['error' => 'Unprocessable'],
            422,
        ),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new SmtpCredentialService($connector);
    $service->create('Bad Name');
})->throws(AhasendException::class);

// ─── list() ───────────────────────────────────────────────────────────────

it('lists SMTP credentials as an array of DTOs', function (): void {
    $mockClient = new MockClient([
        ListSmtpCredentialsRequest::class => MockResponse::make([
            'data' => [
                ['id' => 'cred-001', 'name' => 'App One', 'username' => 'user1', 'host' => 'smtp.ahasend.com', 'port' => 587],
                ['id' => 'cred-002', 'name' => 'App Two', 'username' => 'user2', 'host' => 'smtp.ahasend.com', 'port' => 587],
            ],
        ], 200),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service     = new SmtpCredentialService($connector);
    $credentials = $service->list();

    expect($credentials)->toHaveCount(2)
        ->and($credentials[0])->toBeInstanceOf(SmtpCredential::class)
        ->and($credentials[0]->id)->toBe('cred-001')
        ->and($credentials[1]->name)->toBe('App Two');
});

it('throws AhasendException on API error when listing credentials', function (): void {
    $mockClient = new MockClient([
        ListSmtpCredentialsRequest::class => MockResponse::make(['error' => 'Server error'], 500),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new SmtpCredentialService($connector);
    $service->list();
})->throws(AhasendException::class);

// ─── get() ────────────────────────────────────────────────────────────────

it('retrieves a single SMTP credential by ID', function (): void {
    $mockClient = new MockClient([
        GetSmtpCredentialRequest::class => MockResponse::make([
            'id'       => 'cred-001',
            'name'     => 'My App',
            'username' => 'smtp_my_app',
            'host'     => 'smtp.ahasend.com',
            'port'     => 587,
        ], 200),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service    = new SmtpCredentialService($connector);
    $credential = $service->get('cred-001');

    expect($credential)->toBeInstanceOf(SmtpCredential::class)
        ->and($credential->id)->toBe('cred-001');
});

it('throws InvalidArgumentException for empty credential ID in get', function (): void {
    $service = new SmtpCredentialService(app(AhasendConnector::class));
    $service->get('');
})->throws(\InvalidArgumentException::class);

it('throws AhasendException when credential is not found', function (): void {
    $mockClient = new MockClient([
        GetSmtpCredentialRequest::class => MockResponse::make(['error' => 'Not found'], 404),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new SmtpCredentialService($connector);
    $service->get('nonexistent');
})->throws(AhasendException::class);

// ─── delete() ─────────────────────────────────────────────────────────────

it('deletes an SMTP credential', function (): void {
    $mockClient = new MockClient([
        DeleteSmtpCredentialRequest::class => MockResponse::make([], 204),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new SmtpCredentialService($connector);
    $result  = $service->delete('cred-001');

    expect($result)->toBeTrue();
});

it('throws InvalidArgumentException for empty credential ID in delete', function (): void {
    $service = new SmtpCredentialService(app(AhasendConnector::class));
    $service->delete('');
})->throws(\InvalidArgumentException::class);

it('throws AhasendException when deleting a non-existent credential', function (): void {
    $mockClient = new MockClient([
        DeleteSmtpCredentialRequest::class => MockResponse::make(['error' => 'Not found'], 404),
    ]);

    $connector = app(AhasendConnector::class);
    $connector->withMockClient($mockClient);

    $service = new SmtpCredentialService($connector);
    $service->delete('nonexistent');
})->throws(AhasendException::class);
