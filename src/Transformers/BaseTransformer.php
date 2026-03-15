<?php

namespace Devespresso\LaravelApiKit\Transformers;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

abstract class BaseTransformer
{
    /**
     * Wrapper
     *
     * @var string
     */
    public $wrapper = 'data';

    /**
     * Defines the output shape of the transformer, keyed by route method name.
     *
     * Each key maps to an array of attribute names (or nested relation definitions)
     * that should be included in the output for that context. There are two special keys:
     *
     *  - '*'  — the wildcard/global format, always included and merged with the matched
     *           route key unless the unmerged prefix is used (see below).
     *  - '_methodName' — prefixing a key with the unmerged prefix (configured in
     *           devespressoApi.transformers.prefixes.unmerged_format, typically '_')
     *           returns that format standalone without merging with '*'.
     *
     * Scalar values in the array are treated as column names. Array values are treated
     * as nested relation definitions and are formatted recursively.
     *
     * Attribute prefixes:
     *  - Hidden attributes (configured in devespressoApi.transformers.prefixes.hidden_attributes,
     *    default ':') — the attribute or relation key is excluded from the output entirely.
     *    When used on a relation key, the relation is still eager-loaded for SELECT purposes
     *    but will not appear in the transformed result.
     *      e.g. ':secret_column', ':hiddenRelation' => ['id']
     *
     *  - Custom attributes (configured in devespressoApi.transformers.prefixes.custom_attributes,
     *    default '@') — the value is not read from the database column directly. Instead it is
     *    resolved via the $customAttributes map, which points to a method on the transformer.
     *      e.g. '@full_name'  →  $this->customAttributes['full_name']  →  $this->getFullName($model)
     *
     * Example:
     *   protected $formats = [
     *       '*'      => ['id', 'name', '@full_name', '!internal_notes'],
     *       'show'   => ['email', 'created_at', 'address' => ['line1', 'city']],
     *       '_index' => ['id', 'name'],  // returned as-is, no merge with '*'
     *   ];
     *
     * @var array
     */
    protected $formats = [];

    /**
     * Rename props
     *
     * @var array
     */
    protected $renames = [];

    /**
     * Formatters
     *
     * @var array
     */
    protected $formatters = [];

    /**
     * Custom Attributes
     *
     * @var array
     */
    protected $customAttributes = [];

    /**
     * Default Attributes
     *
     * @var array
     */
    protected $defaults = [];

    /**
     * Guarded attributes
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * Entry point for transforming model data into a formatted array.
     *
     * Pass the raw Eloquent result (a Model, Collection, or Paginator) and
     * optionally a format key to use. If no format is given, the format is
     * auto-detected from the current route action via getFormatting().
     *
     * Example:
     *   $transformer->transformData($user);           // auto-detects format from route
     *   $transformer->transformData($user, 'show');   // explicitly uses the 'show' format
     *
     * @param  mixed  $data
     */
    public function transformData($data, ?string $format = null): array
    {
        return $this->formatModel($data, $this->getFormatting($format));
    }

    /**
     * Resolves which format definition to use from the $formats array.
     *
     * Format resolution order:
     *  1. If $formatMethod is provided, use it as the key; otherwise read the
     *     current controller method name from the active Laravel route action.
     *  2. If that key exists in $formats, merge it on top of $formats['*']
     *     (the global/wildcard format) and return the result.
     *  3. If a prefixed version of the key exists (prefix defined in config
     *     devespressoApi.transformers.prefixes.unmerged_format, typically '_'),
     *     return that format standalone — it does NOT merge with $formats['*'].
     *  4. Fall back to $formats['*'] alone, or an empty array if not defined.
     *
     * Example $formats definition in a subclass:
     *   protected $formats = [
     *       '*'     => ['id', 'name'],          // always included
     *       'show'  => ['email', 'created_at'], // merged with * on the show route
     *       '_index' => ['id', 'name'],          // returned as-is, no * merge
     *   ];
     */
    public function getFormatting(?string $formatMethod = null): array
    {
        $method = Str::afterLast(Route::currentRouteAction(), '@');

        $mainFormat = $this->formats['*'] ?? [];

        $requireFormat = $formatMethod ?? $method;

        if (array_key_exists($requireFormat, $this->formats)) {
            return array_merge(
                $mainFormat,
                $this->formats[$requireFormat]
            );
        }
        // If the require format is prefixed by a underscore it wont merge with the main format
        $requireFormat = config('devespressoApi.transformers.prefixes.unmerged_format') . $requireFormat;

        if (array_key_exists($requireFormat, $this->formats)) {
            return $this->formats[$requireFormat];
        }

        return $mainFormat;
    }

    /**
     * Recursively formats a Model, Collection, or Paginator into a plain array.
     *
     * - If $collection is null, returns null.
     * - If $collection is not a Model (i.e. a Collection or Paginator), each item
     *   is formatted individually by calling this method recursively, then the
     *   result is returned as a plain array.
     * - If $collection is a Model, each entry in $attributes is processed:
     *     - Keys starting with the hidden_attributes prefix are skipped entirely.
     *     - Array values are treated as nested relations — the relation is loaded
     *       from the model and formatted recursively.
     *     - Scalar values are treated as column/attribute names, run through
     *       guard checks, custom attribute resolution, formatters, and defaults
     *       before being added to the output under their (possibly renamed) key.
     *
     * $currentKey tracks the dot-notation path of the current attribute as the
     * recursion descends into relations, allowing renames/formatters/guards/defaults
     * to be targeted at specific nested paths (e.g. "user.address.city").
     *
     * @param  array  $attributes
     */
    protected function formatModel(Collection|Model|Paginator|null $collection, $attributes, array $currentKey = []): ?array
    {
        if (!$collection) {
            return null;
        }

        if (!$collection instanceof Model) {
            return optional(
                optional($collection)->map(
                    function ($model) use ($attributes, $currentKey) {
                        return $this->formatModel(
                            $model,
                            $attributes,
                            $currentKey
                        );
                    }
                )
            )->toArray();
        }

        $modelFormatted = [];

        foreach ($attributes as $key => $attribute) {
            if (
                Str::startsWith($key, config('devespressoApi.transformers.prefixes.hidden_attributes'))
            ) {
                continue;
            }

            if (is_iterable($attribute)) {
                $renamedKey = $this->renameKey($key, $currentKey);

                $currentKey[] = $key;

                $modelFormatted[$renamedKey] = $this->formatModel(
                    $collection->$key,
                    $attribute,
                    $currentKey
                );

                continue;
            }

            if (
                $this->isAttributeGuarded($attribute, $currentKey, $collection) ||
                Str::startsWith($attribute, config('devespressoApi.transformers.prefixes.hidden_attributes'))
            ) {
                continue;
            }

            // If table is present we should remove it
            $attribute = Str::after($attribute, '.');

            $isCustomAttribute = Str::startsWith(
                $attribute,
                config('devespressoApi.transformers.prefixes.custom_attributes')
            );

            $attribute = Str::replaceFirst(
                config('devespressoApi.transformers.prefixes.custom_attributes'),
                '',
                $attribute
            );

            $modelFormatted[$this->renameKey($attribute, $currentKey)] = $this->applyDefaultValue(
                $this->findFormatters(
                    $attribute,
                    $this->checkForCustomAttributeAndGetValue(
                        $attribute,
                        $collection,
                        $isCustomAttribute
                    ),
                    $currentKey
                ),
                $attribute,
                $currentKey,
                $collection
            );
        }

        return $modelFormatted;
    }

    /**
     * Returns the output key name for an attribute, applying any rename rules.
     *
     * Rename resolution order:
     *  1. If $renames['*'][$attribute] exists, it is a global rename applied to
     *     that attribute name regardless of where it appears in the structure.
     *  2. If $renames['relation.attribute'] exists (dot-notation path built from
     *     $currentKey + $attribute), that path-specific rename is used.
     *  3. Otherwise the original attribute name is returned unchanged.
     *
     * Example $renames definition:
     *   protected $renames = [
     *       '*'              => ['created_at' => 'createdAt'],  // global
     *       'address.line1'  => 'street',                       // path-specific
     *   ];
     */
    protected function renameKey(string $attribute, array $currentKey = []): string
    {
        // If theres nothing to rename we just return the current key
        if (!count($this->renames)) {
            return $attribute;
        }

        // We check if we have any global keys that we need to rename
        if (array_key_exists('*', $this->renames) && array_key_exists($attribute, $this->renames['*'])) {
            return $this->renames['*'][$attribute];
        }

        $currentKey[] = $attribute;

        $uniqueKey = implode('.', $currentKey);

        if (array_key_exists($uniqueKey, $this->renames)) {
            return $this->renames[$uniqueKey];
        }

        return $attribute;
    }

    /**
     * Looks up and applies any formatters registered for the given attribute.
     *
     * Formatter resolution order (both levels can apply to the same value):
     *  1. Global formatters — $formatters['*'][$attribute] is checked first and
     *     applied if found. This allows a formatter to run on an attribute name
     *     regardless of where it appears in the nested structure.
     *  2. Path-specific formatters — $formatters['relation.attribute'] (dot-notation
     *     path built from $currentKey + $attribute) is checked next and applied on
     *     top of any global result.
     *
     * The actual invocation is delegated to runFormatters().
     *
     * Example $formatters definition:
     *   protected $formatters = [
     *       '*'            => ['amount' => 'formatCurrency'],  // global
     *       'user.name'    => ['ucwords'],                     // path-specific
     *   ];
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function findFormatters(string $attribute, $value, array $currentKey = [])
    {
        // If there are nothing in the formatters we just return the value
        if (!count($this->formatters)) {
            return $value;
        }
        // We check global formatters if we have them we run the formatters
        if (array_key_exists('*', $this->formatters) && array_key_exists($attribute, $this->formatters['*'])) {
            $globalFormatters = $this->formatters['*'][$attribute];
            $value = $this->runFormatters($value, $globalFormatters);
        }

        $currentKey[] = $attribute;

        $uniqueKey = implode('.', $currentKey);

        if (!array_key_exists($uniqueKey, $this->formatters)) {
            return $value;
        }

        $formatters = $this->formatters[$uniqueKey];

        return $this->runFormatters($value, $formatters);
    }

    /**
     * Executes one or more formatter methods on a value and returns the result.
     *
     * Accepted $formatters shapes:
     *  - A string: treated as a single method name on this transformer.
     *      e.g. 'formatCurrency' → $this->formatCurrency($value)
     *  - An array of [method => params] pairs:
     *      - If $params is an array and the method exists, it is called with the
     *        params spread as additional arguments:
     *          e.g. ['round' => [2]] → $this->round($value, 2)
     *      - Otherwise $params itself is treated as the method name:
     *          e.g. [0 => 'ucwords'] → $this->ucwords($value)
     *
     * Formatters in an array are applied in order, each receiving the output of
     * the previous one, allowing formatter chaining.
     *
     * @param  mixed  $value
     * @param  mixed  $formatters
     */
    protected function runFormatters($value, $formatters): mixed
    {
        if (is_string($formatters)) {
            return $this->$formatters($value);
        }

        foreach ($formatters as $method => $params) {
            if (is_array($params) && method_exists($this, $method)) {
                $value = $this->$method($value, ...$params);

                continue;
            }

            $value = $this->$params($value);
        }

        return $value;
    }

    /**
     * Retrieves the value for an attribute, handling custom attribute resolution.
     *
     * If $isCustomAttribute is true and the attribute is registered in
     * $customAttributes, the mapped method on this transformer is called with
     * the model as its argument — allowing computed values that don't exist as
     * real database columns. Otherwise the attribute is read directly from the
     * model via property access.
     *
     * Example $customAttributes definition:
     *   protected $customAttributes = [
     *       'full_name' => 'getFullName',  // calls $this->getFullName($model)
     *   ];
     *
     * To use in a format, prefix the attribute with the custom_attributes prefix
     * (configured in devespressoApi.transformers.prefixes.custom_attributes, typically '$'):
     *   protected $formats = ['*' => ['$full_name']];
     *
     * @param  mixed  $model
     */
    protected function checkForCustomAttributeAndGetValue(string $attribute, $model, bool $isCustomAttribute)
    {
        if ($isCustomAttribute && array_key_exists($attribute, $this->customAttributes)) {
            return $this->{$this->customAttributes[$attribute]}($model);
        }

        return $model->$attribute;
    }

    /**
     * Returns a fallback value when an attribute resolves to null.
     *
     * If $value is not null it is returned immediately with no further checks.
     * Default resolution order (first match wins):
     *  1. Global default — $defaults['*'][$attribute]: applies to that attribute
     *     name anywhere in the structure.
     *  2. Path-specific default — $defaults['relation.attribute'] (dot-notation
     *     path built from $currentKey + $attribute).
     *
     * In both cases the default can be either a plain scalar/array value, or a
     * string method name on this transformer that is called with $model to compute
     * the default dynamically.
     *
     * Example $defaults definition:
     *   protected $defaults = [
     *       '*'          => ['status' => 'active'],          // global scalar
     *       'user.score' => 'getDefaultScore',               // path-specific method
     *   ];
     *
     * @param  mixed  $value
     * @param  string  $uniqueKey
     * @return mixed
     */
    public function applyDefaultValue(
        $value,
        string $attribute,
        array $currentKey,
        $model
    ) {
        if (!is_null($value)) {
            return $value;
        }

        // We check global defaults if we have it, set the value
        if (
            array_key_exists('*', $this->defaults) &&
            array_key_exists($attribute, $this->defaults['*'])
        ) {
            $newValue = $this->defaults['*'][$attribute];
            if (is_string($newValue) && method_exists($this, $newValue)) {
                return $this->$newValue($model);
            }

            return $newValue;
        }

        $currentKey[] = $attribute;

        $uniqueKey = implode('.', $currentKey);

        if (array_key_exists($uniqueKey, $this->defaults)) {
            $newValue = $this->defaults[$uniqueKey];
            if (is_string($newValue) && method_exists($this, $newValue)) {
                return $this->$newValue($model);
            }

            return $newValue;
        }

        return $value;
    }

    /**
     * Determines whether an attribute should be excluded from the output.
     *
     * Guard resolution order (first match wins):
     *  1. Global guard — $guarded['*'][$attribute]: if a method name is mapped to
     *     the attribute, it is called with the model. If it returns true the attribute
     *     is hidden regardless of where it appears in the structure.
     *  2. Path-specific guard — $guarded['relation.attribute'] (dot-notation path
     *     built from $currentKey + $attribute): the mapped method is called with
     *     the model and its return value determines visibility.
     *
     * If no matching guard rule is found, returns false (attribute is visible).
     *
     * Example $guarded definition:
     *   protected $guarded = [
     *       '*'           => ['secret' => 'isNotAdmin'],     // global
     *       'user.salary' => 'isNotHrUser',                  // path-specific
     *   ];
     *
     * The mapped value must be a method name on this transformer that accepts the
     * model and returns a boolean.
     */
    protected function isAttributeGuarded(string $attribute, array $currentKey, $model): bool
    {
        if (
            array_key_exists('*', $this->guarded) &&
            array_key_exists($attribute, $this->guarded['*']) &&
            method_exists($this, $this->guarded['*'][$attribute])
        ) {
            return $this->{$this->guarded['*'][$attribute]}($model);
        }

        $currentKey[] = $attribute;

        $uniqueKey = implode('.', $currentKey);

        if (
            array_key_exists($uniqueKey, $this->guarded) &&
            method_exists($this, $this->guarded[$uniqueKey])
        ) {
            return $this->{$this->guarded[$uniqueKey]}($model);
        }

        return false;
    }
}
