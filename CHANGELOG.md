# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

This release corrects the package against AhaSend's current v2 API (audited 2026-07-17 against the live OpenAPI spec and reference docs). Several endpoints — most critically authentication — were built against incorrect or outdated assumptions and would fail against a real account. All changes below are **breaking** unless noted otherwise; this package is pre-1.0, so no deprecation shims are provided.

### Fixed
- **Authentication was completely broken.** `AhasendConnector` now sends `Authorization: Bearer {api_key}` instead of `X-Api-Key`. The v2 API only accepts Bearer authentication — `X-Api-Key` is exclusive to the deprecated v1 API and caused every live request to fail with `401 Unauthorized`.
- `BounceStatisticsRequest` and `DeliveryTimeAnalyticsRequest` now call the correct `/statistics/transactional/bounce` and `/statistics/transactional/delivery-time` paths. The previous `/reports/bounces` and `/reports/delivery-time` paths do not exist on the v2 API and returned 404.
- `DeleteSuppressionRequest` now deletes via `DELETE /suppressions?email=...` (query parameter) instead of `DELETE /suppressions/{email}` (path segment), matching the real API contract.
- `DeleteAllSuppressionsRequest` now calls `DELETE /suppressions/all` instead of `DELETE /suppressions`.

### Changed
- `ReportService::bounceStatistics()`, `deliverabilityBreakdown()`, and `deliveryTimeAnalytics()` now return an array of time-bucketed DTOs (`BounceStatistics[]`, `DeliverabilityBreakdown[]`, `DeliveryTimeAnalytics[]`) instead of a single flat aggregate object, matching AhaSend's actual bucketed statistics response. The previous DTOs read fields (`total_sent`, `hard_bounces`, `average_delivery_seconds`, `domains`, ...) that don't exist in the real response and always evaluated to zero/default values regardless of actual account activity.
- `Message` DTO now exposes `sender`/`recipient` (single email strings) instead of `fromEmail`/`fromName`/`to`/`cc`, matching the real `GET /messages/{id}` response shape. `scheduledAt` removed (not present in the API); `deliveredAt`/`updatedAt` added.
- `MessageStatus` enum values corrected to the real API vocabulary: `Queued`, `Scheduled`, `Delivered`, `Deferred`, `Bounced`, `Failed`, `Suppressed`. Removed non-existent values (`Sent`, `Opened`, `Clicked`, `Cancelled`); added missing ones (`Deferred`, `Suppressed`).
- `MessageService::list()` now returns a `pagination` key (`has_more`, `next_cursor`, `previous_cursor`) instead of `meta`, matching the real list-messages response envelope.
- `Suppression` DTO now exposes `id` and `expiresAt`; the `type` property and `SuppressionType` enum were removed entirely — no such field exists on the API's suppression resource (this was previously always guessed as `manual` since `type` never appeared in real responses).
- `SuppressionService::delete()` and `deleteAll()` now accept an optional `$domain` filter, matching the real query parameters.
- `SmtpCredential::fromArray()` default host fallback corrected from `smtp.ahasend.com` to `send.ahasend.com` (EU). AhaSend's SMTP relay never used the `smtp.ahasend.com` hostname; real hosts are `send.ahasend.com` (EU) / `send-us.ahasend.com` (US) on ports 25, 587, or 2525 — port 465 (implicit TLS) is not supported.

### Added
- `ListMessagesRequest` / `MessageService::list()`: new optional filters `status`, `sender`, `recipient`, `tags`, `from_time`, `to_time`.
- `ListSuppressionsRequest` / `SuppressionService::list()`: new optional filters `from_time`, `to_time`.
- `BounceStatisticsRequest` / `DeliveryTimeAnalyticsRequest`: new optional filters `recipient_domains`, `tags`, `group_by`, matching the full parameter set already supported by the deliverability endpoint.
- `EmailMessage` DTO: new optional send fields `replyTo`, `headers`, `ampContent`, `sandbox` (boolean, distinct from the existing `sandboxResult` enum) — passed through to all four send request classes.

---

## [0.1.1] - 2026-06-19

### Fixed
- Inbound **message routing** now dispatches `MailReceived`. AhaSend delivers inbound emails as `message.routing` (not `message.reception`) with the recipient under `data.to`; the webhook controller now handles that event type and resolves the recipient from `recipient` / `email` / `to`. Previously inbound mail was logged as an "unhandled event" and dropped.

## [0.1.0] - 2026-06-19

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
