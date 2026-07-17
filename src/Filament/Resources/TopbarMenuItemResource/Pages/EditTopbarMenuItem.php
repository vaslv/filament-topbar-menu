<?php

namespace Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;

class EditTopbarMenuItem extends EditRecord
{
    protected static string $resource = TopbarMenuItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * The form edits a virtual `visibility_mode` string instead of the
     * `visibility` JSON column (see the resource form for why). Derive the
     * mode from the stored array when filling the form.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $visibility = $data['visibility'] ?? null;

        $data['visibility_mode'] = TopbarMenuItem::visibilityModeFromArray(
            is_array($visibility) ? $visibility : null,
        );

        return $data;
    }

    /**
     * Map the virtual `visibility_mode` back onto the `visibility` array,
     * preserving keys the form does not manage (notably `roles`).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var TopbarMenuItem $record */
        $record = $this->getRecord();

        $mode = $data['visibility_mode'] ?? null;

        $data['visibility'] = TopbarMenuItem::applyVisibilityMode(
            $record->visibility,
            is_string($mode) ? $mode : null,
        );

        unset($data['visibility_mode']);

        return $data;
    }
}
