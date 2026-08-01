<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;
use ReflectionProperty;
use Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource;

/**
 * Where resolving a favicon is offered from the panel. Two places, each for a
 * different intent: the field the admin is editing, and a selection in the
 * table. The items table deliberately has no per-row action — see the note in
 * the resource's recordActions().
 */
class FaviconActionsTest extends TestCase
{
    public function test_the_items_table_offers_no_per_row_favicon_action(): void
    {
        $names = array_map(
            fn ($action): string => $action->getName(),
            $this->table()->getRecordActions(),
        );

        // The row still carries its ordinary actions — otherwise "no favicon
        // action here" would pass on an empty list and prove nothing.
        $this->assertContains('edit', $names);

        $this->assertSame(
            [],
            array_filter($names, fn (string $name): bool => str_contains(strtolower($name), 'favicon')),
        );
    }

    public function test_a_selection_can_still_be_resolved_in_bulk(): void
    {
        $names = [];

        foreach ($this->table()->getToolbarActions() as $action) {
            foreach ($action instanceof BulkActionGroup ? $action->getActions() : [$action] as $child) {
                $names[] = $child->getName();
            }
        }

        $this->assertContains('refreshFavicons', $names);
    }

    public function test_the_favicon_url_field_keeps_its_fetch_button(): void
    {
        $field = null;

        foreach (TopbarMenuItemResource::form(Schema::make($this->host()))->getFlatComponents() as $component) {
            if (method_exists($component, 'getName') && $component->getName() === 'favicon_url') {
                $field = $component;

                break;
            }
        }

        $this->assertNotNull($field, 'The form no longer has a favicon_url field.');

        $suffixes = array_map(
            fn ($action): string => $action->getName(),
            $field->getSuffixActions(),
        );

        $this->assertContains('fetchFavicon', $suffixes);
    }

    protected function table(): Table
    {
        $livewire = new class extends Component implements HasActions, HasSchemas, HasTable
        {
            use InteractsWithActions;
            use InteractsWithSchemas;
            use InteractsWithTable;

            public function render(): string
            {
                return '';
            }
        };

        $table = TopbarMenuItemResource::table(Table::make($livewire));

        (new ReflectionProperty($livewire, 'table'))->setValue($livewire, $table);

        return $table;
    }

    protected function host(): Component
    {
        return new class extends Component implements HasActions, HasSchemas
        {
            use InteractsWithActions;
            use InteractsWithSchemas;

            public function render(): string
            {
                return '';
            }
        };
    }
}
