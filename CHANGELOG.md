# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

### Added
- `EmailMessage` DTO: new optional send fields `tags`, `tracking`, `schedule`, `retention`, `substitutions`, `sandboxResult` — passed through to all four send request classes
- New webhook events: `MailClicked`, `MailSuppressed`, `MailTransientError`, `MailReceived`, `DomainDnsError`, `SuppressionCreated`
- `WebhookController` now dispatches all six new event types and maps them to correct status strings in the database driver
- Guard in `WebhookController::persistStatusUpdate()` to skip DB update for non-message events (e.g. `domain.dns_error`, `suppression.created`) that carry no message ID

### Changed
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
