<?php

namespace Devespresso\LaravelApiKit\Tests\Feature\ServiceProvider;

use Devespresso\LaravelApiKit\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_hidden_attributes_prefix_config_is_set(): void
    {
        $this->assertSame('!', config('devespressoApi.transformers.prefixes.hidden_attributes'));
    }

    public function test_custom_attributes_prefix_config_is_set(): void
    {
        $this->assertSame('@', config('devespressoApi.transformers.prefixes.custom_attributes'));
    }

    public function test_unmerged_format_prefix_config_is_set(): void
    {
        $this->assertSame('_', config('devespressoApi.transformers.prefixes.unmerged_format'));
    }

    public function test_pagination_with_pages_defaults_to_false(): void
    {
        $this->assertFalse(config('devespressoApi.pagination.with_pages'));
    }

    public function test_auto_select_config_is_present(): void
    {
        $this->assertNotNull(config('devespressoApi.auto_select'));
    }

    public function test_auto_eager_load_config_is_present(): void
    {
        $this->assertNotNull(config('devespressoApi.auto_eager_load'));
    }
}
