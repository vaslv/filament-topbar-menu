<?php

namespace Vaslv\FilamentTopbarMenu\Support;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The single source of the menu items table schema, shared by the package
 * migration and the create-table command so both produce an identical table.
 * The command exists because switching an existing install to a dedicated
 * connection leaves the migration recorded in the app's own migrations table —
 * plain `migrate` skips it and the table never appears on the new connection.
 */
class MenuTableSchema
{
    /**
     * The connection the menu table lives on. Null means the application's
     * default connection (see the `connection` config option).
     */
    public function connection(): ?string
    {
        return config('filament-topbar-menu.connection');
    }

    public function table(): string
    {
        return config('filament-topbar-menu.table_name', 'filament_topbar_menu_items');
    }

    /**
     * Whether the menu table exists on the configured connection.
     */
    public function exists(): bool
    {
        return Schema::connection($this->connection())->hasTable($this->table());
    }

    /**
     * Whether a menu table (still) exists on the application's default
     * connection — the copy source when an install switches to a dedicated
     * connection.
     */
    public function existsOnDefaultConnection(): bool
    {
        return Schema::connection(null)->hasTable($this->table());
    }

    /**
     * Create the menu table on the configured connection unless it already
     * exists. Returns true when this call created it.
     */
    public function ensure(): bool
    {
        $tableName = $this->table();
        $schema = Schema::connection($this->connection());

        if ($schema->hasTable($tableName)) {
            return false;
        }

        try {
            $schema->create($tableName, function (Blueprint $table) use ($tableName) {
                $table->id();
                $table->foreignId('parent_id')
                    ->nullable()
                    ->constrained($tableName)
                    ->cascadeOnDelete();
                $table->string('label');
                $table->string('type')->default('url');
                $table->string('url')->nullable();
                $table->string('route')->nullable();
                $table->json('route_parameters')->nullable();
                $table->string('target')->default('_self');
                $table->string('icon')->nullable();
                $table->string('favicon_url')->nullable();
                // The icon's own bytes as a `data:` URI, for menus whose links
                // the visitor's browser cannot fetch — see addFaviconData().
                // mediumText, not text: base64 inflates by a third, and MySQL's
                // TEXT tops out at 64 KB, right where the byte cap sits.
                $table->mediumText('favicon_data')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort')->default(0);
                $table->json('visibility')->nullable();
                $table->timestamps();

                $table->index(['parent_id', 'is_active', 'sort']);
            });
        } catch (QueryException $e) {
            // Two apps sharing one menu database can race between hasTable()
            // and create() on their first deploy. The loser's "table already
            // exists" error means the table is there — the desired end state —
            // so only rethrow when the table genuinely failed to appear. The
            // re-check uses a fresh builder: the earlier hasTable() answer is
            // stale by definition here.
            if (Schema::connection($this->connection())->hasTable($tableName)) {
                return false;
            }

            throw $e;
        }

        return true;
    }

    /**
     * Add the `favicon_data` column to an existing menu table. Returns true when
     * this call added it.
     *
     * Separate from ensure() because that one only ever creates a whole table:
     * on an install that already has the menu, `hasTable()` is true and nothing
     * inside the closure runs. Both the column's own migration and the
     * create-table command call this, so a table created fresh and one upgraded
     * in place end up with the same columns and types — the same contract
     * ensure() has with that command. (Only the ordinal position can differ:
     * `after()` is a MySQL-only modifier, so on PostgreSQL and SQLite an
     * upgraded table carries the column last. Nothing here reads by position.)
     *
     * Idempotent by the same reasoning ensure() is: several apps may share one
     * menu database and each records the migration separately.
     */
    public function addFaviconData(): bool
    {
        $tableName = $this->table();
        $schema = Schema::connection($this->connection());

        if (! $schema->hasTable($tableName) || $schema->hasColumn($tableName, 'favicon_data')) {
            return false;
        }

        try {
            $schema->table($tableName, function (Blueprint $table): void {
                $table->mediumText('favicon_data')->nullable()->after('favicon_url');
            });
        } catch (QueryException $e) {
            // Lost a race with a sibling app against the same shared database.
            // The column being there is the desired end state, so only a column
            // that genuinely failed to appear is an error worth rethrowing.
            if (Schema::connection($this->connection())->hasColumn($tableName, 'favicon_data')) {
                return false;
            }

            throw $e;
        }

        return true;
    }
}
