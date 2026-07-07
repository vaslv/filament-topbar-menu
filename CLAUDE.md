# filament-topbar-menu

## Health Stack

There is no local PHP runtime on this machine. Run every tool through the
`php84-intl` Docker image (OrbStack):

```
docker run --rm -v "$PWD":/app -w /app php84-intl:latest <command>
```

- typecheck: vendor/bin/phpstan analyse --no-progress --memory-limit=512M
- lint: vendor/bin/pint --test
- test: vendor/bin/phpunit

There is no dead-code or shell-lint tool configured; `/health` skips those.
