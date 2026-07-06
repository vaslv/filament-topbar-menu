<?php

namespace Vaslv\FilamentTopbarMenu\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Database\Eloquent\Collection items()
 * @method static \Illuminate\Database\Eloquent\Collection visibleItems(?\Illuminate\Contracts\Auth\Authenticatable $user = null)
 * @method static void flushCache()
 * @method static string cacheKey()
 *
 * @see \Vaslv\FilamentTopbarMenu\TopbarMenu
 */
class TopbarMenu extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Vaslv\FilamentTopbarMenu\TopbarMenu::class;
    }
}
