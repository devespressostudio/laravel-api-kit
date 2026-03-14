<?php

namespace Devespresso\LaravelApiKit\Tests\Fixtures\Models;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A concrete admin user stub for testing adminMethods dispatch.
 * method_exists() must return true for isAdmin() — Mockery stubs won't work
 * here because isAdmin() is not declared on the Authenticatable interface.
 */
class FakeAdminUser implements Authenticatable
{
    public function isAdmin(): bool
    {
        return true;
    }

    public function getAuthIdentifierName(): string { return 'id'; }
    public function getAuthIdentifier(): mixed { return 1; }
    public function getAuthPassword(): string { return ''; }
    public function getAuthPasswordName(): string { return 'password'; }
    public function getRememberToken(): ?string { return null; }
    public function setRememberToken($value): void {}
    public function getRememberTokenName(): string { return 'remember_token'; }
}
