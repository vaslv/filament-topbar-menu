<?php

namespace Vaslv\FilamentTopbarMenu;

use Illuminate\Support\ServiceProvider;
use Vaslv\FilamentTopbarMenu\Commands\CreateTableCommand;
use Vaslv\FilamentTopbarMenu\Commands\RefreshFaviconsCommand;
use Vaslv\FilamentTopbarMenu\Support\FaviconResolver;

class TopbarMenuServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filament-topbar-menu.php', 'filament-topbar-menu');

        // scoped(), not singleton(): TopbarMenu memoizes the resolved menu for
        // the request, because the plugin renders the menu twice on every panel
        // page (desktop copy plus mobile copy). A scoped binding is one instance
        // per request and per queued job, and Octane flushes it between
        // requests — a singleton would carry one request's menu into the next.
        $this->app->scoped(TopbarMenu::class);

        // Stateless, so a singleton is right here.
        $this->app->singleton(FaviconResolver::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-topbar-menu');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filament-topbar-menu');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateTableCommand::class,
                RefreshFaviconsCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/filament-topbar-menu.php' => config_path('filament-topbar-menu.php'),
            ], 'filament-topbar-menu-config');

            $this->publishes([
                __DIR__.'/../database/migrations/create_filament_topbar_menu_items_table.php' => database_path('migrations/create_filament_topbar_menu_items_table.php'),
                __DIR__.'/../database/migrations/add_favicon_data_to_filament_topbar_menu_items_table.php' => database_path('migrations/add_favicon_data_to_filament_topbar_menu_items_table.php'),
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
