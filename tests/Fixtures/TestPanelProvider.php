<?php

namespace Vaslv\FilamentTopbarMenu\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use Vaslv\FilamentTopbarMenu\TopbarMenuPlugin;

/**
 * A minimal real panel, so tests that need routed resource URLs (the View page
 * and the post-save redirects) resolve them the way an application does,
 * instead of asserting against a hand-built string.
 */
class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugin(TopbarMenuPlugin::make());
    }
}
