@php
    /** @var \Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem $item */
@endphp

@if ($item->favicon_url)
    <img
        src="{{ $item->favicon_url }}"
        alt=""
        loading="lazy"
        class="ftm-favicon"
        onerror="this.style.display = 'none'"
    />
@elseif ($item->icon)
    @php
        try {
            $ftmIconHtml = \Filament\Support\generate_icon_html(
                $item->icon,
                attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['ftm-icon']),
            );
        } catch (\Throwable) {
            // Unknown or misspelled icon name: render nothing instead of letting
            // BladeUI's SvgNotFound bubble up. The menu renders on every panel
            // page, so an unhandled exception here would 500 the entire panel.
            $ftmIconHtml = null;
        }
    @endphp

    @if ($ftmIconHtml)
        {!! $ftmIconHtml->toHtml() !!}
    @endif
@endif
