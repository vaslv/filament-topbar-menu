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

        foreach ($this->shippedLocales() as $locale) {
            if ($locale === 'en') {
                continue;
            }

            $keys = $flatten(require "{$langPath}/{$locale}/filament-topbar-menu.php");
            sort($keys);

            $this->assertSame(
                $reference,
                $keys,
                "Locale [{$locale}] does not define the same translation keys as [en].",
            );
        }
    }

    public function test_every_locale_keeps_the_link_placeholder_in_the_icon_helper(): void
    {
        // The resource substitutes the heroicons.com anchor into :link; a
        // translation that drops the placeholder silently loses the link.
        foreach ($this->shippedLocales() as $locale) {
            $lines = require __DIR__."/../resources/lang/{$locale}/filament-topbar-menu.php";

            $this->assertStringContainsString(
                ':link',
                $lines['fields']['icon']['helper'],
                "Locale [{$locale}] lost the :link placeholder in fields.icon.helper.",
            );
        }
    }

    /**
     * @return list<string>
     */
    private function shippedLocales(): array
    {
        $locales = array_map(
            'basename',
            glob(__DIR__.'/../resources/lang/*', GLOB_ONLYDIR) ?: [],
        );

        sort($locales);

        return $locales;
    }
}
