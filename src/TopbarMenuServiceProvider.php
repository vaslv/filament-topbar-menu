<?php

namespace Vaslv\FilamentTopbarMenu;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;
use Vaslv\FilamentTopbarMenu\Commands\RefreshFaviconsCommand;
use Vaslv\FilamentTopbarMenu\Support\FaviconResolver;

class TopbarMenuServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filament-topbar-menu.php', 'filament-topbar-menu');

        $this->app->singleton(TopbarMenu::class);
        $this->app->singleton(FaviconResolver::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-topbar-menu');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filament-topbar-menu');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        FilamentAsset::register([
            Css::make('filament-topbar-menu', __DIR__.'/../resources/dist/filament-topbar-menu.css'),
        ], package: 'vaslv/filament-topbar-menu');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RefreshFaviconsCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/filament-topbar-menu.php' => config_path('filament-topbar-menu.php'),
            ], 'filament-topbar-menu-config');

            $this->publishes([
                __DIR__.'/../database/migrations/create_filament_topbar_menu_items_table.php' => database_path('migrations/create_filament_topbar_menu_items_table.php'),
            ], 'filament-topbar-menu-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/filament-topbar-menu'),
            ], 'filament-topbar-menu-views');

            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/filament-topbar-menu'),
            ], 'filament-topbar-menu-translations');
        }
    }
}
