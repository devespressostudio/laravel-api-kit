<?php

namespace Devespresso\LaravelApiKit\Tests\Fixtures\Transformers;

use Devespresso\LaravelApiKit\Transformers\BaseTransformer;

/**
 * Concrete subclass of BaseTransformer for testing.
 *
 * Exposes protected methods via public wrappers and provides a set of
 * real formatter, guard, and default methods that tests can reference
 * by name in $formatters, $guarded, and $defaults config arrays.
 */
class FakeTransformer extends BaseTransformer
{
    public $formats = [];
    public $renames = [];
    public $formatters = [];
    public $customAttributes = [];
    public $defaults = [];
    public $guarded = [];

    // -------------------------------------------------------------------------
    // Public wrappers for protected methods
    // -------------------------------------------------------------------------

    public function callFormatModel($collection, $attributes, array $currentKey = []): ?array
    {
        return $this->formatModel($collection, $attributes, $currentKey);
    }

    public function callRenameKey(string $attribute, array $currentKey = []): string
    {
        return $this->renameKey($attribute, $currentKey);
    }

    public function callFindFormatters(string $attribute, $value, array $currentKey = [])
    {
        return $this->findFormatters($attribute, $value, $currentKey);
    }

    public function callRunFormatters($value, $formatters): mixed
    {
        return $this->runFormatters($value, $formatters);
    }

    public function callCheckForCustomAttributeAndGetValue(string $attribute, $model, bool $isCustomAttribute)
    {
        return $this->checkForCustomAttributeAndGetValue($attribute, $model, $isCustomAttribute);
    }

    public function callIsAttributeGuarded(string $attribute, array $currentKey, $model): bool
    {
        return $this->isAttributeGuarded($attribute, $currentKey, $model);
    }

    // -------------------------------------------------------------------------
    // Formatter methods (referenced by name in $formatters)
    // -------------------------------------------------------------------------

    public function toUpper($value): string
    {
        return strtoupper((string) $value);
    }

    public function addPrefix($value, string $prefix): string
    {
        return $prefix . $value;
    }

    public function doubleValue($value): int
    {
        return (int) $value * 2;
    }

    // -------------------------------------------------------------------------
    // Guard methods (referenced by name in $guarded, must return bool)
    // -------------------------------------------------------------------------

    public function alwaysGuard($model): bool
    {
        return true;
    }

    public function neverGuard($model): bool
    {
        return false;
    }

    // -------------------------------------------------------------------------
    // Default methods (referenced by name in $defaults)
    // -------------------------------------------------------------------------

    public function getDefaultStatus($model): string
    {
        return 'active';
    }

    // -------------------------------------------------------------------------
    // Custom attribute methods (referenced by name in $customAttributes)
    // -------------------------------------------------------------------------

    public function getFullName($model): string
    {
        return $model->first_name . ' ' . $model->last_name;
    }
}
