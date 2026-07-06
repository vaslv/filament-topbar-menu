<?php

namespace Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource;

class ListTopbarMenuItems extends ListRecords
{
    protected static string $resource = TopbarMenuItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
