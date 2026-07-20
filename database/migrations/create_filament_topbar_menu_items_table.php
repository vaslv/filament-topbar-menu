<?php

use Illuminate\Database\Migrations\Migration;
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
    }

    public function down(): void
    {
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
