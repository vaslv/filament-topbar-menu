<?php

namespace Vaslv\FilamentTopbarMenu\Tests\Fixtures;

use Illuminate\Foundation\Auth\User;

/**
 * A user model with the hasAnyRole() contract the package's role visibility
 * relies on (in real apps it comes from e.g. spatie/laravel-permission).
 */
class RoleAwareUser extends User
{
    /**
     * @param  array<int, string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return true;
    }
}
