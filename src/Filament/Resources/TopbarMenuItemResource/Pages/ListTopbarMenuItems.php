<?php

namespace Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource;
use Vaslv\FilamentTopbarMenu\Support\MenuTransfer;

class ListTopbarMenuItems extends ListRecords
{
    protected static string $resource = TopbarMenuItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->exportAction(),
            $this->importAction(),
            CreateAction::make(),
        ];
    }

    protected function exportAction(): Action
    {
        return Action::make('export')
            ->label($this->trans('actions.export'))
            ->icon('heroicon-m-arrow-down-tray')
            ->color('gray')
            // Export dumps every item (including inactive ones and role
            // visibility rules), so require the resource's view gate rather
            // than relying on page access alone.
            ->visible(fn (): bool => TopbarMenuItemResource::canViewAny())
            ->action(function (): StreamedResponse {
                $payload = app(MenuTransfer::class)->export();

                return response()->streamDownload(
                    function () use ($payload): void {
                        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    },
                    'topbar-menu-'.now()->format('Y-m-d').'.json',
                    ['Content-Type' => 'application/json'],
                );
            });
    }

    protected function importAction(): Action
    {
        return Action::make('import')
            ->label($this->trans('actions.import'))
            ->icon('heroicon-m-arrow-up-tray')
            ->color('gray')
            // Import creates records — gate it behind the resource's create
            // policy so a view-only user cannot add menu items.
            ->visible(fn (): bool => TopbarMenuItemResource::canCreate())
            ->modalDescription($this->trans('import.description'))
            ->schema([
                FileUpload::make('file')
                    ->label($this->trans('import.file'))
                    ->acceptedFileTypes(['application/json', 'text/json', 'text/plain'])
                    // Bound the upload so a large file cannot exhaust memory
                    // when it is read and decoded in one shot below.
                    ->maxSize(2048)
                    ->required()
                    // The file is parsed right here in the action; nothing
                    // should be persisted to the panel's default disk.
                    ->storeFiles(false),

                Toggle::make('replace')
                    ->label($this->trans('import.replace'))
                    ->helperText($this->trans('import.replace_helper'))
                    ->default(false)
                    // Replace deletes the whole menu first, so only offer it to
                    // users the delete policy allows; others can still append.
                    ->visible(fn (): bool => TopbarMenuItemResource::canDeleteAny()),
            ])
            ->action(function (array $data): void {
                /** @var TemporaryUploadedFile $file */
                $file = $data['file'];

                $payload = json_decode($file->get(), true);

                // Belt-and-suspenders: the replace toggle is hidden without the
                // delete policy, but never trust the form state — re-check.
                $replace = ((bool) ($data['replace'] ?? false))
                    && TopbarMenuItemResource::canDeleteAny();

                try {
                    $count = app(MenuTransfer::class)->import(
                        is_array($payload) ? $payload : [],
                        $replace,
                    );
                } catch (InvalidArgumentException $exception) {
                    Notification::make()
                        ->title($this->trans('notifications.import_invalid'))
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                } catch (\Throwable) {
                    // The import runs in a transaction, so a failure here (a
                    // storage read error, an unexpected DB constraint) has
                    // already rolled back — surface it as a failed import
                    // rather than a 500 on the panel page.
                    Notification::make()
                        ->title($this->trans('notifications.import_invalid'))
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title($this->trans('notifications.import_success', ['count' => $count]))
                    ->success()
                    ->send();
            });
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    protected function trans(string $key, array $replace = []): string
    {
        return __("filament-topbar-menu::filament-topbar-menu.{$key}", $replace);
    }
}
