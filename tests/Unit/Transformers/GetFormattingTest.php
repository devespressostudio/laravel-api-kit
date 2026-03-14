<?php

namespace Devespresso\LaravelApiKit\Tests\Unit\Transformers;

use Devespresso\LaravelApiKit\Tests\Fixtures\Transformers\FakeTransformer;
use Devespresso\LaravelApiKit\Tests\TestCase;
use Illuminate\Support\Facades\Route;

class GetFormattingTest extends TestCase
{
    private FakeTransformer $transformer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transformer = new FakeTransformer();
    }

    public function test_returns_empty_array_when_formats_is_empty(): void
    {
        Route::shouldReceive('currentRouteAction')->andReturn('App\Http\Controllers\FakeController@index');

        $this->assertSame([], $this->transformer->getFormatting());
    }

    public function test_returns_wildcard_format_as_fallback_when_no_route_key_matches(): void
    {
        Route::shouldReceive('currentRouteAction')->andReturn('App\Http\Controllers\FakeController@nonexistent');
        $this->transformer->formats = ['*' => ['id', 'name']];

        $this->assertSame(['id', 'name'], $this->transformer->getFormatting());
    }

    public function test_merges_wildcard_and_named_format_when_explicit_key_is_passed(): void
    {
        $this->transformer->formats = [
            '*'    => ['id', 'name'],
            'show' => ['email'],
        ];

        $this->assertSame(['id', 'name', 'email'], $this->transformer->getFormatting('show'));
    }

    public function test_merges_in_correct_order_wildcard_first_then_named(): void
    {
        $this->transformer->formats = [
            '*'    => ['id'],
            'show' => ['email'],
        ];

        $this->assertSame(['id', 'email'], array_values($this->transformer->getFormatting('show')));
    }

    public function test_returns_unmerged_format_without_wildcard_when_key_uses_unmerged_prefix(): void
    {
        $this->transformer->formats = [
            '*'      => ['id', 'name'],
            '_index' => ['email'],
        ];

        $this->assertSame(['email'], $this->transformer->getFormatting('index'));
    }

    public function test_falls_back_to_wildcard_when_explicit_format_key_has_no_match(): void
    {
        $this->transformer->formats = ['*' => ['id']];

        $this->assertSame(['id'], $this->transformer->getFormatting('nonexistent'));
    }

    public function test_auto_detects_route_controller_method_when_no_format_key_is_passed(): void
    {
        Route::shouldReceive('currentRouteAction')->andReturn('App\Http\Controllers\UserController@show');
        $this->transformer->formats = [
            '*'    => ['id'],
            'show' => ['email'],
        ];

        $this->assertSame(['id', 'email'], $this->transformer->getFormatting());
    }

    public function test_respects_custom_unmerged_format_prefix_from_config(): void
    {
        config(['devespressoApi.transformers.prefixes.unmerged_format' => '~']);
        $this->transformer->formats = [
            '*'      => ['id'],
            '~index' => ['name'],
            '_index' => ['email'], // should NOT match when prefix is ~
        ];

        $this->assertSame(['name'], $this->transformer->getFormatting('index'));
    }
}
