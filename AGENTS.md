# filament-topbar-menu

Guidance for AI coding agents (and humans) working in this repository.

## Commands

Install dependencies with `composer install`, then use the Composer scripts:

- typecheck: `composer analyse` (PHPStan / Larastan)
- lint: `composer lint:test` (Pint, check-only) — use `composer lint` to autofix
- test: `composer test` (PHPUnit)
- all of the above: `composer check`

## Requirements

Requires PHP 8.4+. If you have no local PHP runtime, prefix any command with a
Docker wrapper, e.g.:

```
docker run --rm -v "$PWD":/app -w /app php84-intl:latest composer check
```

There is no dead-code or shell-lint tool configured.

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
