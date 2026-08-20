<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Vaslv\FilamentTopbarMenu\Support\MenuTableSchema;

return new class extends Migration
{
    /**
     * The duplicate-table recovery inside ensure() has to be able to query
     * after a failed CREATE. PostgreSQL aborts the whole transaction on that
     * error, so its re-check would itself throw (SQLSTATE 25P02) and turn the
     * intended race-safe no-op into a confusing deploy failure — exactly the
     * race the recovery exists for, since two apps sharing one menu database
     * hit it on their first deploy. The sibling add_favicon_data migration
     * carries this for the same reason; the create one was missed.
     *
     * Running outside a transaction costs nothing here: a single CREATE TABLE
     * is atomic on its own.
     */
    public $withinTransaction = false;

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
