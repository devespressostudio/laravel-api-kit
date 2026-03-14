<?php

namespace Devespresso\LaravelApiKit\Tests\Unit\Services;

use Devespresso\LaravelApiKit\Tests\Fixtures\Services\FakeFilterService;
use Devespresso\LaravelApiKit\Tests\TestCase;

class DataHelpersTest extends TestCase
{
    private FakeFilterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = (new FakeFilterService())
            ->setData(['name' => 'Alice', 'status' => null])
            ->setExtras(['tenant_id' => 42]);
    }

    // -------------------------------------------------------------------------
    // dataHasValue
    // -------------------------------------------------------------------------

    public function test_data_has_value_returns_false_when_key_does_not_exist(): void
    {
        $this->assertFalse($this->service->dataHasValue('email', 'alice@test.com'));
    }

    public function test_data_has_value_returns_true_when_key_and_value_match(): void
    {
        $this->assertTrue($this->service->dataHasValue('name', 'Alice'));
    }

    public function test_data_has_value_returns_false_when_value_does_not_match(): void
    {
        $this->assertFalse($this->service->dataHasValue('name', 'Bob'));
    }

    public function test_data_has_value_returns_true_for_null_value_match(): void
    {
        $this->assertTrue($this->service->dataHasValue('status', null));
    }

    // -------------------------------------------------------------------------
    // getDataValue
    // -------------------------------------------------------------------------

    public function test_get_data_value_returns_null_when_key_is_absent(): void
    {
        $this->assertNull($this->service->getDataValue('email'));
    }

    public function test_get_data_value_returns_the_stored_value(): void
    {
        $this->assertSame('Alice', $this->service->getDataValue('name'));
    }

    public function test_get_data_value_returns_null_not_default_when_key_exists_with_null(): void
    {
        $this->assertNull($this->service->getDataValue('status', 'fallback'));
    }

    public function test_get_data_value_returns_custom_default_when_key_is_absent(): void
    {
        $this->assertSame('default@test.com', $this->service->getDataValue('email', 'default@test.com'));
    }

    // -------------------------------------------------------------------------
    // dataHasKeys
    // -------------------------------------------------------------------------

    public function test_data_has_keys_returns_true_when_all_keys_are_present(): void
    {
        $this->assertTrue($this->service->dataHasKeys(['name', 'status']));
    }

    public function test_data_has_keys_returns_false_when_a_key_is_missing(): void
    {
        $this->assertFalse($this->service->dataHasKeys(['name', 'email']));
    }

    public function test_data_has_keys_returns_true_for_an_empty_keys_array(): void
    {
        $this->assertTrue($this->service->dataHasKeys([]));
    }

    // -------------------------------------------------------------------------
    // getExtraProperty
    // -------------------------------------------------------------------------

    public function test_get_extra_property_returns_value_when_it_exists(): void
    {
        $this->assertSame(42, $this->service->getExtraProperty('tenant_id'));
    }

    public function test_get_extra_property_returns_null_when_property_does_not_exist(): void
    {
        $this->assertNull($this->service->getExtraProperty('missing'));
    }
}
