<?php

namespace Devespresso\LaravelApiKit\Transformers;

use Illuminate\Contracts\Pagination\CursorPaginator;
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
     *  - Accessor attributes (configured in devespressoApi.transformers.prefixes.accessor_attributes,
     *    default '~') — the column is NOT added to the SELECT query (it is a Laravel model accessor,
     *    not a real database column), but the value is still read from the model and included in the
     *    output via normal property access.
     *      e.g. '~full_name'  →  $model->full_name  (resolved by the model's accessor)
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
     * The version resolved from the last call to resolveVersionedFormats().
     *
     * Null when versioning is disabled or no version was detected. When a
     * version falls back to the full chain (unknown version), this holds the
     * last entry in the versions config, not the raw requested value.
     *
     * @var string|null
     */
    protected ?string $resolvedVersion = null;

    /**
     * Declares the highest version this transformer explicitly supports.
     *
     * When set, any version in the config chain that falls within this boundary
     * but has no corresponding format method will throw a RuntimeException instead
     * of being silently skipped. Versions beyond this value are always skipped.
     *
     * Leave null to skip all missing version methods silently.
     *
     * Example: $latestVersion = 'v3'  — v2Format() and v3Format() must exist.
     *
     * @var string|null
     */
    protected ?string $latestVersion = null;

    /**
     * Versioned property overrides set during the last resolveVersionedFormats() call.
     *
     * These hold the merged result of applying all version-chain property overrides
     * (renames, formatters, guarded, defaults, customAttributes) on top of the base
     * properties. They are reset at the start of each resolveVersionedFormats() call
     * so that the base properties are never mutated by versioning.
     *
     * Consumer methods (renameKey, findFormatters, etc.) read from these when non-null,
     * falling back to the base properties when versioning has not run or is disabled.
     */
    protected ?array $versionedRenames = null;
    protected ?array $versionedFormatters = null;
    protected ?array $versionedGuarded = null;
    protected ?array $versionedDefaults = null;
    protected ?array $versionedCustomAttributes = null;

    /**
     * Entry point for transforming model data into a formatted array.
     *
     * Pass the raw Eloquent result (a Model, Collection, Paginator, or CursorPaginator) and
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
     * Resolves which format definition to use.
     *
     * When versioning is disabled this follows the original resolution order:
     *  1. If $formatMethod is provided use it as the key; otherwise read the
     *     current controller method name from the active Laravel route action.
     *  2. If that key exists in the resolved formats, merge it on top of '*'
     *     and return the result.
     *  3. If a prefixed version of the key exists (prefix defined in config
     *     devespressoApi.transformers.prefixes.unmerged_format, typically '_'),
     *     return that format standalone — it does NOT merge with '*'.
     *  4. Fall back to '*' alone, or an empty array if not defined.
     *
     * When versioning is enabled the resolved formats are first built by
     * applying the version chain (see resolveVersionedFormats()), then the
     * same resolution order above is applied to the result.
     */
    public function getFormatting(?string $formatMethod = null): array
    {
        $formats = $this->resolveVersionedFormats();

        $method = Str::afterLast(Route::currentRouteAction(), '@');

        $mainFormat = $formats['*'] ?? [];

        $requireFormat = $formatMethod ?? $method;

        if (array_key_exists($requireFormat, $formats)) {
            return array_merge($mainFormat, $formats[$requireFormat]);
        }

        $requireFormat = config('devespressoApi.transformers.prefixes.unmerged_format') . $requireFormat;

        if (array_key_exists($requireFormat, $formats)) {
            return $formats[$requireFormat];
        }

        return $mainFormat;
    }

    /**
     * Returns the base format definition used as the starting point for
     * version chain resolution.
     *
     * Override this method instead of setting $formats when using versioning.
     * The default implementation returns $formats for full backward compatibility.
     *
     * Example:
     *   protected function baseFormat(): array
     *   {
     *       return [
     *           '*'    => ['id', 'name', 'status'],
     *           'show' => ['email', 'created_at'],
     *       ];
     *   }
     */
    protected function baseFormat(): array
    {
        return $this->formats;
    }

    /**
     * Resolves the final formats array, applying the version chain when enabled.
     *
     * Accepts an optional $version to bypass auto-detection — useful in tests
     * or when the version is already known at the call site.
     *
     * When versioning is disabled, returns $formats unchanged.
     */
    protected function resolveVersionedFormats(?string $version = null): array
    {
        // Always reset versioned properties — even when versioning is disabled —
        // so that stale state from a previous call never bleeds into the next one.
        $this->versionedRenames          = null;
        $this->versionedFormatters       = null;
        $this->versionedGuarded          = null;
        $this->versionedDefaults         = null;
        $this->versionedCustomAttributes = null;
        $this->resolvedVersion           = null;

        if (!config('devespressoApi.versioning.enabled')) {
            return $this->formats;
        }

        $base = $this->baseFormat();
        $allVersions = config('devespressoApi.versioning.versions', []);
        $detectedVersion = $version ?? $this->detectVersion();
        $chain = $this->resolveVersionChain($detectedVersion, $allVersions);

        $this->resolvedVersion = !empty($chain) ? end($chain) : null;

        return $this->applyVersionChain($base, $chain);
    }

    /**
     * Returns the version that was resolved during the last getFormatting() call.
     *
     * When an unknown version is requested (e.g. 'v445') and the system falls
     * back to the full chain, this returns the last known version ('v3'), not
     * the raw requested value. Returns null when versioning is disabled or no
     * version was detected.
     */
    public function getResolvedVersion(): ?string
    {
        return $this->resolvedVersion;
    }

    /**
     * Detects the active API version from the current request.
     *
     * The detection strategy is controlled by devespressoApi.versioning.driver:
     *  - 'route_prefix' — matches the start of the route URI against the known
     *    versions list (e.g. a route at 'v2/posts' resolves to 'v2').
     *  - 'header'       — reads the value of the configured header name
     *    (devespressoApi.versioning.header, default 'X-Api-Version').
     *
     * Returns null when no version can be detected, which causes getFormatting()
     * to fall back to the base format.
     */
    protected function detectVersion(): ?string
    {
        $driver = config('devespressoApi.versioning.driver', 'route_prefix');

        return match ($driver) {
            'header'       => request()->header(config('devespressoApi.versioning.header', 'X-Api-Version')) ?: null,
            'route_prefix' => $this->detectVersionFromRoutePrefix(),
            default        => throw new \InvalidArgumentException(
                "Unknown versioning driver [{$driver}]. Supported: 'route_prefix', 'header'."
            ),
        };
    }

    /**
     * Attempts to match the current route URI prefix against the known versions.
     *
     * Iterates the versions list and returns the first one whose string appears
     * at the start of the route URI (e.g. 'v2' matches 'v2/posts/{id}').
     * Returns null if no match is found or no route is active.
     */
    protected function detectVersionFromRoutePrefix(): ?string
    {
        $uri = request()->route()?->uri() ?? '';

        if ($uri === '') {
            return null;
        }

        foreach (config('devespressoApi.versioning.versions', []) as $version) {
            if (Str::startsWith($uri, $version . '/') || $uri === $version) {
                return $version;
            }
        }

        return null;
    }

    /**
     * Resolves the ordered list of version methods to apply for a given request.
     *
     * If the requested version is found in the list, the chain is sliced up to
     * and including that version. If it is not found (unknown version), the full
     * chain is returned — falling back to the latest known version. If no version
     * is requested, an empty array is returned and only the base format is used.
     *
     * @param  string[]  $versions  Ordered list of all known versions from config.
     * @return string[]             Ordered subset of versions to apply.
     */
    protected function resolveVersionChain(?string $requestedVersion, array $versions): array
    {
        if (empty($versions) || !$requestedVersion) {
            return [];
        }

        $index = array_search($requestedVersion, $versions);

        return $index !== false
            ? array_slice($versions, 0, $index + 1)
            : $versions; // Unknown version → fall back to full chain (latest)
    }

    /**
     * Walks the version chain and applies each version's changes.
     *
     * For each version in the chain, calls the corresponding method (e.g.
     * v2Format()). If the method does not exist:
     *  - If $latestVersion is set and the version falls within its boundary,
     *    a RuntimeException is thrown — the transformer declared support up
     *    to that version but the method is missing.
     *  - Otherwise the version is silently skipped.
     *
     * Each version method may return any combination of:
     *  - 'merge' => false + 'formats' => [...] — standalone format, replaces all
     *    accumulated formats. Other properties (renames, formatters, etc.) are
     *    still merged cumulatively.
     *  - 'append' / 'remove' — additive/subtractive changes to the format fields.
     *  - 'renames', 'formatters', 'guarded', 'defaults', 'customAttributes' —
     *    merged on top of the transformer's base properties using a deep merge.
     *    Supports both global ('*') and dot-notation path-specific keys. Later
     *    versions override earlier values for the same key.
     *
     * @param  string[]  $versions  Ordered list of versions to apply.
     */
    protected function applyVersionChain(array $formats, array $versions): array
    {
        $allVersions = config('devespressoApi.versioning.versions', []);

        foreach ($versions as $version) {
            $method = $version . 'Format';

            if (!method_exists($this, $method)) {
                if ($this->latestVersion !== null) {
                    $versionIndex = array_search($version, $allVersions);
                    $latestIndex  = array_search($this->latestVersion, $allVersions);

                    if ($latestIndex !== false && $versionIndex !== false && $versionIndex <= $latestIndex) {
                        throw new \RuntimeException(sprintf(
                            'Transformer [%s] declares $latestVersion = \'%s\' but [%s()] is missing. Add the method or update $latestVersion.',
                            static::class,
                            $this->latestVersion,
                            $method
                        ));
                    }
                }

                continue;
            }

            $versionFormat = $this->$method();

            // Fix #3 — validate keys so typos ('appned', 'rename') fail loudly.
            $validKeys   = ['merge', 'formats', 'append', 'remove', 'renames', 'formatters', 'guarded', 'defaults', 'customAttributes'];
            $invalidKeys = array_diff(array_keys($versionFormat), $validKeys);
            if (!empty($invalidKeys)) {
                throw new \InvalidArgumentException(sprintf(
                    '[%s::%s()] contains unknown keys: [%s]. Valid keys are: [%s].',
                    static::class, $method,
                    implode(', ', $invalidKeys),
                    implode(', ', $validKeys)
                ));
            }

            if (isset($versionFormat['merge']) && $versionFormat['merge'] === false && !array_key_exists('formats', $versionFormat)) {
                throw new \InvalidArgumentException(sprintf(
                    '[%s::%s()] sets merge: false but is missing the required \'formats\' key.',
                    static::class, $method
                ));
            }

            // Note: merge: false only resets the accumulated *formats* for this version.
            // Property overrides (renames, formatters, guarded, defaults, customAttributes)
            // always merge cumulatively — they are not affected by merge: false.
            if (isset($versionFormat['merge']) && $versionFormat['merge'] === false) {
                $formats = $versionFormat['formats'];
            } else {
                if (!empty($versionFormat['append'])) {
                    $formats = $this->applyVersionAppends($formats, $versionFormat['append']);
                }

                if (!empty($versionFormat['remove'])) {
                    $formats = $this->applyVersionRemoves($formats, $versionFormat['remove']);
                }
            }

            // Fix #1 — write to separate versioned properties, never mutate the base ones.
            foreach (['renames', 'formatters', 'guarded', 'defaults', 'customAttributes'] as $property) {
                if (!empty($versionFormat[$property])) {
                    $versionedProperty = 'versioned' . ucfirst($property);
                    $this->$versionedProperty = $this->mergeVersionedProperties(
                        $this->$versionedProperty ?? $this->$property,
                        $versionFormat[$property]
                    );
                }
            }
        }

        return $formats;
    }

    /**
     * Recursively merges version property overrides into the base property.
     *
     * When both the base and override values for a key are arrays, the merge
     * recurses — allowing nested keys (e.g. the '*' global bucket or deeper
     * structures) to be extended rather than replaced wholesale.
     *
     * When the override value is a scalar (e.g. a rename target string or a
     * method name), it replaces the existing value for that key. This correctly
     * handles dot-notation path-specific keys such as 'user.name' or 'author.bio'
     * whose values are plain strings.
     *
     * Examples:
     *   base:     ['*' => ['created_at' => 'createdAt'], 'user.name' => 'fullName']
     *   override: ['*' => ['status' => 'userStatus'],    'author.email' => 'authorEmail']
     *   result:   ['*' => ['created_at' => 'createdAt', 'status' => 'userStatus'],
     *              'user.name' => 'fullName', 'author.email' => 'authorEmail']
     */
    protected function mergeVersionedProperties(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (isset($base[$key]) && is_array($base[$key]) && is_array($value)) {
                $base[$key] = $this->mergeVersionedProperties($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    /**
     * Applies append operations to the formats array.
     *
     * Each key in $appends maps to a format key (e.g. '*', 'show'). The items
     * under that key are merged into the matching format entry via appendItems().
     */
    protected function applyVersionAppends(array $formats, array $appends): array
    {
        foreach ($appends as $formatKey => $items) {
            if (!array_key_exists($formatKey, $formats)) {
                $formats[$formatKey] = [];
            }
            $formats[$formatKey] = $this->appendItems($formats[$formatKey], $items);
        }

        return $formats;
    }

    /**
     * Recursively appends items into a format array.
     *
     * Scalar values are appended if not already present. Array values (keyed)
     * are treated as nested relations — if the relation already exists the items
     * are merged recursively; otherwise the whole relation is added as-is.
     */
    protected function appendItems(array $formatArray, array $items): array
    {
        foreach ($items as $key => $value) {
            if (is_array($value)) {
                if (array_key_exists($key, $formatArray)) {
                    $formatArray[$key] = $this->appendItems($formatArray[$key], $value);
                } else {
                    $formatArray[$key] = $value;
                }
            } elseif (!in_array($value, $formatArray, true)) {
                $formatArray[] = $value;
            }
        }

        return $formatArray;
    }

    /**
     * Applies remove operations to the formats array.
     *
     * Each key in $removes maps to a format key (e.g. '*', 'show'). The items
     * under that key are removed from the matching format entry via removeItems().
     */
    protected function applyVersionRemoves(array $formats, array $removes): array
    {
        foreach ($removes as $formatKey => $items) {
            if (!array_key_exists($formatKey, $formats)) {
                continue;
            }
            $formats[$formatKey] = $this->removeItems($formats[$formatKey], $items);
        }

        return $formats;
    }

    /**
     * Recursively removes items from a format array.
     *
     * Scalar values are removed by value, preserving all string-keyed entries
     * (relations) so they are never accidentally dropped. Array values (keyed)
     * are treated as nested relations — the remove is applied recursively.
     */
    protected function removeItems(array $formatArray, array $items): array
    {
        foreach ($items as $key => $value) {
            if (is_array($value)) {
                if (array_key_exists($key, $formatArray)) {
                    $formatArray[$key] = $this->removeItems($formatArray[$key], $value);
                }
            } else {
                $result = [];
                foreach ($formatArray as $k => $v) {
                    if (is_string($k)) {
                        $result[$k] = $v; // always keep relations
                    } elseif ($v !== $value) {
                        $result[] = $v;
                    }
                }
                $formatArray = $result;
            }
        }

        return $formatArray;
    }

    /**
     * Recursively formats a Model, Collection, or Paginator into a plain array.
     *
     * - If $collection is null, returns null.
     * - If $collection is not a Model (i.e. a Collection, Paginator, or CursorPaginator), each item
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
    protected function formatModel(Collection|Model|Paginator|CursorPaginator|null $collection, $attributes, array $currentKey = []): ?array
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

            // Strip accessor prefix — the value is read from the model like a normal
            // attribute, but it is not selected from the database (handled in the filter service).
            $attribute = Str::replaceFirst(
                config('devespressoApi.transformers.prefixes.accessor_attributes'),
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
        $renames = $this->versionedRenames ?? $this->renames;

        // If theres nothing to rename we just return the current key
        if (!count($renames)) {
            return $attribute;
        }

        // We check if we have any global keys that we need to rename
        if (array_key_exists('*', $renames) && array_key_exists($attribute, $renames['*'])) {
            return $renames['*'][$attribute];
        }

        $currentKey[] = $attribute;

        $uniqueKey = implode('.', $currentKey);

        if (array_key_exists($uniqueKey, $renames)) {
            return $renames[$uniqueKey];
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
        $formatters = $this->versionedFormatters ?? $this->formatters;

        // If there are nothing in the formatters we just return the value
        if (!count($formatters)) {
            return $value;
        }
        // We check global formatters if we have them we run the formatters
        if (array_key_exists('*', $formatters) && array_key_exists($attribute, $formatters['*'])) {
            $globalFormatters = $formatters['*'][$attribute];
            $value = $this->runFormatters($value, $globalFormatters);
        }

        $currentKey[] = $attribute;

        $uniqueKey = implode('.', $currentKey);

        if (!array_key_exists($uniqueKey, $formatters)) {
            return $value;
        }

        return $this->runFormatters($value, $formatters[$uniqueKey]);
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
        $customAttributes = $this->versionedCustomAttributes ?? $this->customAttributes;

        if ($isCustomAttribute && array_key_exists($attribute, $customAttributes)) {
            return $this->{$customAttributes[$attribute]}($model);
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

        $defaults = $this->versionedDefaults ?? $this->defaults;

        // We check global defaults if we have it, set the value
        if (
            array_key_exists('*', $defaults) &&
            array_key_exists($attribute, $defaults['*'])
        ) {
            $newValue = $defaults['*'][$attribute];
            if (is_string($newValue) && method_exists($this, $newValue)) {
                return $this->$newValue($model);
            }

            return $newValue;
        }

        $currentKey[] = $attribute;

        $uniqueKey = implode('.', $currentKey);

        if (array_key_exists($uniqueKey, $defaults)) {
            $newValue = $defaults[$uniqueKey];
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
        $guarded = $this->versionedGuarded ?? $this->guarded;

        if (
            array_key_exists('*', $guarded) &&
            array_key_exists($attribute, $guarded['*']) &&
            method_exists($this, $guarded['*'][$attribute])
        ) {
            return $this->{$guarded['*'][$attribute]}($model);
        }

        $currentKey[] = $attribute;

        $uniqueKey = implode('.', $currentKey);

        if (
            array_key_exists($uniqueKey, $guarded) &&
            method_exists($this, $guarded[$uniqueKey])
        ) {
            return $this->{$guarded[$uniqueKey]}($model);
        }

        return false;
    }
}
