<?php

namespace Devespresso\LaravelApiKit\Tests\Fixtures\Services;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Invokable resolver that always returns null — simulates unauthenticated / roleless users.
 */
class FakeNullRoleResolver
{
    public function __invoke(?Authenticatable $user): mixed
    {
        return null;
    }
}
