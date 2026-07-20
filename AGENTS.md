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

## Conventions

- Translations live in `resources/lang/{locale}/filament-topbar-menu.php`; keep
  every locale's key set identical to `en`.
- Follow the existing code style — Pint enforces it.
