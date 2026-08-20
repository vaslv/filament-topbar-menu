# filament-topbar-menu

Guidance for AI coding agents (and humans) working in this repository.

## Commands

Install dependencies with `composer install`, then use the Composer scripts:

- typecheck: `composer analyse` (PHPStan / Larastan)
- lint: `composer lint:test` (Pint, check-only) — use `composer lint` to autofix
- test: `composer test` (PHPUnit)
- all of the above: `composer check`

## Requirements

Requires PHP 8.2+ (see `composer.json`). If you have no local PHP runtime,
prefix any command with a Docker wrapper, e.g.:

```
docker run --rm -v "$PWD":/app -w /app php84-intl:latest composer check
```

There is no dead-code or shell-lint tool configured.

`composer.lock` is not committed, so every leg resolves its own dependencies.
That matters: PHP's own behavior differs between versions (`filter_var()`
classifies `::ffff:127.0.0.1` as public on 8.2 and reserved from 8.3, which is
how an SSRF hole once reached a release), and so does the dependency set.

CI covers the axes that have bitten this package:

- PHP 8.2, 8.3 and 8.4, each resolving the newest dependencies it can.
- PHP 8.2 with `--prefer-lowest`, so the bottom of every constraint runs too.
- MySQL 8 and PostgreSQL 16. The package ships hand-written SQL
  (`scopeTreeOrdered()`) and migrations written around driver behaviour;
  SQLite tolerates shapes MySQL rejects. Point the suite at a server locally
  with `TOPBAR_TEST_DB=pgsql` plus the usual `DB_*` variables.
- One leg without `ext-intl`, which the package supports and guards for.

A local run covers the first line of that list only, so read CI before tagging
rather than treating a green local run as the answer.

## Releasing

Tags (`vX.Y.Z`) define versions; `main` is protected against force-push, so a
tag can never be corrected after it is pushed. Sequence every release as:

1. Rename the `## [Unreleased]` heading in `CHANGELOG.md` to the exact version
   being tagged (e.g. `## [1.6.0]`) — **the tag name must appear in
   CHANGELOG.md before anything is pushed**. Update README if needed.
2. Commit the docs (or amend them into the release commit).
3. `git push`, then `git tag -a vX.Y.Z -m "vX.Y.Z"` and push the tag.

Never push a release whose changelog still says `Unreleased`, and never create
a new tag just to fix docs — a docs commit on `main` is enough.

## Conventions

- Translations live in `resources/lang/{locale}/filament-topbar-menu.php`; keep
  every locale's key set identical to `en`.
- Follow the existing code style — Pint enforces it.
