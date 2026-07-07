<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use Illuminate\Support\Facades\Http;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;

class RefreshFaviconsCommandTest extends TestCase
{
    public function test_it_fills_missing_favicons_for_external_url_items(): void
    {
        Http::fake([
            '*/favicon.ico' => Http::response('icon-bytes', 200, ['Content-Type' => 'image/x-icon']),
        ]);

        $missing = TopbarMenuItem::create([
            'label' => 'External',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://example.com',
        ]);

        $existing = TopbarMenuItem::create([
            'label' => 'Already has one',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://other.com',
            'favicon_url' => 'https://other.com/manually-set.png',
        ]);

        $routeItem = TopbarMenuItem::create([
            'label' => 'Internal',
            'type' => TopbarMenuItem::TYPE_ROUTE,
            'route' => 'dashboard',
        ]);

        $this->artisan('filament-topbar-menu:refresh-favicons')->assertSuccessful();

        $this->assertSame('https://example.com/favicon.ico', $missing->refresh()->favicon_url);
        $this->assertSame('https://other.com/manually-set.png', $existing->refresh()->favicon_url);
        $this->assertNull($routeItem->refresh()->favicon_url);
    }

    public function test_force_option_refreshes_existing_favicons_too(): void
    {
        Http::fake([
            '*/favicon.ico' => Http::response('icon-bytes', 200, ['Content-Type' => 'image/x-icon']),
        ]);

        $existing = TopbarMenuItem::create([
            'label' => 'Already has one',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://other.com',
            'favicon_url' => 'https://other.com/stale.png',
        ]);

        $this->artisan('filament-topbar-menu:refresh-favicons', ['--force' => true])->assertSuccessful();

        $this->assertSame('https://other.com/favicon.ico', $existing->refresh()->favicon_url);
    }

    public function test_it_does_not_break_on_labels_containing_console_style_tags(): void
    {
        Http::fake([
            '*/favicon.ico' => Http::response('icon-bytes', 200, ['Content-Type' => 'image/x-icon']),
        ]);

        // A stored label containing Symfony console markup must not abort the
        // command: the output is escaped, so `<fg=bogus>` (an invalid style tag
        // that would otherwise throw) is printed literally.
        TopbarMenuItem::create([
            'label' => 'Deploy <fg=bogus>prod</>',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://example.com',
        ]);

        $this->artisan('filament-topbar-menu:refresh-favicons')->assertSuccessful();
    }

    public function test_it_is_a_noop_success_when_favicons_are_disabled(): void
    {
        config()->set('filament-topbar-menu.enable_favicons', false);

        Http::fake();

        // Documented as a no-op: it must exit 0 so it can live in deploy
        // scripts / schedulers without reporting a false failure.
        $this->artisan('filament-topbar-menu:refresh-favicons')->assertSuccessful();

        Http::assertNothingSent();
    }
}
