<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('filament-topbar-menu.table_name', 'filament_topbar_menu_items');

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table) use ($tableName) {
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
        Schema::dropIfExists(config('filament-topbar-menu.table_name', 'filament_topbar_menu_items'));
    }
};
