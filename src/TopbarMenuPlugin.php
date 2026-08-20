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

    protected bool $shouldRenderOnMobile = true;

    protected bool $shouldRenderMobileInSidebar = false;

    protected ?string $mobileRenderHook = null;

    protected string|UnitEnum|null $resourceNavigationGroup = null;

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
            fn (): View => $this->renderMenu('filament-topbar-menu::menu'),
        );

        if ($this->shouldRenderOnMobile) {
            // A second copy of the same menu, for the viewports where the first
            // one cannot be seen: Filament hides the whole `.fi-topbar-start`
            // container below `lg`, so the desktop copy disappears on mobile the
            // same way Filament's own top navigation does. By default it comes
            // back as a second sidebar — the same slide-in panel as the one the
            // ☰ opens, built from the same `fi-sidebar` classes, behind a button
            // of its own next to the ☰. Two buttons, two menus, so this menu is
            // never below the panel's own navigation and never has to be
            // scrolled past it. mobileSidebar() appends it to the panel's own
            // sidebar instead, as one list with its navigation.
            $panel->renderHook(
                $this->getMobileRenderHook(),
                fn (): View => $this->renderMenu($this->getMobileView()),
            );
        }
    }

    /**
     * Render one copy of the menu tree, filtered for the current user.
     */
    protected function renderMenu(string $view): View
    {
        $user = filament()->auth()->user();

        return view($view, [
            'items' => app(TopbarMenu::class)->visibleItems($user),
            'user' => $user,
        ]);
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

    /**
     * Toggle the mobile copy of the menu, rendered as a slide-in panel behind
     * its own topbar button because the desktop copy is hidden below `lg`. Turn
     * it off for a panel that renders the menu somewhere visible on mobile
     * already — e.g. one that moved the menu to its own render hook with
     * renderHook().
     */
    public function mobile(bool $condition = true): static
    {
        $this->shouldRenderOnMobile = $condition;

        return $this;
    }

    /**
     * Render the mobile copy inside the panel's own sidebar, below its
     * navigation, instead of as a slide-in panel of its own. Worth it on a panel
     * with a short navigation, where one list reads better than two menus; on a
     * long one the menu ends up below every navigation item and has to be
     * scrolled to.
     */
    public function mobileSidebar(bool $condition = true): static
    {
        $this->shouldRenderMobileInSidebar = $condition;

        return $this;
    }

    /**
     * Render the mobile copy of the menu at a different panel render hook. An
     * explicit hook wins over the one the mobile variant would pick.
     */
    public function mobileRenderHook(string $renderHook): static
    {
        $this->mobileRenderHook = $renderHook;

        return $this;
    }

    /**
     * The hook the mobile copy renders at: the start of the topbar for the
     * slide-in panel — the only place Filament offers next to its own sidebar
     * buttons, which the view then reorders past with one CSS rule, since there
     * is no hook between the ☰ and the logo — and the end of the sidebar
     * navigation for the in-sidebar variant.
     */
    protected function getMobileRenderHook(): string
    {
        return $this->mobileRenderHook ?? ($this->shouldRenderMobileInSidebar
            ? PanelsRenderHook::SIDEBAR_NAV_END
            : PanelsRenderHook::TOPBAR_START);
    }

    protected function getMobileView(): string
    {
        return $this->shouldRenderMobileInSidebar
            ? 'filament-topbar-menu::mobile-sidebar-menu'
            : 'filament-topbar-menu::mobile-menu';
    }

    public function resourceNavigationGroup(string|UnitEnum|null $group): static
    {
        $this->resourceNavigationGroup = $group;

        return $this;
    }

    public function resourceNavigationSort(?int $sort): static
    {
        $this->resourceNavigationSort = $sort;

        return $this;
    }

    public function getResourceNavigationGroup(): string|UnitEnum|null
    {
        return $this->resourceNavigationGroup;
    }

    public function getResourceNavigationSort(): ?int
    {
        return $this->resourceNavigationSort;
    }
}
