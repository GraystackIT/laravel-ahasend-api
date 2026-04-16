# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

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
