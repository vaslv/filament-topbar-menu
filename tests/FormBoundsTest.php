<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Field;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;
use Vaslv\FilamentTopbarMenu\Filament\Resources\TopbarMenuItemResource;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;

/**
 * The bounds the form owes the columns behind it.
 *
 * The import has always rejected an over-length value up front, "rather than
 * letting the database truncate them silently (MySQL non-strict) or throw a raw
 * driver exception (MySQL strict)" — its own words. The form, which is the path
 * an admin actually writes through, enforced that on `label` alone.
 */
class FormBoundsTest extends TestCase
{
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

    protected function field(string $name): Field
    {
        // withHidden: the `url` field is only shown for a URL-type item, and
        // its rules are the same either way.
        $components = TopbarMenuItemResource::form(Schema::make($this->host()))
            ->getFlatComponents(withHidden: true);

        foreach ($components as $component) {
            if ($component instanceof Field && $component->getName() === $name) {
                return $component;
            }
        }

        $this->fail("The form no longer has a [{$name}] field.");
    }

    /**
     * Whether the form's own rules for a field reject a value.
     */
    protected function rejects(string $name, mixed $value): bool
    {
        return Validator::make(
            [$name => $value],
            [$name => $this->field($name)->getValidationRules()],
        )->fails();
    }

    public function test_the_string_fields_are_bounded_by_the_column_they_write(): void
    {
        $tooLong = 'https://example.com/'.str_repeat('a', TopbarMenuItem::URL_MAX_LENGTH);

        $this->assertTrue($this->rejects('url', $tooLong));
        $this->assertTrue($this->rejects('favicon_url', $tooLong));
        $this->assertTrue($this->rejects('icon', str_repeat('a', TopbarMenuItem::URL_MAX_LENGTH + 1)));

        // The bound is the column's, so a value that fits still passes.
        $this->assertFalse($this->rejects('url', 'https://example.com/reports'));
        $this->assertFalse($this->rejects('favicon_url', 'https://example.com/favicon.ico'));
        $this->assertFalse($this->rejects('icon', 'heroicon-o-book-open'));
    }

    public function test_the_link_fields_accept_only_http_schemes(): void
    {
        // Laravel's bare `url` rule accepts a long scheme list. None of these
        // executes script in a current browser, which is why the render-time
        // gate is the security boundary and this is tidiness — but the form is
        // the guided path and has no business storing any of them.
        foreach (['data://text/html;base64,PHNjcmlwdD4=', 'view-source://example.com/', 'jar://example.com/x'] as $url) {
            $this->assertTrue($this->rejects('url', $url), "[{$url}] must not pass the form.");
            $this->assertTrue($this->rejects('favicon_url', $url), "[{$url}] must not pass the form.");
        }

        $this->assertFalse($this->rejects('url', 'http://example.com'));
        $this->assertFalse($this->rejects('url', 'https://example.com'));
    }

    public function test_sort_takes_an_integer_the_column_can_hold(): void
    {
        // numeric() alone let `2.5` through to `$table->integer('sort')`: a
        // driver error on PostgreSQL and strict MySQL, and on SQLite a stored
        // 2.5 that ordered() sorts by and no admin can reproduce.
        $this->assertTrue($this->rejects('sort', '2.5'));
        $this->assertTrue($this->rejects('sort', '10000000000'));

        $this->assertFalse($this->rejects('sort', '0'));
        $this->assertFalse($this->rejects('sort', '-5'));
        $this->assertFalse($this->rejects('sort', '2147483647'));
    }
}
