# AGENTS.md

Guidance for AI coding agents working in this repository.

## What this repo is

`tyloo/atc` is a library providing a fluent base class (`Tyloo\Atc\ApiTestCase`) for
HTTP+JSON API testing on top of Symfony's `WebTestCase`. No bundle, no DI extension —
customisation is via protected method overrides. PHP ≥ 8.3, Symfony 6.4 | 7 | 8,
PHPUnit 12 | 13. MIT licensed.

## Scope (firm)

The library's purpose is precise: **fluent HTTP+JSON API testing for Symfony**.
The following are permanent non-goals — do not propose features for them:
GraphQL, browser/E2E, WebSockets, snapshot testing, OpenAPI contract validation,
custom Pest expectations, test data factories, database lifecycle management.
Users wanting those should use a different tool.

## Setup

```bash
composer install
```

## Build / verify commands

| Goal | Command |
|---|---|
| Run all tests | `vendor/bin/phpunit` |
| Run a single test | `vendor/bin/phpunit --filter ApiResponseStatusTest` |
| Coverage (HTML) | `vendor/bin/phpunit --coverage-html=build/coverage` |
| Format | `mago format` |
| Lint | `mago lint` |
| Static analysis | `mago analyze` |
| All quality gates | `mago format --check && mago lint && mago analyze && vendor/bin/phpunit` |

CI runs the same set across PHP 8.3 | 8.4 | 8.5 × Symfony 6.4 | 7 | 8 × prefer-lowest | prefer-stable (Symfony 8 + PHP 8.3 excluded — Symfony 8 requires PHP 8.4).

## Code style

- Strict types on every file (`declare(strict_types=1);`).
- `final` by default; `final readonly` for value objects and stateless services.
- `#[Override]` (imported via `use Override;`) on every overridden method (Mago enforces).
- PSR-4 autoloading from `src/` under `Tyloo\Atc\`.
- **No inline FQNs.** Every `\Foo`-style class reference must be imported and used bare. This applies to attributes, type hints, `instanceof`, `catch`, `throw new`, and root-namespace `@throws` annotations. Multi-segment `@throws` (e.g. `\PHPUnit\Framework\Foo`) stay inline.
- Use native `\assert($x instanceof Foo, 'message')` for runtime type narrowing. Cheap, no dependency, and Mago narrows through it.
- Comments are short, clean, and explain WHY (not WHAT). Default to none.
- Public DSL methods carry a one-line summary, `@param`/`@return`/`@throws` for non-obvious types, and a one-line `@example`. Internal helpers stay docblock-free unless `@throws`/`@param` is required for the analyzer.
- Test methods rely on `#[Test]` + a self-documenting name. No method docblocks.

## Test conventions

- `tests/Unit/` — pure PHPUnit, no kernel boot.
- `tests/Functional/` — kernel-boot tests via fixture kernels in `tests/Fixtures/Kernel/`.
- `tests/Fixtures/` — controllers, entities, services used by tests; never used at runtime.
- One test class per source class (`Foo` → `FooTest`); split by responsibility when a single class needs > 300 lines of tests (see `Http/ApiResponse*Test.php`).

## Commit + PR conventions

- Conventional Commits (commitlint enforces both PR titles and individual commits).
- Allowed types: `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `build`, `ci`, `chore`.
- Keep PRs focused. If you find unrelated cleanup, open a separate PR.

## Don'ts

- Don't change the public DSL signatures without a CHANGELOG entry under `[Unreleased]`.
- Don't add new direct dependencies. Suggest via `suggest:` in `composer.json` if the feature is opt-in and only needed for a specific trait.
- Don't disable `mago analyze` baseline rules. If you must ignore, use a per-line `@mago-ignore` with a comment explaining why.
- Don't reformat unrelated files. Keep diffs reviewable.

## When in doubt

The trait + `ApiResponse` classes are the documented public surface; everything else is internal. See [`README.md`](README.md) for usage examples and the extension points.
