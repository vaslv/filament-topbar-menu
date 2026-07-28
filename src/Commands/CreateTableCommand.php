<?php

namespace Vaslv\FilamentTopbarMenu\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;
use Vaslv\FilamentTopbarMenu\Support\MenuTableSchema;
use Vaslv\FilamentTopbarMenu\Support\MenuTransfer;

/**
 * Creates the menu table on the configured connection without consulting the
 * migration repository. Needed when an existing install switches to a
 * dedicated connection (FILAMENT_TOPBAR_MENU_DB_CONNECTION): the package
 * migration is already recorded in the app's own migrations table, so plain
 * `migrate` skips it and the table never appears on the new connection.
 *
 * With --copy-from-default the switch also moves the data: when the dedicated
 * menu is still empty and the app's default connection has a menu table, its
 * items are exported and imported onto the dedicated connection. Both the
 * creation and the copy are idempotent — the command is safe to keep in
 * deploy scripts permanently.
 */
class CreateTableCommand extends Command
{
    protected $signature = 'filament-topbar-menu:create-table
        {--copy-from-default : Copy the menu from the default connection when the dedicated menu is still empty}';

    protected $description = 'Create the topbar menu items table on the configured connection, regardless of recorded migration state';

    public function handle(MenuTableSchema $schema): int
    {
        $dedicated = $schema->connection();

        $this->components->info(__(
            $schema->ensure()
                ? 'filament-topbar-menu::filament-topbar-menu.command.table_created'
                : 'filament-topbar-menu::filament-topbar-menu.command.table_exists',
            [
                'table' => $schema->table(),
                'connection' => $dedicated ?? config('database.default'),
            ],
        ));

        if (! $this->option('copy-from-default')) {
            return self::SUCCESS;
        }

        return $this->copyFromDefault($schema, $dedicated);
    }

    /**
     * Seed the dedicated menu from the app's default connection. Only an empty
     * dedicated menu is seeded: a populated one is the fleet's live shared
     * menu, and merging this app's local items into it silently would corrupt
     * it for every other app. The empty-check and the import are not one
     * atomic step — if two apps race their very first deploy, export/import
     * both sides and clean up by hand.
     */
    protected function copyFromDefault(MenuTableSchema $schema, ?string $dedicated): int
    {
        if ($dedicated === null) {
            // No dedicated connection: the menu already lives on the default
            // connection and there is nothing to copy it from.
            $this->components->warn(__('filament-topbar-menu::filament-topbar-menu.command.copy_skipped_no_dedicated'));

            return self::SUCCESS;
        }

        if (TopbarMenuItem::query()->count() > 0) {
            $this->components->warn(__('filament-topbar-menu::filament-topbar-menu.command.copy_skipped_existing', [
                'connection' => $dedicated,
            ]));

            return self::SUCCESS;
        }

        if (! $schema->existsOnDefaultConnection()) {
            $this->components->info(__('filament-topbar-menu::filament-topbar-menu.command.copy_skipped_no_source'));

            return self::SUCCESS;
        }

        try {
            $count = app(MenuTransfer::class)->copyFromDefaultConnection();
        } catch (InvalidArgumentException $e) {
            // A local row that predates the current validation rules (e.g. a
            // non-http URL) fails the import's trust boundary. Nothing was
            // written — the import validates up front and is transactional.
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(__('filament-topbar-menu::filament-topbar-menu.command.copied', [
            'count' => $count,
            'connection' => $dedicated,
        ]));

        return self::SUCCESS;
    }
}
