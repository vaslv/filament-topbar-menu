@php
    use Filament\Support\Enums\IconSize;
    use Filament\Support\Icons\Heroicon;
    use Filament\View\PanelsIconAlias;
    use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;

    /** @var \Illuminate\Database\Eloquent\Collection<int, TopbarMenuItem> $items */
    /** @var ?\Illuminate\Contracts\Auth\Authenticatable $user */

    // The rows of the mobile menu, in Filament's own sidebar markup. Shared by
    // both mobile variants — the slide-in panel of `mobile-menu.blade.php` and
    // the in-sidebar list of `mobile-sidebar-menu.blade.php` — which differ only
    // in what they wrap this list in.
@endphp

@foreach ($items as $item)
    @php
        $children = $item->visibleChildren($user ?? null);
        $url = $item->resolveUrl();
        $itemNewTab = $item->resolveTarget() === TopbarMenuItem::TARGET_BLANK;
        // The favicon takes precedence over the (validated) icon; an unknown
        // icon name resolves to null instead of a 500 — see displayIcon().
        $itemIcon = $item->displayIcon();
    @endphp

    @if ($children->isEmpty())
        @if ($url)
            {{-- `sidebar-collapsible` is false on every item: the collapsed
                 (icon-only + tooltip) sidebar is a desktop state, and this
                 list only ever renders below `lg`. --}}
            <x-filament-panels::sidebar.item
                :url="$url"
                :icon="$itemIcon"
                :active="$item->isActive()"
                :should-open-url-in-new-tab="$itemNewTab"
                :sidebar-collapsible="false"
            >
                {{ $item->label }}
            </x-filament-panels::sidebar.item>
        @endif
    @else
        @php
            $childItems = $children
                ->filter(fn (TopbarMenuItem $child): bool => filled($child->resolveUrl()))
                ->values();

            // Prefixed the way Filament prefixes its sub-navigation groups
            // (`sub_navigation_…`): the collapsed state lives in one shared
            // `collapsedGroups` store keyed by label, so an unprefixed key would
            // collapse a panel navigation group of the same name along with this
            // one.
            $groupKey = 'topbar_menu_'.$item->label;
        @endphp

        {{-- A group is a pure toggle here, exactly as it is in the desktop
             copy: its own URL is never rendered as a row. --}}
        <li
            x-data="{
                label: @js($groupKey),
                init() {
                    // Filament seeds `collapsedGroups` from a script inside the
                    // sidebar. This list can render outside it — in the slide-in
                    // panel, or at a hook of the panel's choosing — and the
                    // store's `$persist(null)` default would make
                    // groupIsCollapsed() throw wherever that script did not run.
                    this.$store.sidebar.collapsedGroups ??= []
                },
            }"
            data-group-label="{{ $groupKey }}"
            x-bind:class="{ 'fi-collapsed': $store.sidebar.groupIsCollapsed(label) }"
            @class([
                'fi-sidebar-group',
                'fi-collapsible',
                'fi-active' => $item->isBranchActive($user ?? null),
            ])
        >
            <div
                x-on:click="$store.sidebar.toggleCollapsedGroup(label)"
                class="fi-sidebar-group-btn"
            >
                @if (filled($itemIcon))
                    {{ \Filament\Support\generate_icon_html($itemIcon, size: IconSize::Large) }}
                @endif

                <span class="fi-sidebar-group-label">
                    {{ $item->label }}
                </span>

                <x-filament::icon-button
                    color="gray"
                    :icon="Heroicon::ChevronUp"
                    :icon-alias="PanelsIconAlias::SIDEBAR_GROUP_COLLAPSE_BUTTON"
                    :label="$item->label"
                    x-bind:aria-expanded="! $store.sidebar.groupIsCollapsed(label)"
                    x-on:click.stop="$store.sidebar.toggleCollapsedGroup(label)"
                    class="fi-sidebar-group-collapse-btn"
                />
            </div>

            <ul
                x-show="! $store.sidebar.groupIsCollapsed(label)"
                x-collapse.duration.200ms
                class="fi-sidebar-group-items"
            >
                @foreach ($childItems as $child)
                    {{-- Neither `grouped` nor `sub-grouped`: both make Filament
                         draw `fi-sidebar-item-grouped-border` — a dot joined by
                         connecting lines — for a child that has no icon, which
                         turns a menu where only some items carry a favicon into
                         a mix of icons and tree markers. The desktop dropdown
                         leaves such a child's icon slot empty, and so does this
                         list. A child that does have an icon keeps it either way;
                         `sub-grouped` would additionally throw it away, which is
                         the opposite of what this package exists for. --}}
                    <x-filament-panels::sidebar.item
                        :url="$child->resolveUrl()"
                        :icon="$child->displayIcon()"
                        :active="$child->isActive()"
                        :should-open-url-in-new-tab="$child->resolveTarget() === TopbarMenuItem::TARGET_BLANK"
                        :sidebar-collapsible="false"
                    >
                        {{ $child->label }}
                    </x-filament-panels::sidebar.item>
                @endforeach
            </ul>
        </li>
    @endif
@endforeach
