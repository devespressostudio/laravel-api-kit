<?php

namespace Devespresso\LaravelApiKit\Tests;

use Devespresso\LaravelApiKit\LaravelApiKitServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelApiKitServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('devespressoApi.transformers.prefixes.hidden_attributes', '!');
        $app['config']->set('devespressoApi.transformers.prefixes.custom_attributes', '@');
        $app['config']->set('devespressoApi.transformers.prefixes.accessor_attributes', '~');
        $app['config']->set('devespressoApi.transformers.prefixes.unmerged_format', '_');
        $app['config']->set('devespressoApi.auto_select', false);
        $app['config']->set('devespressoApi.auto_eager_load', false);
        $app['config']->set('devespressoApi.pagination.with_pages', false);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    protected function createFakeUsersTable(): void
    {
        Schema::create('fake_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }
}
