<?php

declare(strict_types=1);

namespace GraystackIT\Ahasend\Services;

use GraystackIT\Ahasend\Connectors\AhasendConnector;
use GraystackIT\Ahasend\Data\SmtpCredential;
use GraystackIT\Ahasend\Exceptions\AhasendException;
use GraystackIT\Ahasend\Requests\SmtpCredentials\CreateSmtpCredentialRequest;
use GraystackIT\Ahasend\Requests\SmtpCredentials\DeleteSmtpCredentialRequest;
use GraystackIT\Ahasend\Requests\SmtpCredentials\GetSmtpCredentialRequest;
use GraystackIT\Ahasend\Requests\SmtpCredentials\ListSmtpCredentialsRequest;
use Illuminate\Support\Facades\Log;
use Saloon\Exceptions\Request\RequestException;

class SmtpCredentialService
{
    public function __construct(private readonly AhasendConnector $connector) {}

    /**
     * Create a new SMTP credential.
     *
     * The response will include the generated password — store it securely,
     * as the API will not return it again.
     *
     * @throws AhasendException
     */
    public function create(string $name): SmtpCredential
    {
        Log::info('Ahasend: creating SMTP credential', ['name' => $name]);

        try {
            $response   = $this->connector->send(new CreateSmtpCredentialRequest($name));
            $credential = SmtpCredential::fromArray($response->json());

            Log::info('Ahasend: SMTP credential created', ['id' => $credential->id, 'name' => $name]);

            return $credential;
        } catch (RequestException $e) {
            Log::error('Ahasend: failed to create SMTP credential', [
                'name'   => $name,
                'status' => $e->getResponse()->status(),
                'error'  => $e->getMessage(),
            ]);

            throw AhasendException::fromRequestException($e);
        }
    }

    /**
     * List all SMTP credentials.
     *
     * @return SmtpCredential[]
     * @throws AhasendException
     */
    public function list(): array
    {
        Log::info('Ahasend: listing SMTP credentials');

        try {
            $response = $this->connector->send(new ListSmtpCredentialsRequest());
            $body     = $response->json();

            return array_map(
                static fn (array $item): SmtpCredential => SmtpCredential::fromArray($item),
                $body['data'] ?? $body,
            );
        } catch (RequestException $e) {
            Log::error('Ahasend: failed to list SMTP credentials', [
                'status' => $e->getResponse()->status(),
                'error'  => $e->getMessage(),
            ]);

            throw AhasendException::fromRequestException($e);
        }
    }

    /**
     * Retrieve a single SMTP credential by ID.
     *
     * @throws AhasendException
     */
    public function get(string $credentialId): SmtpCredential
    {
        Log::info('Ahasend: fetching SMTP credential', ['credential_id' => $credentialId]);

        try {
            $response = $this->connector->send(new GetSmtpCredentialRequest($credentialId));

            return SmtpCredential::fromArray($response->json());
        } catch (RequestException $e) {
            Log::error('Ahasend: failed to get SMTP credential', [
                'credential_id' => $credentialId,
                'status'        => $e->getResponse()->status(),
                'error'         => $e->getMessage(),
            ]);

            throw AhasendException::fromRequestException($e);
        }
    }

    /**
     * Delete an SMTP credential by ID.
     *
     * @throws AhasendException
     */
    public function delete(string $credentialId): bool
    {
        Log::info('Ahasend: deleting SMTP credential', ['credential_id' => $credentialId]);

        try {
            $response = $this->connector->send(new DeleteSmtpCredentialRequest($credentialId));

            Log::info('Ahasend: SMTP credential deleted', ['credential_id' => $credentialId]);

            return $response->successful();
        } catch (RequestException $e) {
            Log::error('Ahasend: failed to delete SMTP credential', [
                'credential_id' => $credentialId,
                'status'        => $e->getResponse()->status(),
                'error'         => $e->getMessage(),
            ]);

            throw AhasendException::fromRequestException($e);
        }
    }
}
