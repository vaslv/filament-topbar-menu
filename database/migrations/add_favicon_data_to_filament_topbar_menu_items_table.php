<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Vaslv\FilamentTopbarMenu\Support\MenuTableSchema;

return new class extends Migration
{
    /**
     * The duplicate-column recovery inside addFaviconData() has to be able to
     * query after a failed ALTER. PostgreSQL aborts the whole transaction on
     * that error, so its re-check would itself throw (SQLSTATE 25P02) and turn
     * the intended race-safe no-op into a confusing failure. Running outside a
     * transaction costs nothing here — a single ALTER is atomic on its own.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        // The column definition lives in MenuTableSchema next to the rest of
        // the table, so a menu created fresh by ensure() and one upgraded in
        // place here cannot drift apart. addFaviconData() is idempotent and
        // race-safe.
        //
        // Note the order: this package ships migrations with no timestamp
        // prefix and Laravel sorts them by name, so `add_…` actually runs
        // BEFORE `create_…`. On a fresh install this is therefore the no-op
        // (no table yet) and the create migration that follows ships the
        // column itself; on an existing install create is already recorded as
        // ran, and this is the one that does the work.
        (new MenuTableSchema)->addFaviconData();
    }

    public function down(): void
    {
        // Same reasoning as the create migration's down(): a dedicated
        // connection may be a menu shared by several apps, each recording this
        // migration in its own migrations table, so a routine rollback in one
        // of them must not drop a column the others still write to.
        if (config('filament-topbar-menu.connection') !== null) {
            return;
        }

        $table = config('filament-topbar-menu.table_name', 'filament_topbar_menu_items');
        $schema = Schema::connection($this->getConnection());

        if (! $schema->hasTable($table) || ! $schema->hasColumn($table, 'favicon_data')) {
            return;
        }

        $schema->table($table, function (Blueprint $table): void {
            $table->dropColumn('favicon_data');
        });
    }

    /**
     * The connection the menu table lives on — see the create migration.
     */
    public function getConnection(): ?string
    {
        return config('filament-topbar-menu.connection');
    }
};
