<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;
use Vaslv\FilamentTopbarMenu\TopbarMenu;

/**
 * What the render path refuses even though the database holds it.
 *
 * `favicon_data` and `icon` were already validated on the way out; `url` and
 * `favicon_url` were not, and they are the two that reach an anchor `href` and
 * an `<img src>` on every panel page. The form and the import are not the only
 * writers these columns have — the package documents the others itself: a
 * sibling app on a fleet-shared menu table, a seeder, hand-written SQL — so the
 * last gate before the markup is the one that has to hold.
 */
class RenderTrustTest extends TestCase
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

    /**
     * @return array<string, array{string}>
     */
    public static function executableUrls(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'javascript, mixed case' => ['JaVaScRiPt:alert(1)'],
            'javascript, comment-wrapped' => ['javascript://%0aalert(1)'],
            // A browser strips tab/newline before it reads the scheme; PHP does
            // not, so these are the shapes where the two disagree.
            'javascript, split by a newline' => ["java\nscript:alert(1)"],
            'javascript, split by a tab' => ["java\tscript:alert(1)"],
            'javascript, leading whitespace' => ['  javascript:alert(1)'],
            'data html' => ['data:text/html,<script>alert(1)</script>'],
            'vbscript' => ['vbscript:msgbox(1)'],
            'file' => ['file:///etc/passwd'],
            'view-source' => ['view-source://example.com/'],
            'http without a host' => ['http:favicon.ico'],
        ];
    }

    #[DataProvider('executableUrls')]
    public function test_resolve_url_refuses_a_url_the_render_path_must_not_emit(string $url): void
    {
        $item = TopbarMenuItem::create(['label' => 'Bad', 'type' => TopbarMenuItem::TYPE_URL]);
        $item->forceFill(['url' => $url])->save();

        $this->assertNull($item->resolveUrl(), "[{$url}] must not reach an href.");
    }

    /**
     * @return array<string, array{string}>
     */
    public static function legitimateUrls(): array
    {
        return [
            'https' => ['https://example.com/docs'],
            'http' => ['http://example.com'],
            // The package's own seeder ships two of these.
            'root-relative' => ['/telescope'],
            'path-relative' => ['reports/monthly'],
            'mailto' => ['mailto:team@example.com'],
            'tel' => ['tel:+123456789'],
        ];
    }

    #[DataProvider('legitimateUrls')]
    public function test_resolve_url_keeps_a_link_a_menu_legitimately_holds(string $url): void
    {
        $item = TopbarMenuItem::create(['label' => 'Good', 'type' => TopbarMenuItem::TYPE_URL]);
        $item->forceFill(['url' => $url])->save();

        $this->assertSame($url, $item->resolveUrl());
    }

    public function test_the_menu_view_drops_an_item_whose_url_is_executable(): void
    {
        $item = TopbarMenuItem::create(['label' => 'Payroll', 'type' => TopbarMenuItem::TYPE_URL]);
        $item->forceFill(['url' => 'javascript:alert(document.cookie)'])->save();

        $html = view('filament-topbar-menu::menu', [
            'items' => app(TopbarMenu::class)->visibleItems(),
            'user' => null,
        ])->render();

        // The item is skipped whole, not escaped into the page: an escaped
        // `javascript:` href still runs on click.
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('Payroll', $html);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unrenderableFaviconUrls(): array
    {
        return [
            // An authenticated same-origin GET fired from every panel page.
            'same-origin path' => ['/admin/users/1/delete'],
            'protocol-relative' => ['//tracker.example.com/beacon.gif'],
            'no host' => ['http:favicon.ico'],
            'data uri' => ['data:image/png;base64,AAAA'],
            'quote in the value' => ['https://evil.test/a.png" onerror="alert(1)'],
            'whitespace in the value' => ['https://evil.test/a b.png'],
        ];
    }

    #[DataProvider('unrenderableFaviconUrls')]
    public function test_display_icon_refuses_a_favicon_link_it_would_not_have_stored(string $faviconUrl): void
    {
        $item = TopbarMenuItem::create([
            'label' => 'Docs',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://example.com',
            'icon' => 'heroicon-o-book-open',
        ]);

        $item->forceFill(['favicon_url' => $faviconUrl])->save();

        // Falls back to the validated icon name rather than rendering the link.
        $this->assertSame('heroicon-o-book-open', $item->displayIcon());
    }

    public function test_display_icon_keeps_a_favicon_link_the_resolver_would_have_stored(): void
    {
        $item = TopbarMenuItem::create([
            'label' => 'Docs',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://example.com',
            'icon' => 'heroicon-o-book-open',
            'favicon_url' => 'https://example.com/favicon.ico',
        ]);

        $this->assertSame('https://example.com/favicon.ico', $item->displayIcon());
    }

    public function test_the_menu_view_does_not_emit_a_refused_favicon_link(): void
    {
        $item = TopbarMenuItem::create([
            'label' => 'Docs',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://example.com',
        ]);

        $item->forceFill(['favicon_url' => '/admin/users/1/delete'])->save();

        $html = view('filament-topbar-menu::menu', [
            'items' => app(TopbarMenu::class)->visibleItems(),
            'user' => null,
        ])->render();

        $this->assertStringContainsString('Docs', $html);
        $this->assertStringNotContainsString('users/1/delete', $html);
    }

    public function test_the_three_gates_share_one_definition(): void
    {
        // The favicon rule used to exist three times — in the resolver, in the
        // import and (as a slash test) at render — and the copies had drifted:
        // one accepted a host-less `http:` URL the others refused. One
        // definition, so a change to it cannot reach two of the three.
        $this->assertFalse(TopbarMenuItem::isStorableFaviconUrl('http:favicon.ico'));
        $this->assertFalse(TopbarMenuItem::isStorableFaviconUrl(str_repeat('a', 300)));
        $this->assertTrue(TopbarMenuItem::isStorableFaviconUrl('https://example.com/favicon.ico'));
    }
}
