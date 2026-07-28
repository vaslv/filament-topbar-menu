<?php

namespace Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmptyState;
use Filament\Schemas\Schema;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;
use Vaslv\FilamentTopbarMenu\Support\MenuTableSchema;
use Vaslv\FilamentTopbarMenu\Support\MenuTransfer;

class ListTopbarMenuItems extends ListRecords
{
    protected static string $resource = TopbarMenuItemResource::class;

    /**
     * Per-request memo of the missing-table check; several visibility
     * closures and content() ask for it during a single render.
     */
    protected ?bool $menuTableIsMissing = null;

    protected function getHeaderActions(): array
    {
        return [
            $this->setupTableAction(),
            $this->exportAction(),
            $this->importAction(),
            CreateAction::make()
                ->visible(fn (): bool => ! $this->menuTableIsMissing()),
        ];
    }

    /**
     * The list page must survive a missing menu table — the window between
     * switching FILAMENT_TOPBAR_MENU_DB_CONNECTION and the create-table
     * deploy step. Rendering the records table would query the missing table
     * and 500 the page, so the table is replaced with an explanation while
     * the header keeps the setup action that fixes the situation in place.
     */
    public function content(Schema $schema): Schema
    {
        if ($this->menuTableIsMissing()) {
            return $schema->components([
                EmptyState::make($this->trans('missing_table.title'))
                    ->icon('heroicon-o-circle-stack')
                    ->description($this->trans('missing_table.description')),
            ]);
        }

        return parent::content($schema);
    }

    protected function setupTableAction(): Action
    {
        return Action::make('setupTable')
            ->label($this->trans('actions.create_table'))
            ->icon('heroicon-m-circle-stack')
            ->color('warning')
            // The action repairs infrastructure and can pull the whole local
            // menu into the shared database — gate it like record creation.
            ->visible(fn (): bool => $this->menuTableIsMissing() && TopbarMenuItemResource::canCreate())
            ->action(function (): void {
                try {
                    $copied = $this->setUpMenuTable();
                } catch (InvalidArgumentException $exception) {
                    // The table was created, but a legacy local row failed the
                    // copy's validation — nothing was copied (the import is
                    // transactional). Surface the row so it can be fixed and
                    // the menu re-imported through the Export/Import actions.
                    Notification::make()
                        ->title($this->trans('notifications.table_created'))
                        ->body($exception->getMessage())
                        ->warning()
                        ->send();

                    $this->redirect(TopbarMenuItemResource::getUrl());

                    return;
                }

                Notification::make()
                    ->title($this->trans('notifications.table_created'))
                    ->body($copied === null ? null : $this->trans('notifications.table_copied', ['count' => $copied]))
                    ->success()
                    ->send();

                // A full redirect, not a re-render: the whole page (header
                // actions, records table) must rebuild against the now
                // existing table.
                $this->redirect(TopbarMenuItemResource::getUrl());
            });
    }

    /**
     * Create the menu table on the configured connection and, in the switch
     * scenario (dedicated connection, empty dedicated menu, local menu still
     * on the default connection), copy the local menu over — the exact
     * behavior of `filament-topbar-menu:create-table --copy-from-default`.
     *
     * @return int|null The number of copied items, or null when nothing needed copying.
     */
    protected function setUpMenuTable(): ?int
    {
        $schema = app(MenuTableSchema::class);

        $schema->ensure();

        $shouldCopy = $schema->connection() !== null
            && TopbarMenuItem::query()->count() === 0
            && $schema->existsOnDefaultConnection();

        return $shouldCopy ? app(MenuTransfer::class)->copyFromDefaultConnection() : null;
    }

    protected function menuTableIsMissing(): bool
    {
        return $this->menuTableIsMissing ??= ! app(MenuTableSchema::class)->exists();
    }

    protected function exportAction(): Action
    {
        return Action::make('export')
            ->label($this->trans('actions.export'))
            ->icon('heroicon-m-arrow-down-tray')
            ->color('gray')
            // Export dumps every item (including inactive ones and role
            // visibility rules), so require the resource's view gate rather
            // than relying on page access alone. Hidden while the menu table
            // is missing — there is nothing to export and the query would 500.
            ->visible(fn (): bool => ! $this->menuTableIsMissing() && TopbarMenuItemResource::canViewAny())
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
            // policy so a view-only user cannot add menu items. Hidden while
            // the menu table is missing: create it first via the setup action.
            ->visible(fn (): bool => ! $this->menuTableIsMissing() && TopbarMenuItemResource::canCreate())
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
