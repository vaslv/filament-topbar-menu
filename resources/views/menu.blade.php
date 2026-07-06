@php
    /** @var \Illuminate\Database\Eloquent\Collection<int, \Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem> $items */
    /** @var ?\Illuminate\Contracts\Auth\Authenticatable $user */
@endphp

@if ($items->isNotEmpty())
    <nav class="ftm-nav" aria-label="Topbar menu">
        @foreach ($items as $item)
            @php
                $url = $item->resolveUrl();
                $target = $item->resolveTarget();
                $children = $item->visibleChildren($user ?? null);
            @endphp

            @if ($children->isEmpty())
                @if ($url)
                    <a
                        href="{{ $url }}"
                        target="{{ $target }}"
                        @if ($target === '_blank') rel="noopener noreferrer" @endif
                        class="ftm-link"
                    >
                        @include('filament-topbar-menu::partials.item-icon', ['item' => $item])
                        <span class="ftm-label">{{ $item->label }}</span>
                    </a>
                @endif
            @else
                <div
                    class="ftm-item"
                    x-data="{ open: false }"
                    x-on:mouseenter="if (window.matchMedia('(hover: hover)').matches) open = true"
                    x-on:mouseleave="if (window.matchMedia('(hover: hover)').matches) open = false"
                    x-on:keydown.escape.window="open = false"
                >
                    <div class="ftm-trigger">
                        @if ($url)
                            <a
                                href="{{ $url }}"
                                target="{{ $target }}"
                                @if ($target === '_blank') rel="noopener noreferrer" @endif
                                class="ftm-link"
                            >
                                @include('filament-topbar-menu::partials.item-icon', ['item' => $item])
                                <span class="ftm-label">{{ $item->label }}</span>
                            </a>

                            <button
                                type="button"
                                class="ftm-link ftm-caret"
                                x-on:click="open = ! open"
                                aria-haspopup="true"
                                x-bind:aria-expanded="open"
                            >
                                <span class="fi-sr-only">{{ __('Toggle :label submenu', ['label' => $item->label]) }}</span>
                                @include('filament-topbar-menu::partials.chevron')
                            </button>
                        @else
                            <button
                                type="button"
                                class="ftm-link"
                                x-on:click="open = ! open"
                                aria-haspopup="true"
                                x-bind:aria-expanded="open"
                            >
                                @include('filament-topbar-menu::partials.item-icon', ['item' => $item])
                                <span class="ftm-label">{{ $item->label }}</span>
                                @include('filament-topbar-menu::partials.chevron')
                            </button>
                        @endif
                    </div>

                    <div
                        class="ftm-dropdown"
                        x-cloak
                        x-show="open"
                        x-transition:enter="ftm-transition-enter"
                        x-on:click.outside="open = false"
                    >
                        @foreach ($children as $child)
                            @php
                                $childUrl = $child->resolveUrl();
                                $childTarget = $child->resolveTarget();
                            @endphp

                            @if ($childUrl)
                                <a
                                    href="{{ $childUrl }}"
                                    target="{{ $childTarget }}"
                                    @if ($childTarget === '_blank') rel="noopener noreferrer" @endif
                                    class="ftm-dropdown-link"
                                >
                                    @include('filament-topbar-menu::partials.item-icon', ['item' => $child])
                                    <span class="ftm-label">{{ $child->label }}</span>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </nav>
@endif
