<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use Filament\Actions\ActionsServiceProvider;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use ReflectionProperty;
use Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;
use Vaslv\FilamentTopbarMenu\Tests\Fixtures\RoleAwareUser;
use Vaslv\FilamentTopbarMenu\Tests\Fixtures\TestPanelProvider;
use Vaslv\FilamentTopbarMenu\Tests\Fixtures\TopbarMenuItemPolicy;

/**
 * The two surfaces of the items table that write without Filament consulting a
 * policy on its own: the inline `is_active` toggle and the custom bulk action.
 *
 * Both are reachable by anyone the `viewAny` policy admits to the list page, so
 * each has to carry its own gate — and on a fleet-shared menu table the blast
 * radius of missing one is every app reading that table, not just this panel.
 */
class AuthorizationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        // A real panel: Resource::can() reads the panel's authorization
        // settings, so the gate under test cannot be evaluated without one.
        return array_merge(parent::getPackageProviders($app), [
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
            TestPanelProvider::class,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Gate::policy(TopbarMenuItem::class, TopbarMenuItemPolicy::class);
    }

    /**
     * Sign in as a user holding exactly the given abilities.
     */
    protected function actingAsUserAllowedTo(string ...$abilities): RoleAwareUser
    {
        config([TopbarMenuItemPolicy::ABILITIES => $abilities]);

        $user = new RoleAwareUser;
        $user->id = 1;

        $this->actingAs($user);

        return $user;
    }

    /**
     * The resource's table as a panel builds it: `configureTable()`, not
     * `table()`, because that is where Filament attaches the framework-owned
     * gates (`authorizeReorder`) the package inherits rather than declares.
     */
    protected function menuItemsTable(): Table
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

        $table = Table::make($livewire);

        TopbarMenuItemResource::configureTable($table);

        (new ReflectionProperty($livewire, 'table'))->setValue($livewire, $table);

        return $table;
    }

    protected function isActiveColumn(TopbarMenuItem $record): ToggleColumn
    {
        /** @var ToggleColumn $column */
        $column = $this->menuItemsTable()->getColumn('is_active');

        // The record is bound before isDisabled() is evaluated — see
        // HasColumns::updateTableColumnState, the caller this mirrors.
        $column->record($record);

        return $column;
    }

    protected function toolbarAction(string $name): BulkAction
    {
        foreach ($this->menuItemsTable()->getToolbarActions() as $action) {
            foreach ($action instanceof BulkActionGroup ? $action->getActions() : [$action] as $child) {
                if ($child->getName() === $name) {
                    /** @var BulkAction $child */
                    return $child;
                }
            }
        }

        $this->fail("The items table has no toolbar action named [{$name}].");
    }

    public function test_the_is_active_toggle_is_disabled_for_a_user_who_cannot_update(): void
    {
        $record = TopbarMenuItem::create(['label' => 'Home', 'url' => 'https://example.com']);

        $this->actingAsUserAllowedTo('viewAny', 'view');

        // `disabled()` is the only gate Filament consults before an inline
        // editable column saves, so this assertion IS the authorization.
        $this->assertTrue($this->isActiveColumn($record)->isDisabled());
    }

    public function test_the_is_active_toggle_stays_usable_for_a_user_who_can_update(): void
    {
        $record = TopbarMenuItem::create(['label' => 'Home', 'url' => 'https://example.com']);

        $this->actingAsUserAllowedTo('viewAny', 'view', 'update');

        $this->assertFalse($this->isActiveColumn($record)->isDisabled());
    }

    public function test_the_is_active_toggle_is_judged_per_record(): void
    {
        // A policy that answers per record must be asked per record; deciding
        // once for the column would be the subtle version of the same hole.
        $allowed = TopbarMenuItem::create(['label' => 'Editable', 'url' => 'https://example.com']);
        $denied = TopbarMenuItem::create(['label' => 'Locked', 'url' => 'https://example.org']);

        $this->actingAsUserAllowedTo('viewAny', 'view', 'update');

        config([TopbarMenuItemPolicy::LOCKED => [$denied->getKey()]]);

        $this->assertFalse($this->isActiveColumn($allowed)->isDisabled());
        $this->assertTrue($this->isActiveColumn($denied)->isDisabled());
    }

    public function test_the_bulk_favicon_action_authorizes_every_selected_record(): void
    {
        $record = TopbarMenuItem::create(['label' => 'Home', 'url' => 'https://example.com']);

        $action = $this->toolbarAction('refreshFavicons');

        // A custom BulkAction has no authorization of its own, so the package
        // has to declare it. Filament filters the selection with this same
        // response, which is why it is also what the action's `$records` are.
        $this->assertTrue($action->shouldAuthorizeIndividualRecords());

        $this->actingAsUserAllowedTo('viewAny', 'view');
        $this->assertFalse($action->getIndividualRecordAuthorizationResponse($record)->allowed());

        $this->actingAsUserAllowedTo('viewAny', 'view', 'update');
        $this->assertTrue($action->getIndividualRecordAuthorizationResponse($record)->allowed());
    }

    public function test_reordering_stays_behind_the_reorder_policy(): void
    {
        // Filament wires this one itself — Resource::configureTable() calls
        // ->authorizeReorder(static::canReorder(...)) — so the assertion pins
        // that ->reorderable('sort') does not opt out of it by passing a
        // condition of its own.
        $table = $this->menuItemsTable();

        $this->actingAsUserAllowedTo('viewAny', 'view');
        $this->assertFalse($table->isReorderAuthorized());

        $this->actingAsUserAllowedTo('viewAny', 'view', 'reorder');
        $this->assertTrue($table->isReorderAuthorized());
    }
}
