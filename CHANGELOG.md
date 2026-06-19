# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

### Added
- `EmailMessage` DTO: new optional send fields `tags`, `tracking`, `schedule`, `retention`, `substitutions`, `sandboxResult` — passed through to all four send request classes
- New webhook events: `MailClicked`, `MailSuppressed`, `MailTransientError`, `MailReceived`, `DomainDnsError`, `SuppressionCreated`
- `WebhookController` now dispatches all six new event types and maps them to correct status strings in the database driver
- Guard in `WebhookController::persistStatusUpdate()` to skip DB update for non-message events (e.g. `domain.dns_error`, `suppression.created`) that carry no message ID
- `phpunit.xml.dist` with the test suites and dummy AhaSend credentials, so the test suite is self-contained and no longer depends on environment variables being present
- `scripts/release.sh` release helper (semver tag bump + CHANGELOG roll + tag/push), mirroring the other GraystackIT packages

### Changed
- Laravel 13 / Symfony 8 support: widened `symfony/mailer` and `symfony/mime` to `^6.4|^7.0|^8.0` and `php` to `^8.2|^8.3|^8.4`. The package now installs cleanly alongside Laravel 13 (which ships Symfony 8); the `AhaSendTransport` API surface is unchanged
- `AhasendService::send()` preserves all new optional fields when copying `EmailMessage` to assign an auto-generated UUID
- `SendConversationalEmailRequest` applies all optional fields except `substitutions` (the conversational endpoint does not support template substitution)

---

## Initial release

### Added
- Initial release of `graystackit/laravel-ahasend-api`
- Saloon v4 connector with API key authentication
- `SendEmailRequest`, `SendHtmlEmailRequest`, `SendEmailWithAttachmentsRequest`
- `AhasendService` with `sendText`, `sendHtml`, `sendWithAttachments` convenience methods
- Automatic request-type selection based on message content
- Auto-generated UUID `message_id` for correlation
- Retry handling via Saloon's `sendAndRetry`
- Webhook controller with HMAC-SHA256 signature verification
- Events: `MailSent`, `MailDelivered`, `MailOpened`, `MailFailed`, `MailBounced`
- Configurable storage: `log` (default) or `database`
- `ahasend_messages` migration for database driver
- `TracksAhasendMail` trait for use in Laravel Mailables
- Full Pest test suite (unit + feature)
- Auto-discovery via `extra.laravel.providers` in `composer.json`
