<?php

namespace Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;

class CreateTopbarMenuItem extends CreateRecord
{
    protected static string $resource = TopbarMenuItemResource::class;

    /**
     * The form edits a virtual `visibility_mode` string instead of the
     * `visibility` JSON column (see the resource form for why). Map it onto
     * the `visibility` array before creating the record.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $mode = $data['visibility_mode'] ?? null;

        $data['visibility'] = TopbarMenuItem::applyVisibilityMode(
            null,
            is_string($mode) ? $mode : null,
        );

        unset($data['visibility_mode']);

        return $data;
    }
}
