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
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;
use Vaslv\FilamentTopbarMenu\Support\MenuTableSchema;
use Vaslv\FilamentTopbarMenu\Support\MenuTransfer;
use Vaslv\FilamentTopbarMenu\TopbarMenu;

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
            $this->flushCacheAction(),
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

    /**
     * Keep the drag-and-drop view in the order the rest of the page uses.
     *
     * Filament orders a reordering table by the reorder column alone, which is
     * the flat `sort` the table deliberately does not list by — so switching
     * reorder mode on would reshuffle every row under the admin's cursor, and
     * a drag would be aimed at a list that is about to rearrange itself again.
     *
     * A drag still writes plain sequential `sort` values across the whole page,
     * so moving an item between groups only changes its position among its own
     * siblings — reordering does not reparent, the Parent field does.
     *
     * @param  EloquentBuilder<TopbarMenuItem>  $query
     * @return EloquentBuilder<TopbarMenuItem>
     */
    protected function applySortingToTableQuery(EloquentBuilder $query): EloquentBuilder
    {
        if ($this->isTableReordering()) {
            return $query->treeOrdered();
        }

        return parent::applySortingToTableQuery($query);
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

    /**
     * Drop the cached menu snapshot.
     *
     * Every write through this package already flushes it — the model's saved
     * and deleted hooks, the import, the reorder callback. This action is for
     * the cases outside that reach: a row written by a sibling app on a shared
     * menu database, a seeder, hand-written SQL, or a cache store that was
     * restored from elsewhere. Nothing is lost by pressing it: the next render
     * rebuilds the snapshot from the database.
     *
     * Hidden while the menu table is missing, where the setup action is the
     * only thing on offer — flushing there would only trade a stale menu for an
     * empty one, and the table has to exist before either is worth anything.
     */
    protected function flushCacheAction(): Action
    {
        return Action::make('flushCache')
            ->label($this->trans('actions.flush_cache'))
            ->icon('heroicon-m-arrow-path')
            ->color('gray')
            // The snapshot is shared by every visitor of every panel, so ask
            // for the same gate export needs to read the menu at all.
            ->visible(fn (): bool => ! $this->menuTableIsMissing() && TopbarMenuItemResource::canViewAny())
            ->action(function (): void {
                app(TopbarMenu::class)->flushCache();

                Notification::make()
                    ->title($this->trans('notifications.cache_flushed'))
                    ->success()
                    ->send();
            });
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
                    //
                    // Sized against what this package's own export can produce,
                    // not a round number: an embedded favicon travels inside
                    // the file as base64, so a menu of embedded icons is orders
                    // of magnitude larger than a menu of links. A limit that
                    // rejects a file this very panel exported would be a
                    // one-way door dressed up as validation.
                    ->maxSize(16384)
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
