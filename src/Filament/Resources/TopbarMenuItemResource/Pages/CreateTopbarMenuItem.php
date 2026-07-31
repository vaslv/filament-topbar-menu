<?php

namespace Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;

/**
 * Where a save lands is not decided here: CreateRecord already redirects to
 * the `view` page when the resource has one, so enabling
 * `filament-topbar-menu.enable_view_page` is enough — and a panel that set its
 * own create-page redirect keeps it. EditTopbarMenuItem has to say it out loud
 * because Filament's default there is to stay on the form.
 */
class CreateTopbarMenuItem extends CreateRecord
{
    protected static string $resource = TopbarMenuItemResource::class;

    /**
     * The form edits virtual fields (`visibility_mode`,
     * `visibility_hide_on_current_domain`) instead of the `visibility` JSON
     * column (see the resource form for why). Map them onto the `visibility`
     * array before creating the record — chained, so the second helper builds
     * on the first one's result instead of clobbering it. The toggle only
     * counts for URL items; it is not offered for route items.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $mode = $data['visibility_mode'] ?? null;

        $data['visibility'] = TopbarMenuItem::applyHideOnCurrentDomain(
            TopbarMenuItem::applyVisibilityMode(null, is_string($mode) ? $mode : null),
            ($data['type'] ?? null) === TopbarMenuItem::TYPE_URL
                && (bool) ($data['visibility_hide_on_current_domain'] ?? false),
        );

        unset($data['visibility_mode'], $data['visibility_hide_on_current_domain']);

        return $data;
    }
}
