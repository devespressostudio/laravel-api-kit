<?php

namespace Devespresso\LaravelApiKit\Tests\Fixtures\Services;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Invokable role resolver fixture for testing.
 * Returns the 'role' property from the user, or null for guests.
 */
class FakeRoleResolver
{
    public function __invoke(?Authenticatable $user): mixed
    {
        return $user?->role ?? null;
    }
}
