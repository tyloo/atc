# Contributing to atc

Thanks for taking the time to contribute! This guide covers everything you need to ship a change against `tyloo/atc`.

## Code of conduct

This project adheres to a [Code of Conduct](CODE_OF_CONDUCT.md). By participating, you agree to uphold it.

## Setting up locally

```bash
git clone https://github.com/tyloo/atc.git
cd atc
composer install
vendor/bin/phpunit
```

## Quality gates (run before pushing)

| Goal | Command |
|---|---|
| Format check | `mago format --check` |
| Apply formatter | `mago format` |
| Lint | `mago lint` |
| Static analysis | `mago analyze` |
| Tests | `vendor/bin/phpunit` |
| Coverage HTML | `vendor/bin/phpunit --coverage-html=build/coverage` |

CI re-runs all of these across PHP 8.4 | 8.5 × Symfony 7 | 8 × prefer-lowest | prefer-stable. A green local run is the minimum bar for opening a PR.

## Branch naming

- `feat/<short-description>`
- `fix/<short-description>`
- `docs/<short-description>`
- `chore/<short-description>`

## Commit messages

[Conventional Commits](https://www.conventionalcommits.org/), enforced by commitlint:

```text
feat: add assertJsonExact helper
fix(http): preserve query string on PATCH
docs: clarify mockService default behaviour
```

Allowed types: `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `build`, `ci`, `chore`. Both your PR title and every individual commit must follow this.

## Pull requests

1. Open the PR against `main`.
2. Fill out the PR template (summary, type, test plan, breaking changes, checklist).
3. Make sure the CI matrix passes — six jobs, all must be green.
4. If you change the public DSL, add a `## [Unreleased]` entry to `CHANGELOG.md`.
5. Squash-merge is the default. Keep your PR title clean — it becomes the merge commit.

## Adding a new trait

1. Mirror the file/test layout (`src/Trait/InteractsWithFoo.php` + tests).
2. Document the new trait with a usage example in [`README.md`](README.md).
3. Update `composer.json` `suggest:` entry if it's gated on an optional dep.
4. Add a `## [Unreleased]` CHANGELOG entry.

## Reporting bugs / requesting features

Use the GitHub issue templates. They guide you through the minimum information needed to act on the report.

## Reporting security vulnerabilities

**Do not open a public issue.** See [SECURITY.md](SECURITY.md).

## License

By contributing, you agree your contributions are licensed under MIT (see [LICENSE](LICENSE)).
