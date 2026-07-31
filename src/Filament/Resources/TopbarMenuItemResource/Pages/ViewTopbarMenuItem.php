<?php

namespace Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource;

/**
 * The read-only page for a single menu item, rendered from the resource's
 * infolist. It is only routed when `filament-topbar-menu.enable_view_page`
 * is on — see TopbarMenuItemResource::getPages().
 */
class ViewTopbarMenuItem extends ViewRecord
{
    protected static string $resource = TopbarMenuItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
