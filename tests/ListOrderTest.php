<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Tables\Table;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use ReflectionProperty;
use Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource;
use Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource\Pages\ListTopbarMenuItems;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;

/**
 * The items table lists the menu, so it has to read like the menu: an item's
 * `sort` is its position among its siblings, not a number the whole table can
 * be lined up by. Ordering flat by that column interleaves children with the
 * roots of other groups, and the page whose job is "what does the topbar look
 * like" then answers something else.
 */
class ListOrderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), [
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
        ]);
    }

    public function test_children_follow_their_parent_instead_of_the_flat_sort_column(): void
    {
        $services = TopbarMenuItem::create(['label' => 'Services', 'url' => 'https://a.example.com', 'sort' => 0]);
        $docs = TopbarMenuItem::create(['label' => 'Docs', 'url' => 'https://b.example.com', 'sort' => 10]);

        TopbarMenuItem::create(['label' => 'Horizon', 'url' => 'https://c.example.com', 'sort' => 5, 'parent_id' => $services->id]);
        TopbarMenuItem::create(['label' => 'Telescope', 'url' => 'https://d.example.com', 'sort' => 20, 'parent_id' => $services->id]);
        TopbarMenuItem::create(['label' => 'API', 'url' => 'https://e.example.com', 'sort' => 1, 'parent_id' => $docs->id]);

        // Ordered by the raw column this would be Services, API, Horizon, Docs,
        // Telescope — the API row sitting above the group it belongs to, and
        // Telescope orphaned at the bottom.
        $this->assertSame(
            ['Services', 'Horizon', 'Telescope', 'Docs', 'API'],
            $this->listedLabels(),
        );
    }

    public function test_equal_sort_values_are_broken_the_way_the_rendered_menu_breaks_them(): void
    {
        $first = TopbarMenuItem::create(['label' => 'First', 'url' => 'https://a.example.com', 'sort' => 0]);
        $second = TopbarMenuItem::create(['label' => 'Second', 'url' => 'https://b.example.com', 'sort' => 0]);

        TopbarMenuItem::create(['label' => 'Second child', 'url' => 'https://c.example.com', 'sort' => 0, 'parent_id' => $second->id]);
        TopbarMenuItem::create(['label' => 'First child', 'url' => 'https://d.example.com', 'sort' => 0, 'parent_id' => $first->id]);

        // Every sort is 0, so insertion order decides — the same `id` tie-break
        // TopbarMenuItem::ordered() applies when the menu itself is built.
        $this->assertSame(
            ['First', 'First child', 'Second', 'Second child'],
            $this->listedLabels(),
        );
    }

    public function test_the_order_matches_the_menu_the_topbar_renders(): void
    {
        $services = TopbarMenuItem::create(['label' => 'Services', 'url' => 'https://a.example.com', 'sort' => 3]);
        $docs = TopbarMenuItem::create(['label' => 'Docs', 'url' => 'https://b.example.com', 'sort' => 1]);

        TopbarMenuItem::create(['label' => 'Horizon', 'url' => 'https://c.example.com', 'sort' => 2, 'parent_id' => $services->id]);
        TopbarMenuItem::create(['label' => 'Metrics', 'url' => 'https://d.example.com', 'sort' => 1, 'parent_id' => $services->id]);
        TopbarMenuItem::create(['label' => 'API', 'url' => 'https://e.example.com', 'sort' => 9, 'parent_id' => $docs->id]);

        // The tree the topbar renders, flattened depth-first: this is the whole
        // point of the ordering, so assert against the render path rather than
        // against a second hand-written expectation of it.
        $expected = [];

        foreach (TopbarMenuItem::query()->root()->ordered()->with(['children' => fn ($q) => $q->ordered()])->get() as $root) {
            $expected[] = $root->label;

            foreach ($root->children as $child) {
                $expected[] = $child->label;
            }
        }

        $this->assertSame($expected, $this->listedLabels());
    }

    public function test_a_third_level_row_stays_under_the_item_it_hangs_off(): void
    {
        $rootA = TopbarMenuItem::create(['label' => 'Root A', 'url' => 'https://a.example.com', 'sort' => 0]);
        $rootB = TopbarMenuItem::create(['label' => 'Root B', 'url' => 'https://b.example.com', 'sort' => 5]);

        $child = TopbarMenuItem::create(['label' => 'A-child', 'url' => 'https://c.example.com', 'sort' => 5, 'parent_id' => $rootA->id]);

        TopbarMenuItem::create(['label' => 'A-grandchild', 'url' => 'https://d.example.com', 'sort' => 0, 'parent_id' => $child->id]);
        TopbarMenuItem::create(['label' => 'B-child', 'url' => 'https://e.example.com', 'sort' => 0, 'parent_id' => $rootB->id]);

        // The menu renders two levels, so a third one is a misconfiguration —
        // and this table is where an admin finds those rows. Keyed by the
        // immediate parent, A-grandchild would sort by A-child's `sort` of 5
        // and land past Root B's whole group, at the bottom of the page.
        $this->assertSame(
            ['Root A', 'A-child', 'A-grandchild', 'Root B', 'B-child'],
            $this->listedLabels(),
        );
    }

    public function test_switching_on_reordering_does_not_reshuffle_the_list(): void
    {
        $services = TopbarMenuItem::create(['label' => 'Services', 'url' => 'https://a.example.com', 'sort' => 0]);
        TopbarMenuItem::create(['label' => 'Docs', 'url' => 'https://b.example.com', 'sort' => 10]);
        TopbarMenuItem::create(['label' => 'Horizon', 'url' => 'https://c.example.com', 'sort' => 20, 'parent_id' => $services->id]);

        // The two orders genuinely differ on this data — otherwise the
        // assertion below would hold whether or not the page overrides
        // Filament's reordering sort.
        $this->assertNotSame(
            TopbarMenuItem::query()->orderBy('sort')->pluck('label')->all(),
            $this->listedLabels(),
        );

        // Filament orders a reordering table by the reorder column alone. Left
        // at that, pressing the reorder button would rearrange the rows the
        // admin is about to drag — so both views use the same order.
        $this->assertSame($this->listedLabels(), $this->listedLabels(reordering: true));
    }

    /**
     * The labels in the order the list page puts them in, taken from Filament's
     * own sorting pass rather than from a query this test builds itself.
     *
     * @return list<string>
     */
    protected function listedLabels(bool $reordering = false): array
    {
        $page = new ListTopbarMenuItems;

        (new ReflectionProperty($page, 'table'))->setValue(
            $page,
            TopbarMenuItemResource::table(Table::make($page)),
        );

        $page->isTableReordering = $reordering;

        $query = (new ReflectionMethod($page, 'applySortingToTableQuery'))
            ->invoke($page, TopbarMenuItem::query());

        return $query->pluck('label')->all();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function grammarProvider(): array
    {
        return ['mysql' => ['mysql'], 'pgsql' => ['pgsql'], 'sqlite' => ['sqlite']];
    }

    #[DataProvider('grammarProvider')]
    public function test_the_tree_order_compiles_on_every_supported_grammar(string $driver): void
    {
        // The order is hand-written SQL through the connection's grammar, and
        // CI runs the suite on SQLite alone — so nothing else here would notice
        // a grammar that cannot produce it. Compiling needs no server.
        $sql = TopbarMenuItem::on($driver)->treeOrdered()->toSql();

        $this->assertStringContainsString('topbar_menu_parent', $sql);
        $this->assertStringContainsString('topbar_menu_grandparent', $sql);

        // Every reference to the row's own columns is qualified with the table.
        // That is what lets this coexist with the join Filament adds when the
        // table is sorted by `parent.label`: an unqualified `sort` or `id` would
        // be ambiguous the moment a second copy of the table is in scope, and
        // the failure would be a 500 on the admin list page for that install
        // only. MySQL rejects it; SQLite does not, which is exactly why this
        // cannot be left to the SQLite suite.
        $this->assertStringNotContainsString('order by `sort`', $sql);
        $this->assertStringNotContainsString('order by "sort"', $sql);
        $this->assertMatchesRegularExpression('/order by .*filament_topbar_menu_items.*sort/', $sql);
    }

    public function test_the_tree_order_survives_the_join_filament_adds_for_the_parent_column(): void
    {
        // Sorting the items table by `parent.label` makes Filament join the
        // table to itself. treeOrdered() uses correlated subqueries rather than
        // joins of its own precisely so that this does not become ambiguous.
        $sql = TopbarMenuItem::query()
            ->join('filament_topbar_menu_items as parent', 'parent.id', '=', 'filament_topbar_menu_items.parent_id')
            ->treeOrdered()
            ->toSql();

        // Quoting differs per grammar, so match the alias names themselves.
        $this->assertMatchesRegularExpression('/as .?parent.?/', $sql);
        $this->assertStringContainsString('topbar_menu_parent', $sql);
    }
}
