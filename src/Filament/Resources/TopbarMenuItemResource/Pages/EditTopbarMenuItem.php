<?php

namespace Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource;

class EditTopbarMenuItem extends EditRecord
{
    protected static string $resource = TopbarMenuItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
