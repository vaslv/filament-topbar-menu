<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;
use Vaslv\FilamentTopbarMenu\TopbarMenu;

class TranslationTest extends TestCase
{
    public function test_the_package_translations_are_registered(): void
    {
        $this->app->setLocale('en');
        $this->assertSame('Topbar menu', __('filament-topbar-menu::filament-topbar-menu.labels.plural_model'));

        $this->app->setLocale('ru');
        $this->assertSame('Меню верхней панели', __('filament-topbar-menu::filament-topbar-menu.labels.plural_model'));
    }

    public function test_every_shipped_locale_defines_the_same_keys(): void
    {
        $flatten = function (array $array, string $prefix = '') use (&$flatten): array {
            $keys = [];

            foreach ($array as $key => $value) {
                $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

                if (is_array($value)) {
                    $keys = array_merge($keys, $flatten($value, $path));

                    continue;
                }

                $keys[] = $path;
            }

            return $keys;
        };

        $langPath = __DIR__ . '/../resources/lang';
        $reference = $flatten(require "{$langPath}/en/filament-topbar-menu.php");
        sort($reference);

        foreach (['ru', 'de', 'es', 'fr'] as $locale) {
            $keys = $flatten(require "{$langPath}/{$locale}/filament-topbar-menu.php");
            sort($keys);

            $this->assertSame(
                $reference,
                $keys,
                "Locale [{$locale}] does not define the same translation keys as [en].",
            );
        }
    }

    public function test_the_submenu_aria_label_is_translated(): void
    {
        $parent = TopbarMenuItem::create([
            'label' => 'Сервисы',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://example.com',
        ]);

        TopbarMenuItem::create([
            'label' => 'Child',
            'type' => TopbarMenuItem::TYPE_URL,
            'url' => 'https://example.com/child',
            'parent_id' => $parent->id,
        ]);

        $this->app->setLocale('ru');

        $html = view('filament-topbar-menu::menu', [
            'items' => app(TopbarMenu::class)->visibleItems(),
            'user' => null,
        ])->render();

        $this->assertStringContainsString('Открыть подменю «Сервисы»', $html);
    }
}
