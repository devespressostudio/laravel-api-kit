<?php

namespace Devespresso\LaravelApiKit\Tests\Fixtures\Models;

use Devespresso\LaravelApiKit\Traits\EnableDatabaseFiltering;
use Illuminate\Database\Eloquent\Model;

class FakeUser extends Model
{
    use EnableDatabaseFiltering;

    protected $table = 'fake_users';

    protected $guarded = [];

    protected $perPage = 15;
}
