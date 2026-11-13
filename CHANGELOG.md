# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.1] - 2026-11-13

### Fixed
- The package no longer ships `vendor/` (PHPUnit and other dev dependencies) nor the tests and CI files. Version 0.2.0 on Packagist carried them by mistake; its code is the same, so upgrade to 0.2.1 for a clean install

## [0.2.0] - 2026-11-13

### Added
- `PairingFailed::fromEvent()`, `PairingSucceeded::fromEvent()` and `PairingFailureReason` for the `pairing.failed` / `pairing.succeeded` webhook events: `reason` is the closed set (`PairingFailureReason::ALL`) and a value a newer server adds still decodes (`isKnownReason()` tells them apart); treat it like `error`
- `Disconnected::fromEvent()` and `DisconnectReason` for the `disconnected` webhook event: `willReconnect` is always present, `reason` is the closed set (`DisconnectReason::ALL`) and a value a newer server adds still decodes (`isKnownReason()` tells them apart)
- `Webhook::verifyWebhook()` / `Webhook::verifyDelivery()`: verify `x-wi-signature-v2` (HMAC of `<t>.<body>`) with replay protection (default tolerance 300 s); fall back to the legacy `x-wi-signature` only when no v2 header is sent
- Tests with `receipt` payload fixtures (`status`, `rawType`, `messageIds`)

### Fixed
- README used a non-existent `getStatusCode()`; `WiException::$statusCode` is the property

## [0.1.1] - 2026-09-03

### Added
- Full PHPUnit test suite with 59 test cases covering client, session, and webhook flows
- Continuous integration with strict syntax checks and PHPUnit execution

### Fixed
- Added source and support repository links to `composer.json`
- Corrected homepage link to Packagist

## [0.1.0] - 2026-09-03

### Added
- Initial release of the official wi-api PHP SDK
- Ext-curl and ext-json based client with zero third-party dependencies
- Session lifecycle and complete WhatsApp messaging features
- Secure constant-time webhook signature verification
