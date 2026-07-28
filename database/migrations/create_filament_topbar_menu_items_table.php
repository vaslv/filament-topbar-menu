<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Vaslv\FilamentTopbarMenu\Support\MenuTableSchema;

return new class extends Migration
{
    public function up(): void
    {
        // The schema lives in MenuTableSchema, shared with the create-table
        // command: on installs switched to a dedicated connection later in
        // life this migration is already recorded as ran, so the command is
        // the supported way to create the table there. Both stay identical
        // by construction. ensure() is idempotent and race-safe.
        (new MenuTableSchema)->ensure();
    }

    public function down(): void
    {
        // A dedicated connection is a shared resource: several apps may point
        // at the same menu database, and each records this migration in its
        // OWN migrations table. A routine `migrate:rollback` in any one of
        // them would otherwise drop the centrally managed menu for the whole
        // fleet, so the drop only runs when the menu lives on the app's own
        // default connection. Dropping a shared menu table is a manual call.
        if (config('filament-topbar-menu.connection') !== null) {
            return;
        }

        Schema::connection($this->getConnection())
            ->dropIfExists(config('filament-topbar-menu.table_name', 'filament_topbar_menu_items'));
    }

    /**
     * The connection the menu table lives on. Null keeps the migration on the
     * application's default connection; a value routes it to the dedicated
     * connection configured for the package (see the `connection` config).
     * Laravel's migrator also reads this to run the whole migration there.
     */
    public function getConnection(): ?string
    {
        return config('filament-topbar-menu.connection');
    }
};
