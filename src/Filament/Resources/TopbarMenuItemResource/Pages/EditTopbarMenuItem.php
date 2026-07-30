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
        $visibility = is_array($visibility) ? $visibility : null;

        $data['visibility_mode'] = TopbarMenuItem::visibilityModeFromArray($visibility);

        // Always fill from the stored key — route items included (the field is
        // merely hidden for them). Switching the type to URL in the live form
        // then reveals the real stored value, never an invented `false`, so
        // what the admin sees is what a save applies.
        $data['visibility_hide_on_current_domain'] = (bool) ($visibility['hide_on_current_domain'] ?? false);

        return $data;
    }

    /**
     * Map the virtual fields back onto the `visibility` array, preserving keys
     * the form does not manage (notably `roles`). The helpers are chained —
     * the second builds on the first one's result; independent calls over the
     * original array would clobber each other.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var TopbarMenuItem $record */
        $record = $this->getRecord();

        $mode = $data['visibility_mode'] ?? null;

        // The toggle is only offered for URL items. When the submitted type is
        // route, the hidden toggle must neither erase nor rewrite the stored
        // key — keep whatever the record already has.
        $enabled = ($data['type'] ?? null) === TopbarMenuItem::TYPE_URL
            ? (bool) ($data['visibility_hide_on_current_domain'] ?? false)
            : (bool) ($record->visibility['hide_on_current_domain'] ?? false);

        $data['visibility'] = TopbarMenuItem::applyHideOnCurrentDomain(
            TopbarMenuItem::applyVisibilityMode($record->visibility, is_string($mode) ? $mode : null),
            $enabled,
        );

        unset($data['visibility_mode'], $data['visibility_hide_on_current_domain']);

        return $data;
    }
}
