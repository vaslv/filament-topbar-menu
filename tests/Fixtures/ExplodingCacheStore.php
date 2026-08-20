<?php

namespace Vaslv\FilamentTopbarMenu\Tests\Fixtures;

use Illuminate\Cache\ArrayStore;
use RuntimeException;

/**
 * A cache store that fails for the menu's own keys and works for everything
 * else, standing in for the store being down — a Redis refusing connections is
 * the realistic shape of it.
 *
 * A real store rather than a mock, because what is under test is what escapes
 * `Cache::remember()` into the render hook. Scoped to the menu's keys because
 * the test process itself caches (Testbench, the framework), and a store that
 * failed for everything would take the test runner down instead of the code
 * under test.
 */
class ExplodingCacheStore extends ArrayStore
{
    /**
     * Substring identifying the keys this store refuses to serve.
     */
    public const FAILING_KEY = 'filament-topbar-menu';

    public function get($key): mixed
    {
        $this->explodeOn($key);

        return parent::get($key);
    }

    public function put($key, $value, $seconds): bool
    {
        $this->explodeOn($key);

        return parent::put($key, $value, $seconds);
    }

    public function forget($key): bool
    {
        $this->explodeOn($key);

        return parent::forget($key);
    }

    protected function explodeOn(string $key): void
    {
        if (str_contains($key, self::FAILING_KEY)) {
            throw new RuntimeException('Connection refused [tcp://127.0.0.1:6379]');
        }
    }
}
