@php
    use Filament\Support\Icons\Heroicon;

    /** @var \Illuminate\Database\Eloquent\Collection<int, TopbarMenuItem> $items */
    /** @var ?\Illuminate\Contracts\Auth\Authenticatable $user */
@endphp

@if ($items->isNotEmpty())
    {{-- The desktop copy of the menu is invisible below `lg`: Filament hides the
         whole `.fi-topbar-start` container the TOPBAR_LOGO_AFTER hook sits in,
         the same way it hides its own `.fi-topbar-nav-groups`. So the menu is
         rendered a second time on mobile — as a second sidebar, identical to the
         panel's own one but opened by its own topbar button next to the ☰. Two
         buttons, two menus: the menu is never below the panel's navigation,
         where a long navigation would leave it to be scrolled to.

         The panel is built from Filament's own `fi-sidebar` classes rather than
         a layout of ours, so it inherits the real thing: the same width
         (`--sidebar-width`), the same slide-in transition, the same offset below
         the topbar (`.fi-body-has-topbar .fi-sidebar`), the same dark mode. --}}
    <style>
        @media (min-width: 1024px) {
            .fi-topbar-menu-mobile {
                display: none;
            }
        }

        /* The exact complement of the rule above — `max-width: 1023.98px` would
           leave the fractional widths between the two uncovered, where the
           button renders but is not placed. Tailwind v4 compiles its own
           `max-lg:` to this same form. */
        @media not all and (min-width: 1024px) {
            /* This hook renders at the very start of `.fi-topbar`, before
               Filament's own sidebar buttons. `.fi-topbar` is a flex row, so
               pulling those buttons one slot forward is what puts this menu's
               button to the right of the ☰ instead of to the left of it —
               without a hook between the two, which Filament does not offer. */
            .fi-topbar-open-sidebar-btn,
            .fi-topbar-close-sidebar-btn {
                order: -1;
            }

            /* Filament's own overlay sits at `z-30`, the same layer as the
               topbar, and is only below it because it comes first in the DOM.
               This one is teleported to the end of the body — out of the
               topbar's `overflow-x: clip` and stacking context — so it needs the
               lower layer spelled out, otherwise it would cover the very button
               that closes it. */
            .fi-topbar-menu-mobile-overlay {
                z-index: 20;
            }
        }
    </style>

    <div
        x-data="{
            isOpen: false,
            init() {
                // One menu open at a time: opening the panel's own sidebar
                // closes this one, the way tapping the ☰ closes any other
                // slide-in would.
                this.$watch('$store.sidebar.isOpen', (isSidebarOpen) => {
                    if (isSidebarOpen) {
                        this.isOpen = false
                    }
                })
            },
            open() {
                this.isOpen = true
                this.$store.sidebar.close()
            },
            close() {
                this.isOpen = false
            },
        }"
        x-on:keydown.escape.window="close()"
        class="fi-topbar-menu-mobile"
    >
        {{-- Two buttons rather than one that toggles, exactly as Filament does
             it for the sidebar: each keeps its own label, so a screen reader
             announces the action rather than a state. `icon-size="lg"` and the
             gray color are the ☰ button's own, so the pair reads as one control
             group; primary marks the menu as holding the page you are on. --}}
        <x-filament::icon-button
            :color="$items->contains(fn (\Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem $item): bool => $item->isBranchActive($user ?? null)) ? 'primary' : 'gray'"
            :icon="Heroicon::OutlinedSquares2x2"
            icon-size="lg"
            :label="__('filament-topbar-menu::filament-topbar-menu.mobile.open')"
            x-cloak
            aria-controls="fi-topbar-menu-sidebar"
            x-bind:aria-expanded="isOpen"
            x-on:click="open()"
            x-show="! isOpen"
            class="fi-topbar-menu-mobile-open-btn"
        />

        <x-filament::icon-button
            color="gray"
            :icon="Heroicon::OutlinedXMark"
            icon-size="lg"
            :label="__('filament-topbar-menu::filament-topbar-menu.mobile.close')"
            x-cloak
            aria-controls="fi-topbar-menu-sidebar"
            x-bind:aria-expanded="isOpen"
            x-on:click="close()"
            x-show="isOpen"
            class="fi-topbar-menu-mobile-close-btn"
        />

        {{-- Teleported to the body because a `position: fixed` panel rendered
             inside the topbar would live in the topbar's stacking context and
             be clipped by its `overflow-x: clip`. In the body it is also a child
             of the element carrying `fi-body-has-topbar`, which is what offsets
             a sidebar below the topbar instead of over it. --}}
        <template x-teleport="body">
            <div class="fi-topbar-menu-mobile">
                <div
                    x-cloak
                    x-on:click="close()"
                    x-show="isOpen"
                    x-transition.opacity.300ms
                    class="fi-sidebar-close-overlay fi-topbar-menu-mobile-overlay"
                ></div>

                <div
                    x-cloak
                    x-bind:class="{ 'fi-sidebar-open': isOpen }"
                    id="fi-topbar-menu-sidebar"
                    class="fi-sidebar fi-topbar-menu-sidebar"
                >
                    <nav
                        aria-label="{{ __('filament-topbar-menu::filament-topbar-menu.labels.navigation') }}"
                        class="fi-sidebar-nav"
                    >
                        {{-- A tap on a link closes this menu the way it closes
                             Filament's own sidebar: the click handler Filament
                             puts on each item only knows about the panel's own
                             sidebar store. Delegated from the list and narrowed
                             to links, so collapsing a group does not close the
                             menu it is in. --}}
                        <ul
                            x-on:click="$event.target.closest('a') && close()"
                            class="fi-sidebar-nav-groups"
                        >
                            @include('filament-topbar-menu::sidebar-items', [
                                'items' => $items,
                                'user' => $user ?? null,
                            ])
                        </ul>
                    </nav>
                </div>
            </div>
        </template>
    </div>
@endif
