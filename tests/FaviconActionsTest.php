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
use Illuminate\Support\Facades\Http;
use Livewire\Component;
use ReflectionMethod;
use ReflectionProperty;
use Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;

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

    public function test_the_form_offers_an_upload_and_an_embed_button_for_the_icon_bytes(): void
    {
        $components = [];

        foreach (TopbarMenuItemResource::form(Schema::make($this->host()))->getFlatComponents() as $component) {
            if (method_exists($component, 'getName')) {
                $components[$component->getName()] = $component;
            }
        }

        // The stored value, the uploader that writes into it, and the preview
        // of what is actually stored — FileUpload can neither display a `data:`
        // URI as its state nor round-trip one, so the three are separate.
        $this->assertArrayHasKey('favicon_data', $components);
        $this->assertArrayHasKey('favicon_upload', $components);
        $this->assertArrayHasKey('favicon_data_preview', $components);

        $hints = array_map(
            fn ($action): string => $action->getName(),
            $components['favicon_upload']->getHintActions(),
        );

        $this->assertContains('embedFavicon', $hints);
    }

    public function test_the_upload_field_is_never_saved_to_the_record_itself(): void
    {
        foreach (TopbarMenuItemResource::form(Schema::make($this->host()))->getFlatComponents() as $component) {
            if (method_exists($component, 'getName') && $component->getName() === 'favicon_upload') {
                // The record has no such column: the upload is read in the
                // browser round-trip and turned into `favicon_data`, so
                // dehydrating it would try to write a field that isn't there.
                $this->assertFalse($component->isDehydrated());

                return;
            }
        }

        $this->fail('The form no longer has a favicon_upload field.');
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

    public function test_the_form_round_trips_a_stored_embedded_favicon(): void
    {
        $data = TopbarMenuItem::faviconDataFromBytes(self::PNG);

        $item = TopbarMenuItem::create([
            'label' => 'Embedded',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://example.com',
            'favicon_data' => $data,
        ]);

        $host = $this->host();

        $schema = TopbarMenuItemResource::form(
            Schema::make($host)->statePath('data')->model($item),
        );

        $schema->fill($item->attributesToArray());

        $state = $schema->getState(afterValidate: fn () => null);

        // The value survives the form untouched — the upload field and the
        // preview are UI around it, not extra columns to write.
        $this->assertSame($data, $state['favicon_data'] ?? null);
        $this->assertArrayNotHasKey('favicon_upload', $state);
        $this->assertArrayNotHasKey('favicon_data_preview', $state);
    }

    public function test_there_is_no_bulk_way_to_overwrite_an_embedded_icon(): void
    {
        $names = [];

        foreach ($this->table()->getToolbarActions() as $action) {
            foreach ($action instanceof BulkActionGroup ? $action->getActions() : [$action] as $child) {
                $names[] = $child->getName();
            }
        }

        // An embedded icon can be one an admin uploaded by hand — for a host
        // behind auth that is the only way to get one, and nothing can
        // re-fetch it. A single click that overwrites a selection of them is a
        // one-way door, so the bulk path is links only.
        $this->assertContains('refreshFavicons', $names);

        // Matched on shape, not on one spelling. This used to assert against
        // the literal `embedFavicons`, a name that has never existed — the real
        // action is `embedFavicon`, singular — so copying it into the toolbar,
        // which is exactly the door this guards, would have passed unchanged.
        $this->assertSame(
            [],
            array_values(array_filter($names, fn (string $name): bool => str_contains(strtolower($name), 'embed'))),
        );
    }

    public function test_the_bulk_link_action_skips_items_it_cannot_resolve(): void
    {
        Http::fake(['*/favicon.ico' => Http::response(self::PNG, 200, ['Content-Type' => 'image/x-icon'])]);

        $url = TopbarMenuItem::create([
            'label' => 'External',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://example.com',
        ]);

        $route = TopbarMenuItem::create([
            'label' => 'Internal',
            'type' => TopbarMenuItem::TYPE_ROUTE,
            'route' => 'dashboard',
        ]);

        $embedded = TopbarMenuItem::create([
            'label' => 'Hand-uploaded',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://behind-auth.example.com',
            'favicon_data' => TopbarMenuItem::faviconDataFromBytes(self::PNG),
        ]);

        $resolve = new ReflectionMethod(TopbarMenuItemResource::class, 'resolveFaviconsFor');
        $resolve->invoke(null, TopbarMenuItem::query()->get());

        $this->assertSame('https://example.com/favicon.ico', $url->refresh()->favicon_url);

        // A route item has no URL to resolve — the guard must skip it rather
        // than resolving the panel's own host.
        $this->assertNull($route->refresh()->favicon_url);

        // The link is stored, but the hand-uploaded icon is left exactly where
        // it was: this action never writes favicon_data.
        $this->assertNotNull($embedded->refresh()->favicon_data);
    }

    public function test_removing_the_upload_clears_the_stored_icon(): void
    {
        $schema = TopbarMenuItemResource::form(
            Schema::make($this->host())->statePath('data')->model(TopbarMenuItem::class),
        );

        $schema->fill([
            'label' => 'Embedded',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://example.com',
            'favicon_data' => TopbarMenuItem::faviconDataFromBytes(self::PNG),
        ]);

        $upload = $this->formComponent($schema, 'favicon_upload');

        // Filament runs the same afterStateUpdated hook right after unsetting
        // the file (BaseFileUpload::removeUploadedFile), so clearing the widget
        // has to clear the value. Bailing out on an empty state would leave the
        // icon the admin just removed still stored and still in the topbar.
        $upload->rawState([]);
        $upload->callAfterStateUpdated();

        $state = $schema->getRawState();

        $this->assertArrayHasKey('favicon_data', $state);
        $this->assertNull($state['favicon_data']);
    }

    public function test_the_preview_refuses_anything_the_topbar_would_refuse(): void
    {
        $preview = new ReflectionMethod(TopbarMenuItemResource::class, 'faviconDataPreview');

        foreach ([
            null,
            'javascript:alert(1)',
            'data:text/html;base64,'.base64_encode('<script>alert(1)</script>'),
            'data:image/png;base64,"><script>alert(1)</script>',
        ] as $stored) {
            // The preview is HTML this package builds by hand, so it gates on
            // the same check displayIcon() uses instead of merely escaping.
            $this->assertSame('—', $preview->invoke(null, $stored)->toHtml());
        }

        $data = TopbarMenuItem::faviconDataFromBytes(self::PNG);

        $this->assertStringContainsString('<img src="'.$data.'"', $preview->invoke(null, $data)->toHtml());
    }

    public function test_a_client_supplied_favicon_data_value_is_not_written_verbatim(): void
    {
        // A Hidden field's state is client-controlled: a panel user can PATCH
        // `data.favicon_data` to anything. displayIcon() would refuse a bad
        // value at render time, but without this the row is still written —
        // uncapped — into the column that rides in the cached menu snapshot.
        $this->assertNull($this->saveState('data:text/html;base64,'.base64_encode('<script>alert(1)</script>')));

        config()->set('filament-topbar-menu.favicon_max_bytes', strlen(self::PNG) - 1);

        $this->assertNull($this->saveState('data:image/png;base64,'.base64_encode(self::PNG)));

        config()->set('filament-topbar-menu.favicon_max_bytes', TopbarMenuItem::FAVICON_MAX_BYTES_DEFAULT);

        $data = TopbarMenuItem::faviconDataFromBytes(self::PNG);

        $this->assertSame($data, $this->saveState($data));
    }

    /**
     * What the form would write to `favicon_data` for a given submitted state.
     */
    protected function saveState(?string $faviconData): ?string
    {
        $schema = TopbarMenuItemResource::form(
            Schema::make($this->host())->statePath('data')->model(TopbarMenuItem::class),
        );

        $schema->fill([
            'label' => 'Item',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://example.com',
            'target' => TopbarMenuItem::TARGET_SELF,
        ]);

        $this->formComponent($schema, 'favicon_data')->rawState($faviconData);

        return $schema->getState(afterValidate: fn () => null)['favicon_data'] ?? null;
    }

    protected function formComponent(Schema $schema, string $name): mixed
    {
        foreach ($schema->getFlatComponents() as $component) {
            if (method_exists($component, 'getName') && $component->getName() === $name) {
                return $component;
            }
        }

        $this->fail("The form no longer has a {$name} component.");
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

            /** @var array<string, mixed> */
            public ?array $data = [];

            public function render(): string
            {
                return '';
            }
        };
    }
}
