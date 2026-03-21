<?php

namespace Devespresso\LaravelApiKit\Tests\Fixtures\Models;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A concrete user stub with a configurable role for testing roleMethods dispatch.
 */
class FakeAdminUser implements Authenticatable
{
    public string $role = 'admin';

    public function getAuthIdentifierName(): string { return 'id'; }
    public function getAuthIdentifier(): mixed { return 1; }
    public function getAuthPassword(): string { return ''; }
    public function getAuthPasswordName(): string { return 'password'; }
    public function getRememberToken(): ?string { return null; }
    public function setRememberToken($value): void {}
    public function getRememberTokenName(): string { return 'remember_token'; }
}
