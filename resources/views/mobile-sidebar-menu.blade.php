@php
    /** @var \Illuminate\Database\Eloquent\Collection<int, \Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem> $items */
    /** @var ?\Illuminate\Contracts\Auth\Authenticatable $user */
@endphp

@if ($items->isNotEmpty())
    {{-- The in-sidebar variant of the mobile menu, for panels that would rather
         keep every navigation item in one list than give this menu a slide-in
         panel and a topbar button of its own — see `mobile-menu.blade.php` for
         the default, and `TopbarMenuPlugin::mobileSidebar()` for the switch. The
         menu is appended to the sidebar Filament renders on every panel that has
         navigation, including top-navigation ones, where it slides in over the
         page on mobile.

         The media query is ours because it has to be: `dist/theme.css` ships
         only the utilities Filament itself uses, and every `lg:fi-hidden` rule
         in it is scoped to a specific parent (`.fi-ta-cell`,
         `.fi-layout-sidebar-toggle-btn-ctn`, …), so no ready-made class hides
         an arbitrary element on desktop. Inline rather than a registered asset
         so an update never needs `php artisan filament:assets`. `1024px` is
         Filament's own sidebar breakpoint — the `lg` of its theme and the
         `breakpoint` constant of its sidebar Alpine store. --}}
    <style>
        @media (min-width: 1024px) {
            .fi-topbar-menu-mobile {
                display: none;
            }
        }
    </style>

    <ul class="fi-topbar-menu-mobile fi-sidebar-nav-groups">
        @include('filament-topbar-menu::sidebar-items', [
            'items' => $items,
            'user' => $user ?? null,
        ])
    </ul>
@endif
