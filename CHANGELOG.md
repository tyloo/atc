# Changelog

All notable changes to `tyloo/atc` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-05-12

Initial public release.

### Added

- `ApiTestCase` base class composing `InteractsWithApi`, `InteractsWithContainer`, and `InteractsWithAuth`.
- Optional traits:
  - `InteractsWithDatabase` — Doctrine row assertions (`assertDatabaseHas`, `assertDatabaseMissing`, `assertDatabaseCount`).
  - `InteractsWithMessenger` — in-memory transport capture + dispatch assertions.
  - `InteractsWithMailer` — in-memory mailer capture + sent-email assertions.
  - `InteractsWithNotifier` — in-memory notifier capture + sent-notification assertions.
  - `InteractsWithHttpClient` — `MockHttpClient` swap + recorded-request assertions.
  - `InteractsWithCache` — `ArrayAdapter` cache pool swap.
  - `InteractsWithProfiler` — Symfony Profiler access + DB query-count assertions for N+1 detection.
- Fluent `ApiResponse` with status, header, JSON, JSON Schema, JMESPath, and performance assertions.
- JSON Schema validation via `justinrainbow/json-schema`.
- JMESPath JSON queries via `mtdowling/jmespath.php`.
- `actingAs($user)` for stateless token-based authentication: default reads `$user->getApiToken()` and attaches a Bearer header; override `authenticate()` in your test case for JWT, HMAC, or any other strategy.
- `ServiceMocker` for container-aware service replacement: full mock, partial mock, arbitrary object injection, lazy default mocks.
- `ApiResponse::dump()` and `ApiResponse::dd()` for inspecting status, headers, duration, and decoded body inline in a chain. Optional JMESPath argument dumps just that slice. Uses `symfony/var-dumper` when available.
- No bundle, no YAML, no DI extension. Customisation is via protected method overrides on a base test case (`defaultMocks()`, `authenticate()`, `resolveDefaultHeaders()`, `resolveJsonSchemaBaseDir()`, `resolveMessengerTransports()`, `cachePoolIds()`, `resolveHttpClientStrict()`).
- PHP 8.3 / 8.4 / 8.5 × Symfony 6.4 / 7 / 8 × PHPUnit 12 / 13 compatibility matrix.

[0.1.0]: https://github.com/tyloo/atc/releases/tag/v0.1.0
