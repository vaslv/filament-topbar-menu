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
}
