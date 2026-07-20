@php
    use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;

    /** @var \Illuminate\Database\Eloquent\Collection<int, TopbarMenuItem> $items */
    /** @var ?\Illuminate\Contracts\Auth\Authenticatable $user */

    // Validate a free-text icon name before handing it to Filament: an unknown
    // name would otherwise throw SvgNotFound and 500 every panel page. Favicons
    // are passed separately as images and never go through this.
    $ftmSafeIcon = fn (?string $icon): ?string => TopbarMenuItem::safeIconName($icon);
@endphp

@if ($items->isNotEmpty())
    <ul class="fi-topbar-nav-groups">
        @foreach ($items as $item)
            @php
                $children = $item->visibleChildren($user ?? null);
                $url = $item->resolveUrl();
                $itemNewTab = $item->resolveTarget() === TopbarMenuItem::TARGET_BLANK;
                $itemIcon = $item->favicon_url ?: $ftmSafeIcon($item->icon);
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
                                <x-filament::dropdown.list.item
                                    tag="a"
                                    :href="$childUrl"
                                    :image="$child->favicon_url"
                                    :icon="$child->favicon_url ? null : $ftmSafeIcon($child->icon)"
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
