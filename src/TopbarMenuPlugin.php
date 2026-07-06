<?php

namespace Vaslv\FilamentTopbarMenu;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use UnitEnum;
use Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource;

class TopbarMenuPlugin implements Plugin
{
    protected bool $shouldRegisterResource = true;

    protected string $renderHook = PanelsRenderHook::TOPBAR_LOGO_AFTER;

    protected string | UnitEnum | null $resourceNavigationGroup = null;

    protected ?int $resourceNavigationSort = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-topbar-menu';
    }

    public function register(Panel $panel): void
    {
        if ($this->shouldRegisterResource) {
            $panel->resources([
                TopbarMenuItemResource::class,
            ]);
        }

        $panel->renderHook(
            $this->renderHook,
            fn (): View => view('filament-topbar-menu::menu', [
                'items' => app(TopbarMenu::class)->visibleItems($user = filament()->auth()->user()),
                'user' => $user,
            ]),
        );
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * Toggle registration of the menu item management resource,
     * e.g. to hide it from panels that should only display the menu.
     */
    public function resource(bool $condition = true): static
    {
        $this->shouldRegisterResource = $condition;

        return $this;
    }

    /**
     * Render the menu at a different panel render hook.
     */
    public function renderHook(string $renderHook): static
    {
        $this->renderHook = $renderHook;

        return $this;
    }

    public function resourceNavigationGroup(string | UnitEnum | null $group): static
    {
        $this->resourceNavigationGroup = $group;

        return $this;
    }

    public function resourceNavigationSort(?int $sort): static
    {
        $this->resourceNavigationSort = $sort;

        return $this;
    }

    public function getResourceNavigationGroup(): string | UnitEnum | null
    {
        return $this->resourceNavigationGroup;
    }

    public function getResourceNavigationSort(): ?int
    {
        return $this->resourceNavigationSort;
    }
}
