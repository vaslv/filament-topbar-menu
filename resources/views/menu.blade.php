@php
    use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;

    /** @var \Illuminate\Database\Eloquent\Collection<int, TopbarMenuItem> $items */
    /** @var ?\Illuminate\Contracts\Auth\Authenticatable $user */
@endphp

@if ($items->isNotEmpty())
    {{-- The desktop copy of the menu. Filament hides `.fi-topbar-nav-groups`
         below `lg`, and hides the `.fi-topbar-start` container this render hook
         sits in on top of that, so the mobile copy is rendered separately — as a
         slide-in panel behind its own topbar button (`mobile-menu.blade.php`),
         or inside the panel's own sidebar (`mobile-sidebar-menu.blade.php`).
         Editing one copy without the other leaves the menu different on phones
         than on desktops; the rows of both mobile variants live in
         `sidebar-items.blade.php`. --}}
    <ul class="fi-topbar-nav-groups">
        @foreach ($items as $item)
            @php
                $children = $item->visibleChildren($user ?? null);
                $url = $item->resolveUrl();
                $itemNewTab = $item->resolveTarget() === TopbarMenuItem::TARGET_BLANK;
                // The favicon takes precedence over the (validated) icon; an
                // unknown icon name resolves to null instead of a 500 — see
                // TopbarMenuItem::displayIcon().
                $itemIcon = $item->displayIcon();
            @endphp

            @if ($children->isEmpty())
                @if ($url)
                    <x-filament-panels::topbar.item
                        :url="$url"
                        :icon="$itemIcon"
                        :active="$item->isActive()"
                        :should-open-url-in-new-tab="$itemNewTab"
                    >
                        {{ $item->label }}
                    </x-filament-panels::topbar.item>
                @endif
            @else
                <x-filament::dropdown placement="bottom-start" teleport>
                    <x-slot name="trigger">
                        <x-filament-panels::topbar.item
                            :icon="$itemIcon"
                            :active="$item->isBranchActive($user ?? null)"
                        >
                            {{ $item->label }}
                        </x-filament-panels::topbar.item>
                    </x-slot>

                    <x-filament::dropdown.list>
                        @foreach ($children as $child)
                            @php
                                $childUrl = $child->resolveUrl();
                            @endphp

                            @if ($childUrl)
                                {{-- The marker goes in the icon slot, not in `image`: Filament
                                     renders a slash-containing string there as an <img>, while
                                     `image` is an avatar-style circle that crops a square favicon.
                                     Same marker the top-level items use — see displayIcon(). --}}
                                <x-filament::dropdown.list.item
                                    tag="a"
                                    :href="$childUrl"
                                    :icon="$child->displayIcon()"
                                    :target="$child->resolveTarget() === TopbarMenuItem::TARGET_BLANK ? '_blank' : null"
                                    :color="$child->isActive() ? 'primary' : 'gray'"
                                >
                                    {{ $child->label }}
                                </x-filament::dropdown.list.item>
                            @endif
                        @endforeach
                    </x-filament::dropdown.list>
                </x-filament::dropdown>
            @endif
        @endforeach
    </ul>
@endif
