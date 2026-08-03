<?php

namespace Vaslv\FilamentTopbarMenu\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Support\SupportServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Vaslv\FilamentTopbarMenu\TopbarMenuServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            SupportServiceProvider::class,
            TopbarMenuServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('app.url', 'https://myapp.test');
    }

    /**
     * Make the given URL the current request — the visibility and isActive
     * logic read the request host/port/path through the container binding.
     */
    protected function bindRequest(string $url): void
    {
        $this->app->instance('request', Request::create($url));
    }

    /**
     * A 1×1 PNG — the smallest input that is a real image to every sniffer.
     * Shared here because five test classes embed favicons and all of them
     * need real image bytes, not a string that merely claims to be one.
     */
    protected const PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\x0dIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\x0dIDATx\xda c\xfc\x0f\x00\x02\x05\x01\x01\x84\xa9\x8c!\x00\x00\x00\x00IEND\xaeB`\x82";

    /**
     * A real 16×16 ICO wrapping a BMP — the format `favicon.ico` actually is,
     * and the one the conventional probe tries first. PHP reports it as
     * `image/vnd.microsoft.icon`, not `image/x-icon`.
     */
    protected static function ico(): string
    {
        $width = 16;
        $height = 16;
        $pixels = str_repeat("\x00\x00\xff\xff", $width * $height);
        $mask = str_repeat("\x00", intdiv($width, 8) * $height);
        $dib = pack('VVVvvVVVVVV', 40, $width, $height * 2, 1, 32, 0, strlen($pixels) + strlen($mask), 0, 0, 0, 0);
        $image = $dib.$pixels.$mask;

        return pack('vvv', 0, 1, 1)
            .pack('CCCCvvVV', $width, $height, 0, 0, 1, 32, strlen($image), 22)
            .$image;
    }

    /**
     * An SVG shaped like one a design tool exports: an XML declaration, a
     * generator comment and a DOCTYPE ahead of the root element, which is what
     * pushes `<svg>` past the first kilobyte of the file.
     */
    protected static function svgWithLongPreamble(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<!-- '.str_repeat('Generator: Adobe Illustrator 28.0.0, SVG Export Plug-In. ', 20).' -->'."\n"
            .'<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">'."\n"
            .'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><rect width="16" height="16"/></svg>';
    }
}
