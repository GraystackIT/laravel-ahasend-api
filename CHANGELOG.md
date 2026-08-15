# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

## [0.2.1] - 2026-08-15

### Fixed
- `AhasendDomain::expiresAt()` returned `?Illuminate\Support\Carbon`, which threw a `TypeError` in any application that calls `Date::use(CarbonImmutable::class)` — the model's `created_at` is then a `Carbon\CarbonImmutable`, and that is not a subtype of the declared return type. The method now returns `?Carbon\CarbonInterface`, so it works under either date factory.
- The `@property` annotations on `AhasendDomain`, `AhasendRoute` and `AhasendMessage` claimed `Carbon` for the timestamp columns for the same reason; they now say `CarbonInterface`.

## [0.2.0] - 2026-08-13

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

### Added — domain management, inbound routing

- **Domains API**: `CreateDomainRequest`, `GetDomainRequest`, `ListDomainsRequest`, `UpdateDomainRequest`, `CheckDomainDnsRequest`, `DeleteDomainRequest`, the `Domain` / `DnsRecord` DTOs, the `DnsRecordType` enum and `DomainService`.
- **Routes API** (inbound message routing): `CreateRouteRequest`, `GetRouteRequest`, `ListRoutesRequest`, `UpdateRouteRequest`, `DeleteRouteRequest`, the `Route` DTO and `RouteService`.
- **Persistence**: `ahasend_domains` and `ahasend_routes` tables with the `AhasendDomain` / `AhasendRoute` models. A domain carries an optional polymorphic owner so a consuming application can associate it with one of its own records without this package knowing what that record is.
- **`DomainManager`** — the domain lifecycle: `add()`, `refresh()`, `delete()`, `expirePending()`, `markDnsError()`. **AhaSend sends no event when a domain becomes valid** (the only domain event is `domain.dns_error`), so verification is poll-driven: an explicit DNS check on demand, plus the `ahasend:domains:poll` command.
- **One-open-domain-per-owner guard** in `DomainManager::add()`, ceiling configurable via `ahasend.domains.max_unverified_per_owner` (default 1) and backed on Postgres by a partial unique index, so two concurrent adds cannot slip past the application check. Owner-less domains are exempt. `ahasend:domains:expire` removes domains that stayed unverified past `pending_expiry_days` (default 14) so a typo cannot occupy a slot forever.
- **`RouteManager`** provisions a catch-all inbound route (`*@{domain}`) when a domain becomes verified, and retires it on delete. AhaSend's routes have no `domain` field — the owning domain is derived from the `recipient` pattern and must already be verified — so **each domain needs its own route**; there is no account-wide catch-all.
- **Inbound endpoints**, with the normalised `InboundMessage` / `InboundAttachment` DTOs and the `InboundMailReceived` event. A route's `recipient` pattern is domain-bound, so each receiving domain needs its own route and AhaSend generates a separate signing secret for each — the secret cannot be supplied at creation. Hence two endpoints: `POST {ahasend.inbound.static_path}` (default `ahasend/inboundmail`) for routes managed in the dashboard, verified against the `domain => secret` map in `ahasend.inbound.secrets` (parsed from `AHASEND_INBOUND_SECRETS` as `domain:secret,domain:secret` when the config is not published); and `POST {ahasend.inbound.path}/{route}` for routes the package provisions per customer domain, whose secrets are stored with the route because there is no config file to put them in. Listeners must queue their work — AhaSend retries on any non-2xx, and `InboundMailReceived::$deliveryId` is the idempotency key.
- **`InboundMailReceived::$deliveredForDomain`** — the domain whose secret verified the payload. A mail addressed to two of your domains matches two routes and is delivered **twice**, so consumers need to know which delivery they are looking at; branching on this field means each delivery reaches exactly one consumer. The payload's own `to` would answer the same question, but this one is authenticated rather than merely transmitted, and it stays unambiguous even if a route is set to group several recipients into one request.
- **Payload capture** (`AHASEND_INBOUND_CAPTURE_PATH`): writes every verified inbound and webhook payload out as JSON. Requires a configured path **and** the local or testing environment — payloads carry personal data and base64 attachment bytes and nothing prunes them, so a path set in production is ignored rather than obeyed. Real payloads are the only reliable source for what Ahasend sends, and the captured ones are committed as fixtures under `tests/Fixtures/Payloads/`.
- `InboundMessage::$recipient` — the routed recipient, read from the payload's top-level `to`; `deliveryAddresses()` returns it together with to and cc, most authoritative first. Verified against live mail: that field is **not** the `To:` header (which sits in `headers`), it is the address this particular delivery was routed to. A BCC'd address appears there and in no header, and two deliveries of one mail carry different values — so reading the header instead loses BCC'd mail and cannot tell two deliveries apart.
- Security hardening on the public endpoints: signed timestamps are checked against a tolerance window (`AHASEND_WEBHOOK_TOLERANCE_SECONDS`, default 300) so a captured request cannot be replayed indefinitely; all three routes carry a configurable rate limit (`AHASEND_INBOUND_THROTTLE`, default `120,1`); an unknown route id answers `401` like a bad signature so ids cannot be enumerated by response code; and an empty secret — configured or stored — answers `503` instead of accepting anything, because on a public endpoint "no secret" must never mean "no verification".
- **`ahasend:routes:import`** adopts a route created in the Ahasend dashboard: it stores the pasted signing secret (the API never hands it out again) and repoints the route at this application's inbound endpoint, whose URL carries the id the secret is looked up by.
- Lifecycle events: `DomainCreated`, `DomainVerified`, `DomainVerificationFailed`, `DomainExpired`, `DomainDeleted`, `InboundRouteProvisioned`.
- `Idempotency-Key` header on every write request, via the `HasIdempotencyKey` trait.
- `EmailMessage::withMessageId()` — returns a copy carrying a message id while preserving every other field.
- New config sections `ahasend.domains` and `ahasend.inbound`.

### Fixed
- **`message.routing` is no longer handled by the event webhook.** Routed inbound mail is signed with the route's own secret and belongs on the inbound endpoints; accepting it on `ahasend/webhook` too meant two verification rules for one delivery and processing it twice.
- Route provisioning is now safe under concurrency: `refresh()` takes a row lock, so a scheduled poll and a manual DNS check cannot both provision a route for the same domain, and the create carries a domain-derived idempotency key instead of a fresh one per call. Domains without an owner — an application's own, whose routes live in the dashboard — are never polled and never provisioned, which otherwise gave them a second route and doubled every delivery.
- A route Ahasend returns without a signing secret is deleted again and the provisioning fails loudly, instead of being stored and leaving an endpoint that verifies nothing.
- `DomainVerifyState::Checking` removed: with `refresh()` inside a transaction the state was never visible to anyone, and a failed DNS check now leaves the domain as it was instead of claiming a check ran and failed.
- **No request ever sent a body.** None of the POST/PUT request classes implemented `Saloon\Contracts\Body\HasBody`, and Saloon only reads `defaultBody()` from requests that do — so `SendEmailRequest`, `SendHtmlEmailRequest`, `SendEmailWithAttachmentsRequest`, `SendConversationalEmailRequest`, `CreateSuppressionRequest` and `CreateSmtpCredentialRequest` all went out with an empty body. They now implement `HasBody` and use `HasJsonBody`.
- `WebhookController` now degrades the affected domain to `failed` on `domain.dns_error` instead of only announcing it. Signature verification moved into the shared `VerifiesWebhookSignature` trait.

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
