<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

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

        $langPath = __DIR__.'/../resources/lang';
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
}
