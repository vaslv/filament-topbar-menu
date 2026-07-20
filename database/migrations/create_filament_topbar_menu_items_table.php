<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('filament-topbar-menu.table_name', 'filament_topbar_menu_items');

        $schema = Schema::connection($this->getConnection());

        if ($schema->hasTable($tableName)) {
            return;
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
            // so only rethrow when the table genuinely failed to appear.
            if (! $schema->hasTable($tableName)) {
                throw $e;
            }
        }
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
