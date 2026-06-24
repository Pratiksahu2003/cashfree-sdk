# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-06-24

### Added

- Cashfree PG v3 API client for orders, status, and refunds
- HMAC-SHA256 webhook signature verification
- Laravel auto-discovery with service provider, facade, config, and migrations
- Optional `cashfree_payments` Eloquent model and migration
- Configurable API logging with secret key redaction
- Automatic retries for transient network and API failures
- PHPUnit test suite for webhook verification and configuration

[1.0.0]: https://github.com/Pratiksahu2003/cashfree-sdk/releases/tag/v1.0.0
