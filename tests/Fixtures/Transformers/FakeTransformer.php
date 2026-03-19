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
    public ?string $latestVersion = null;
    public ?array $versionedRenames = null;
    public ?array $versionedFormatters = null;
    public ?array $versionedGuarded = null;
    public ?array $versionedDefaults = null;
    public ?array $versionedCustomAttributes = null;

    // -------------------------------------------------------------------------
    // Public wrappers for protected methods
    // -------------------------------------------------------------------------

    public function callFormatModel($collection, $attributes, array $currentKey = []): ?array
    {
        return $this->formatModel($collection, $attributes, $currentKey);
    }

    public function callResolveVersionedFormats(?string $version = null): array
    {
        return $this->resolveVersionedFormats($version);
    }

    public function callResolveVersionChain(?string $requestedVersion, array $versions): array
    {
        return $this->resolveVersionChain($requestedVersion, $versions);
    }

    public function callApplyVersionChain(array $formats, array $versions): array
    {
        return $this->applyVersionChain($formats, $versions);
    }

    public function callAppendItems(array $formatArray, array $items): array
    {
        return $this->appendItems($formatArray, $items);
    }

    public function callRemoveItems(array $formatArray, array $items): array
    {
        return $this->removeItems($formatArray, $items);
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
