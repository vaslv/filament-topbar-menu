<?php

namespace Vaslv\FilamentTopbarMenu\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;

/**
 * A menu item policy whose answers a test picks per case, so the resource can be
 * exercised as the users a real panel has — notably the read-only one, who is
 * the whole point of the authorization tests.
 *
 * The allowed set lives in config rather than in a static property: Testbench
 * rebuilds the container per test, so config cannot leak between them.
 */
class TopbarMenuItemPolicy
{
    /**
     * The config key holding the abilities this policy grants.
     */
    public const ABILITIES = 'testing.topbar_menu_abilities';

    /**
     * The config key holding record keys this policy refuses to update, so a
     * test can prove the per-record path is asked per record.
     */
    public const LOCKED = 'testing.topbar_menu_locked';

    public function viewAny(Authenticatable $user): bool
    {
        return $this->allows('viewAny');
    }

    public function view(Authenticatable $user, TopbarMenuItem $record): bool
    {
        return $this->allows('view');
    }

    public function create(Authenticatable $user): bool
    {
        return $this->allows('create');
    }

    public function update(Authenticatable $user, TopbarMenuItem $record): bool
    {
        return $this->allows('update')
            && ! in_array($record->getKey(), (array) config(self::LOCKED, []), true);
    }

    public function delete(Authenticatable $user, TopbarMenuItem $record): bool
    {
        return $this->allows('delete');
    }

    public function deleteAny(Authenticatable $user): bool
    {
        return $this->allows('deleteAny');
    }

    public function reorder(Authenticatable $user): bool
    {
        return $this->allows('reorder');
    }

    protected function allows(string $ability): bool
    {
        /** @var array<int, string> $abilities */
        $abilities = (array) config(self::ABILITIES, []);

        return in_array($ability, $abilities, true);
    }
}
