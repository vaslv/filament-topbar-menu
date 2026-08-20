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

    public function test_every_key_the_source_asks_for_exists_in_english(): void
    {
        $missing = [];

        foreach ($this->sourceFiles() as $file) {
            $contents = (string) file_get_contents($file);

            // Two spellings reach the same namespace: the `trans()` helpers on
            // the resource and the list page, and the full key elsewhere.
            // Interpolated keys ("fields.type.options.{$state}") are skipped —
            // they resolve at runtime and the locale-parity test covers the
            // arrays they index into.
            preg_match_all("/(?:static::|\\\$this->)trans\\('([a-z0-9_.]+)'/i", $contents, $short);
            preg_match_all('/filament-topbar-menu::filament-topbar-menu\\.([a-z0-9_.]+)/i', $contents, $full);

            foreach (array_merge($short[1], $full[1]) as $key) {
                // A trailing dot means the key was built by concatenation
                // ('fields.target.options.'.$suffix) and only its prefix is
                // literal — the same runtime case as an interpolated one.
                if (str_ends_with($key, '.')) {
                    continue;
                }

                if (__("filament-topbar-menu::filament-topbar-menu.{$key}") === "filament-topbar-menu::filament-topbar-menu.{$key}") {
                    $missing[] = $key.' ('.basename($file).')';
                }
            }
        }

        // A key the source asks for and `en` does not define renders as the raw
        // namespaced string in the panel — in every locale at once, since they
        // all fall back to English. Locale parity does not catch it: the locales
        // agree with each other and all of them are missing it.
        $this->assertSame([], array_values(array_unique($missing)));
    }

    /**
     * @return list<string>
     */
    protected function sourceFiles(): array
    {
        $files = [];

        $directory = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(__DIR__.'/../src', \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($directory as $file) {
            if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        $this->assertNotEmpty($files);

        return $files;
    }
}
