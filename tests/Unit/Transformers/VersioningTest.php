<?php

namespace Devespresso\LaravelApiKit\Tests\Unit\Transformers;

use Devespresso\LaravelApiKit\Tests\Fixtures\Models\FakeUser;
use Devespresso\LaravelApiKit\Tests\Fixtures\Transformers\FakeTransformer;
use Devespresso\LaravelApiKit\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;

class VersioningTest extends TestCase
{
    private FakeTransformer $transformer;

    private array $baseFormats = [
        '*'    => ['id', 'name', 'status'],
        'show' => ['email', 'created_at'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['config']->set('devespressoApi.versioning.enabled', true);
        $this->app['config']->set('devespressoApi.versioning.versions', ['v2', 'v3']);
        $this->transformer = new FakeTransformer();
        $this->transformer->formats = $this->baseFormats;
    }

    // -------------------------------------------------------------------------
    // baseFormat / disabled
    // -------------------------------------------------------------------------

    public function test_versioning_disabled_returns_formats_property_unchanged(): void
    {
        $this->app['config']->set('devespressoApi.versioning.enabled', false);

        $result = $this->transformer->callResolveVersionedFormats('v2');

        $this->assertSame($this->baseFormats, $result);
    }

    public function test_no_version_returns_base_formats(): void
    {
        $result = $this->transformer->callResolveVersionedFormats(null);

        $this->assertSame($this->baseFormats, $result);
    }

    // -------------------------------------------------------------------------
    // resolveVersionChain
    // -------------------------------------------------------------------------

    public function test_known_version_slices_chain_up_to_and_including_it(): void
    {
        $chain = $this->transformer->callResolveVersionChain('v2', ['v2', 'v3']);

        $this->assertSame(['v2'], $chain);
    }

    public function test_last_version_returns_full_chain(): void
    {
        $chain = $this->transformer->callResolveVersionChain('v3', ['v2', 'v3']);

        $this->assertSame(['v2', 'v3'], $chain);
    }

    public function test_unknown_version_falls_back_to_full_chain(): void
    {
        $chain = $this->transformer->callResolveVersionChain('v445', ['v2', 'v3']);

        $this->assertSame(['v2', 'v3'], $chain);
    }

    public function test_null_version_returns_empty_chain(): void
    {
        $chain = $this->transformer->callResolveVersionChain(null, ['v2', 'v3']);

        $this->assertSame([], $chain);
    }

    public function test_empty_versions_config_returns_empty_chain(): void
    {
        $chain = $this->transformer->callResolveVersionChain('v2', []);

        $this->assertSame([], $chain);
    }

    // -------------------------------------------------------------------------
    // Append — flat
    // -------------------------------------------------------------------------

    public function test_v2_appends_fields_to_wildcard_format(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['append' => ['*' => ['avatar']]];
            }
        };
        $transformer->formats = $this->baseFormats;

        $result = $transformer->callResolveVersionedFormats('v2');

        $this->assertContains('avatar', $result['*']);
        $this->assertContains('id', $result['*']);
    }

    public function test_append_does_not_duplicate_existing_field(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['append' => ['*' => ['id']]]; // 'id' already in base
            }
        };
        $transformer->formats = $this->baseFormats;

        $result = $transformer->callResolveVersionedFormats('v2');

        $this->assertSame(1, count(array_keys($result['*'], 'id')));
    }

    public function test_append_creates_new_format_key_if_missing(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['append' => ['update' => ['title']]];
            }
        };
        $transformer->formats = $this->baseFormats;

        $result = $transformer->callResolveVersionedFormats('v2');

        $this->assertArrayHasKey('update', $result);
        $this->assertContains('title', $result['update']);
    }

    // -------------------------------------------------------------------------
    // Remove — flat
    // -------------------------------------------------------------------------

    public function test_v2_removes_field_from_wildcard_format(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['remove' => ['*' => ['status']]];
            }
        };
        $transformer->formats = $this->baseFormats;

        $result = $transformer->callResolveVersionedFormats('v2');

        $this->assertNotContains('status', $result['*']);
        $this->assertContains('id', $result['*']);
        $this->assertContains('name', $result['*']);
    }

    public function test_remove_on_missing_format_key_is_silently_skipped(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['remove' => ['nonexistent' => ['foo']]];
            }
        };
        $transformer->formats = $this->baseFormats;

        $result = $transformer->callResolveVersionedFormats('v2');

        $this->assertSame($this->baseFormats, $result);
    }

    // -------------------------------------------------------------------------
    // Nested append / remove
    // -------------------------------------------------------------------------

    public function test_nested_append_adds_field_to_existing_relation(): void
    {
        $base = [
            '*' => ['id', 'name', 'author' => ['id', 'name', 'email']],
        ];
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['append' => ['*' => ['author' => ['bio']]]];
            }
        };
        $transformer->formats = $base;

        $result = $transformer->callResolveVersionedFormats('v2');

        $this->assertContains('bio', $result['*']['author']);
        $this->assertContains('email', $result['*']['author']);
    }

    public function test_nested_append_adds_new_relation_if_not_present(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['append' => ['*' => ['tags' => ['id', 'label']]]];
            }
        };
        $transformer->formats = $this->baseFormats;

        $result = $transformer->callResolveVersionedFormats('v2');

        $this->assertArrayHasKey('tags', $result['*']);
        $this->assertSame(['id', 'label'], $result['*']['tags']);
    }

    public function test_nested_remove_removes_field_from_existing_relation(): void
    {
        $base = [
            '*' => ['id', 'name', 'author' => ['id', 'name', 'email']],
        ];
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['remove' => ['*' => ['author' => ['email']]]];
            }
        };
        $transformer->formats = $base;

        $result = $transformer->callResolveVersionedFormats('v2');

        $this->assertNotContains('email', $result['*']['author']);
        $this->assertContains('id', $result['*']['author']);
        $this->assertContains('name', $result['*']['author']);
    }

    public function test_deeply_nested_append_recurses_correctly(): void
    {
        $base = [
            '*' => ['author' => ['address' => ['line1', 'city']]],
        ];
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['append' => ['*' => ['author' => ['address' => ['postcode']]]]];
            }
        };
        $transformer->formats = $base;

        $result = $transformer->callResolveVersionedFormats('v2');

        $this->assertContains('postcode', $result['*']['author']['address']);
        $this->assertContains('city', $result['*']['author']['address']);
    }

    // -------------------------------------------------------------------------
    // Cumulative chain
    // -------------------------------------------------------------------------

    public function test_v3_chain_builds_on_top_of_v2(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return [
                    'append' => ['*' => ['avatar']],
                    'remove' => ['*' => ['status']],
                ];
            }

            public function v3Format(): array
            {
                return ['append' => ['*' => ['verified_at']]];
            }
        };
        $transformer->formats = $this->baseFormats;

        $result = $transformer->callResolveVersionedFormats('v3');

        $this->assertContains('avatar', $result['*']);
        $this->assertContains('verified_at', $result['*']);
        $this->assertNotContains('status', $result['*']);
    }

    public function test_v2_request_does_not_apply_v3_changes(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['append' => ['*' => ['avatar']]];
            }

            public function v3Format(): array
            {
                return ['append' => ['*' => ['verified_at']]];
            }
        };
        $transformer->formats = $this->baseFormats;

        $result = $transformer->callResolveVersionedFormats('v2');

        $this->assertContains('avatar', $result['*']);
        $this->assertNotContains('verified_at', $result['*']);
    }

    // -------------------------------------------------------------------------
    // merge: false (standalone)
    // -------------------------------------------------------------------------

    public function test_standalone_version_replaces_accumulated_formats(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return [
                    'merge'   => false,
                    'formats' => [
                        '*' => ['id', 'avatar'],
                    ],
                ];
            }
        };
        $transformer->formats = $this->baseFormats;

        $result = $transformer->callResolveVersionedFormats('v2');

        $this->assertSame(['id', 'avatar'], $result['*']);
        $this->assertArrayNotHasKey('show', $result);
    }

    public function test_version_after_standalone_builds_on_standalone_not_base(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return [
                    'merge'   => false,
                    'formats' => ['*' => ['id', 'avatar']],
                ];
            }

            public function v3Format(): array
            {
                return ['append' => ['*' => ['verified_at']]];
            }
        };
        $transformer->formats = $this->baseFormats;

        $result = $transformer->callResolveVersionedFormats('v3');

        $this->assertContains('id', $result['*']);
        $this->assertContains('avatar', $result['*']);
        $this->assertContains('verified_at', $result['*']);
        $this->assertNotContains('name', $result['*']); // was in base, wiped by standalone
    }

    // -------------------------------------------------------------------------
    // $latestVersion — strict mode
    // -------------------------------------------------------------------------

    public function test_throws_when_method_missing_within_latest_version_boundary(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/v2Format\(\)/');

        $transformer = new class extends FakeTransformer {
            // v2Format() intentionally absent
        };
        $transformer->formats      = $this->baseFormats;
        $transformer->latestVersion = 'v2';

        $transformer->callResolveVersionedFormats('v2');
    }

    public function test_skips_silently_when_method_missing_and_no_latest_version(): void
    {
        $transformer = new class extends FakeTransformer {
            // v2Format() intentionally absent
        };
        $transformer->formats = $this->baseFormats;

        $result = $transformer->callResolveVersionedFormats('v2');

        $this->assertSame($this->baseFormats, $result);
    }

    public function test_skips_silently_when_method_missing_beyond_latest_version(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['append' => ['*' => ['avatar']]];
            }
            // v3Format() intentionally absent — beyond latestVersion = 'v2'
        };
        $transformer->formats      = $this->baseFormats;
        $transformer->latestVersion = 'v2';

        // v3 is beyond latestVersion so missing v3Format() should not throw
        $result = $transformer->callResolveVersionedFormats('v3');

        $this->assertContains('avatar', $result['*']);
    }

    // -------------------------------------------------------------------------
    // getResolvedVersion
    // -------------------------------------------------------------------------

    public function test_resolved_version_is_set_after_format_resolution(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['append' => ['*' => ['avatar']]];
            }
        };
        $transformer->formats = $this->baseFormats;

        $transformer->callResolveVersionedFormats('v2');

        $this->assertSame('v2', $transformer->getResolvedVersion());
    }

    public function test_resolved_version_reflects_latest_when_unknown_version_requested(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array { return []; }
            public function v3Format(): array { return []; }
        };
        $transformer->formats = $this->baseFormats;

        $transformer->callResolveVersionedFormats('v445');

        $this->assertSame('v3', $transformer->getResolvedVersion());
    }

    public function test_resolved_version_is_null_when_no_version_detected(): void
    {
        $transformer = new class extends FakeTransformer {};
        $transformer->formats = $this->baseFormats;

        $transformer->callResolveVersionedFormats(null);

        $this->assertNull($transformer->getResolvedVersion());
    }

    public function test_resolved_version_is_null_when_versioning_disabled(): void
    {
        $this->app['config']->set('devespressoApi.versioning.enabled', false);

        $transformer = new class extends FakeTransformer {};
        $transformer->formats = $this->baseFormats;

        $transformer->callResolveVersionedFormats('v2');

        $this->assertNull($transformer->getResolvedVersion());
    }

    // -------------------------------------------------------------------------
    // Header driver
    // -------------------------------------------------------------------------

    public function test_header_driver_reads_version_from_request_header(): void
    {
        $this->app['config']->set('devespressoApi.versioning.driver', 'header');

        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['append' => ['*' => ['avatar']]];
            }
        };
        $transformer->formats = $this->baseFormats;

        $this->app['request']->headers->set('X-Api-Version', 'v2');

        $result = $transformer->callResolveVersionedFormats();

        $this->assertContains('avatar', $result['*']);
    }

    public function test_missing_header_falls_back_to_base(): void
    {
        $this->app['config']->set('devespressoApi.versioning.driver', 'header');

        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['append' => ['*' => ['avatar']]];
            }
        };
        $transformer->formats = $this->baseFormats;

        // No header set
        $result = $transformer->callResolveVersionedFormats();

        $this->assertNotContains('avatar', $result['*']);
    }

    public function test_unknown_driver_throws_invalid_argument_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown versioning driver/');

        $this->app['config']->set('devespressoApi.versioning.driver', 'magic');

        $transformer = new FakeTransformer();
        $transformer->formats = $this->baseFormats;

        $transformer->callResolveVersionedFormats(); // triggers detectVersion()
    }

    public function test_route_prefix_driver_matches_uri_that_is_exactly_the_version(): void
    {
        $this->app['config']->set('devespressoApi.versioning.driver', 'route_prefix');

        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['append' => ['*' => ['avatar']]];
            }
        };
        $transformer->formats = $this->baseFormats;

        // Simulate a route whose URI is exactly 'v2' (no trailing path)
        $request = \Illuminate\Http\Request::create('/v2', 'GET');
        $route   = new \Illuminate\Routing\Route('GET', 'v2', []);
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);
        app()->instance('request', $request);

        $result = $transformer->callResolveVersionedFormats();

        $this->assertContains('avatar', $result['*']);
    }

    // -------------------------------------------------------------------------
    // Property overrides — renames
    // -------------------------------------------------------------------------

    public function test_v2_merges_global_renames_with_base(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['renames' => ['*' => ['name' => 'fullName']]];
            }
        };
        $transformer->formats = $this->baseFormats;
        $transformer->renames = ['*' => ['created_at' => 'createdAt']];

        $transformer->callResolveVersionedFormats('v2');

        $this->assertSame('createdAt', $transformer->versionedRenames['*']['created_at']);
        $this->assertSame('fullName', $transformer->versionedRenames['*']['name']);
        // Base property must NOT be mutated
        $this->assertArrayNotHasKey('name', $transformer->renames['*']);
    }

    public function test_v2_merges_dot_notation_renames(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['renames' => ['author.email' => 'authorEmail']];
            }
        };
        $transformer->formats = $this->baseFormats;
        $transformer->renames = ['user.name' => 'fullName'];

        $transformer->callResolveVersionedFormats('v2');

        $this->assertSame('fullName', $transformer->versionedRenames['user.name']);
        $this->assertSame('authorEmail', $transformer->versionedRenames['author.email']);
        // Base property must NOT be mutated
        $this->assertArrayNotHasKey('author.email', $transformer->renames);
    }

    public function test_later_version_overrides_earlier_rename_for_same_key(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['renames' => ['user.name' => 'v2Name']];
            }

            public function v3Format(): array
            {
                return ['renames' => ['user.name' => 'v3Name']];
            }
        };
        $transformer->formats = $this->baseFormats;
        $transformer->renames = [];

        $transformer->callResolveVersionedFormats('v3');

        $this->assertSame('v3Name', $transformer->versionedRenames['user.name']);
    }

    // -------------------------------------------------------------------------
    // Property overrides — formatters
    // -------------------------------------------------------------------------

    public function test_v2_merges_global_formatters_with_base(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['formatters' => ['*' => ['status' => 'toUpper']]];
            }
        };
        $transformer->formats    = $this->baseFormats;
        $transformer->formatters = ['*' => ['name' => 'toUpper']];

        $transformer->callResolveVersionedFormats('v2');

        $this->assertArrayHasKey('name', $transformer->versionedFormatters['*']);
        $this->assertArrayHasKey('status', $transformer->versionedFormatters['*']);
        // Base property must NOT be mutated
        $this->assertArrayNotHasKey('status', $transformer->formatters['*']);
    }

    public function test_v2_merges_dot_notation_formatters(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['formatters' => ['author.bio' => ['toUpper']]];
            }
        };
        $transformer->formats    = $this->baseFormats;
        $transformer->formatters = ['user.name' => ['toUpper']];

        $transformer->callResolveVersionedFormats('v2');

        $this->assertArrayHasKey('user.name', $transformer->versionedFormatters);
        $this->assertArrayHasKey('author.bio', $transformer->versionedFormatters);
        // Base property must NOT be mutated
        $this->assertArrayNotHasKey('author.bio', $transformer->formatters);
    }

    // -------------------------------------------------------------------------
    // Property overrides — guarded
    // -------------------------------------------------------------------------

    public function test_v2_merges_global_guards_with_base(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['guarded' => ['*' => ['salary' => 'alwaysGuard']]];
            }
        };
        $transformer->formats = $this->baseFormats;
        $transformer->guarded = ['*' => ['secret' => 'alwaysGuard']];

        $transformer->callResolveVersionedFormats('v2');

        $this->assertArrayHasKey('secret', $transformer->versionedGuarded['*']);
        $this->assertArrayHasKey('salary', $transformer->versionedGuarded['*']);
        // Base property must NOT be mutated
        $this->assertArrayNotHasKey('salary', $transformer->guarded['*']);
    }

    public function test_v2_merges_dot_notation_guards(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['guarded' => ['author.salary' => 'alwaysGuard']];
            }
        };
        $transformer->formats = $this->baseFormats;
        $transformer->guarded = ['user.secret' => 'alwaysGuard'];

        $transformer->callResolveVersionedFormats('v2');

        $this->assertArrayHasKey('user.secret', $transformer->versionedGuarded);
        $this->assertArrayHasKey('author.salary', $transformer->versionedGuarded);
        // Base property must NOT be mutated
        $this->assertArrayNotHasKey('author.salary', $transformer->guarded);
    }

    // -------------------------------------------------------------------------
    // Property overrides — defaults
    // -------------------------------------------------------------------------

    public function test_v2_merges_defaults_with_base(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['defaults' => ['*' => ['avatar' => 'getDefaultAvatar']]];
            }
        };
        $transformer->formats  = $this->baseFormats;
        $transformer->defaults = ['*' => ['status' => 'active']];

        $transformer->callResolveVersionedFormats('v2');

        $this->assertSame('active', $transformer->versionedDefaults['*']['status']);
        $this->assertSame('getDefaultAvatar', $transformer->versionedDefaults['*']['avatar']);
        // Base property must NOT be mutated
        $this->assertArrayNotHasKey('avatar', $transformer->defaults['*']);
    }

    public function test_v2_merges_dot_notation_defaults(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['defaults' => ['author.bio' => 'getDefaultBio']];
            }
        };
        $transformer->formats  = $this->baseFormats;
        $transformer->defaults = ['user.score' => 'getDefaultScore'];

        $transformer->callResolveVersionedFormats('v2');

        $this->assertArrayHasKey('user.score', $transformer->versionedDefaults);
        $this->assertArrayHasKey('author.bio', $transformer->versionedDefaults);
        // Base property must NOT be mutated
        $this->assertArrayNotHasKey('author.bio', $transformer->defaults);
    }

    // -------------------------------------------------------------------------
    // Property overrides — customAttributes
    // -------------------------------------------------------------------------

    public function test_v2_merges_custom_attributes_with_base(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['customAttributes' => ['avatar_url' => 'getAvatarUrl']];
            }
        };
        $transformer->formats          = $this->baseFormats;
        $transformer->customAttributes = ['full_name' => 'getFullName'];

        $transformer->callResolveVersionedFormats('v2');

        $this->assertArrayHasKey('full_name', $transformer->versionedCustomAttributes);
        $this->assertArrayHasKey('avatar_url', $transformer->versionedCustomAttributes);
        $this->assertSame('getAvatarUrl', $transformer->versionedCustomAttributes['avatar_url']);
        // Base property must NOT be mutated
        $this->assertArrayNotHasKey('avatar_url', $transformer->customAttributes);
    }

    // -------------------------------------------------------------------------
    // Property overrides — cumulative chain
    // -------------------------------------------------------------------------

    public function test_property_overrides_accumulate_across_versions(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['renames' => ['*' => ['name' => 'fullName']]];
            }

            public function v3Format(): array
            {
                return ['renames' => ['*' => ['status' => 'userStatus']]];
            }
        };
        $transformer->formats = $this->baseFormats;
        $transformer->renames = [];

        $transformer->callResolveVersionedFormats('v3');

        $this->assertSame('fullName', $transformer->versionedRenames['*']['name']);
        $this->assertSame('userStatus', $transformer->versionedRenames['*']['status']);
    }

    public function test_standalone_version_still_applies_property_overrides(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return [
                    'merge'   => false,
                    'formats' => ['*' => ['id']],
                    'renames' => ['*' => ['id' => 'userId']],
                ];
            }
        };
        $transformer->formats = $this->baseFormats;
        $transformer->renames = [];

        $transformer->callResolveVersionedFormats('v2');

        $this->assertSame('userId', $transformer->versionedRenames['*']['id']);
    }

    // -------------------------------------------------------------------------
    // Idempotency — repeated calls on the same instance must not double-apply
    // -------------------------------------------------------------------------

    public function test_versioned_state_is_cleared_when_versioning_is_disabled_between_calls(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['renames' => ['*' => ['name' => 'fullName']]];
            }
        };
        $transformer->formats = $this->baseFormats;
        $transformer->renames = [];

        // First call — versioning on, v2 renames are applied
        $transformer->callResolveVersionedFormats('v2');
        $this->assertSame('fullName', $transformer->versionedRenames['*']['name']);

        // Second call — versioning disabled, stale versioned state must be cleared
        $this->app['config']->set('devespressoApi.versioning.enabled', false);
        $transformer->callResolveVersionedFormats('v2');

        $this->assertNull($transformer->versionedRenames);
        $this->assertNull($transformer->getResolvedVersion());
    }

    public function test_calling_resolve_twice_does_not_double_apply_renames(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['renames' => ['*' => ['name' => 'fullName']]];
            }
        };
        $transformer->formats = $this->baseFormats;
        $transformer->renames = ['*' => ['created_at' => 'createdAt']];

        $transformer->callResolveVersionedFormats('v2');
        $transformer->callResolveVersionedFormats('v2'); // second call — must yield same result

        // Only one entry per key — not duplicated or compounded
        $this->assertCount(2, $transformer->versionedRenames['*']); // created_at + name
        $this->assertSame('createdAt', $transformer->versionedRenames['*']['created_at']);
        $this->assertSame('fullName', $transformer->versionedRenames['*']['name']);

        // Base property still untouched after two calls
        $this->assertArrayNotHasKey('name', $transformer->renames['*']);
    }

    public function test_calling_resolve_with_different_versions_does_not_bleed_state(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['renames' => ['*' => ['name' => 'fullName']]];
            }

            public function v3Format(): array
            {
                return ['renames' => ['*' => ['status' => 'userStatus']]];
            }
        };
        $transformer->formats = $this->baseFormats;
        $transformer->renames = [];

        // First call resolves v3 — applies v2 + v3
        $transformer->callResolveVersionedFormats('v3');
        $this->assertArrayHasKey('name', $transformer->versionedRenames['*']);
        $this->assertArrayHasKey('status', $transformer->versionedRenames['*']);

        // Second call resolves v2 only — must NOT still contain v3's status rename
        $transformer->callResolveVersionedFormats('v2');
        $this->assertArrayHasKey('name', $transformer->versionedRenames['*']);
        $this->assertArrayNotHasKey('status', $transformer->versionedRenames['*']);
    }

    // -------------------------------------------------------------------------
    // mergeVersionedProperties — edge cases
    // -------------------------------------------------------------------------

    public function test_merge_adds_new_key_from_override(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['renames' => ['new_key' => 'newValue']];
            }
        };
        $transformer->formats = $this->baseFormats;
        $transformer->renames = ['existing' => 'existingValue'];

        $transformer->callResolveVersionedFormats('v2');

        $this->assertSame('existingValue', $transformer->versionedRenames['existing']);
        $this->assertSame('newValue', $transformer->versionedRenames['new_key']);
    }

    public function test_merge_scalar_overrides_existing_scalar_for_same_key(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['renames' => ['user.name' => 'v2Name']];
            }
        };
        $transformer->formats = $this->baseFormats;
        $transformer->renames = ['user.name' => 'originalName'];

        $transformer->callResolveVersionedFormats('v2');

        $this->assertSame('v2Name', $transformer->versionedRenames['user.name']);
        // Base property must NOT be mutated
        $this->assertSame('originalName', $transformer->renames['user.name']);
    }

    public function test_merge_nested_array_is_merged_not_replaced(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['renames' => ['*' => ['status' => 'userStatus']]];
            }
        };
        $transformer->formats = $this->baseFormats;
        $transformer->renames = ['*' => ['created_at' => 'createdAt']];

        $transformer->callResolveVersionedFormats('v2');

        // Both keys must survive — merge, not replace
        $this->assertSame('createdAt', $transformer->versionedRenames['*']['created_at']);
        $this->assertSame('userStatus', $transformer->versionedRenames['*']['status']);
        // Base property must NOT be mutated
        $this->assertArrayNotHasKey('status', $transformer->renames['*']);
    }

    // -------------------------------------------------------------------------
    // Key validation (Fix #3)
    // -------------------------------------------------------------------------

    public function test_unknown_key_in_version_method_throws_invalid_argument_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/unknown keys.*appned/');

        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['appned' => ['*' => ['avatar']]]; // typo: 'appned' instead of 'append'
            }
        };
        $transformer->formats = $this->baseFormats;

        $transformer->callResolveVersionedFormats('v2');
    }

    public function test_multiple_unknown_keys_are_all_listed_in_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/unknown keys/');

        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['appned' => [], 'rename' => []]; // two typos
            }
        };
        $transformer->formats = $this->baseFormats;

        $transformer->callResolveVersionedFormats('v2');
    }

    public function test_merge_false_without_formats_key_throws_invalid_argument_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/merge: false.*missing.*formats/');

        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['merge' => false]; // missing 'formats'
            }
        };
        $transformer->formats = $this->baseFormats;

        $transformer->callResolveVersionedFormats('v2');
    }

    public function test_empty_version_method_return_is_a_no_op(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return []; // nothing to do this version
            }
        };
        $transformer->formats = $this->baseFormats;

        $result = $transformer->callResolveVersionedFormats('v2');

        // Formats unchanged, no exception thrown
        $this->assertSame($this->baseFormats, $result);
        $this->assertNull($transformer->versionedRenames);
    }

    public function test_all_valid_keys_do_not_throw(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return [
                    'append'           => ['*' => ['avatar']],
                    'remove'           => ['*' => ['status']],
                    'renames'          => ['*' => ['name' => 'fullName']],
                    'formatters'       => [],
                    'guarded'          => [],
                    'defaults'         => [],
                    'customAttributes' => [],
                ];
            }
        };
        $transformer->formats = $this->baseFormats;

        // Must not throw
        $result = $transformer->callResolveVersionedFormats('v2');

        $this->assertContains('avatar', $result['*']);
        $this->assertNotContains('status', $result['*']);
    }

    // -------------------------------------------------------------------------
    // End-to-end: formatModel output with versioned properties
    // -------------------------------------------------------------------------

    public function test_versioned_rename_is_applied_in_format_model_output(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['renames' => ['*' => ['name' => 'fullName']]];
            }
        };
        $transformer->formats = $this->baseFormats;

        $format = $transformer->callResolveVersionedFormats('v2');
        $model  = (new FakeUser())->forceFill(['id' => 1, 'name' => 'Alice', 'status' => 'active']);
        $result = $transformer->callFormatModel($model, $format['*']);

        $this->assertArrayHasKey('fullName', $result);
        $this->assertArrayNotHasKey('name', $result);
        $this->assertSame('Alice', $result['fullName']);
    }

    public function test_versioned_dot_notation_rename_applies_in_nested_relation(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['renames' => ['author.name' => 'authorName']];
            }
        };
        $transformer->formats = ['*' => ['id', 'author' => ['name']]];

        $format = $transformer->callResolveVersionedFormats('v2');
        $author = (new class extends Model {
            protected $table = 'fake';
            public $timestamps = false;
        })->forceFill(['name' => 'Bob']);
        $model = (new FakeUser())->forceFill(['id' => 1]);
        $model->setRelation('author', $author);

        $result = $transformer->callFormatModel($model, $format['*']);

        $this->assertArrayHasKey('authorName', $result['author']);
        $this->assertArrayNotHasKey('name', $result['author']);
        $this->assertSame('Bob', $result['author']['authorName']);
    }

    public function test_versioned_formatter_is_applied_in_format_model_output(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['formatters' => ['*' => ['name' => 'toUpper']]];
            }
        };
        $transformer->formats = $this->baseFormats;

        $format = $transformer->callResolveVersionedFormats('v2');
        $model  = (new FakeUser())->forceFill(['id' => 1, 'name' => 'Alice', 'status' => 'active']);
        $result = $transformer->callFormatModel($model, $format['*']);

        $this->assertSame('ALICE', $result['name']);
    }

    public function test_versioned_dot_notation_formatter_applies_in_nested_relation(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['formatters' => ['author.name' => ['toUpper']]];
            }
        };
        $transformer->formats = ['*' => ['id', 'author' => ['name']]];

        $format = $transformer->callResolveVersionedFormats('v2');
        $author = (new class extends Model {
            protected $table = 'fake';
            public $timestamps = false;
        })->forceFill(['name' => 'Bob']);
        $model = (new FakeUser())->forceFill(['id' => 1]);
        $model->setRelation('author', $author);

        $result = $transformer->callFormatModel($model, $format['*']);

        $this->assertSame('BOB', $result['author']['name']);
    }

    public function test_versioned_guard_hides_attribute_in_format_model_output(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['guarded' => ['*' => ['status' => 'alwaysGuard']]];
            }
        };
        $transformer->formats = $this->baseFormats;

        $format = $transformer->callResolveVersionedFormats('v2');
        $model  = (new FakeUser())->forceFill(['id' => 1, 'name' => 'Alice', 'status' => 'active']);
        $result = $transformer->callFormatModel($model, $format['*']);

        $this->assertArrayNotHasKey('status', $result);
        $this->assertArrayHasKey('name', $result);
    }

    public function test_versioned_default_is_applied_when_attribute_is_null(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return ['defaults' => ['*' => ['status' => 'pending']]];
            }
        };
        $transformer->formats = $this->baseFormats;

        $format = $transformer->callResolveVersionedFormats('v2');
        $model  = (new FakeUser())->forceFill(['id' => 1, 'name' => 'Alice', 'status' => null]);
        $result = $transformer->callFormatModel($model, $format['*']);

        $this->assertSame('pending', $result['status']);
    }

    public function test_versioned_custom_attribute_is_resolved_in_format_model_output(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return [
                    'append'           => ['*' => ['@greeting']],
                    'customAttributes' => ['greeting' => 'getGreeting'],
                ];
            }

            public function getGreeting($model): string
            {
                return 'Hello ' . $model->name;
            }
        };
        $transformer->formats = $this->baseFormats;

        $format = $transformer->callResolveVersionedFormats('v2');
        $model  = (new FakeUser())->forceFill(['id' => 1, 'name' => 'Alice', 'status' => 'active']);
        $result = $transformer->callFormatModel($model, $format['*']);

        $this->assertArrayHasKey('greeting', $result);
        $this->assertSame('Hello Alice', $result['greeting']);
    }

    public function test_full_version_chain_property_overrides_flow_through_format_model(): void
    {
        $transformer = new class extends FakeTransformer {
            public function v2Format(): array
            {
                return [
                    'append'     => ['*' => ['status']],
                    'renames'    => ['*' => ['name' => 'fullName']],
                    'formatters' => ['*' => ['status' => 'toUpper']],
                ];
            }

            public function v3Format(): array
            {
                return [
                    'guarded'  => ['*' => ['status' => 'alwaysGuard']],
                    'defaults' => ['*' => ['name' => 'Anonymous']],
                ];
            }
        };
        $transformer->formats = ['*' => ['id', 'name']];

        $format = $transformer->callResolveVersionedFormats('v3');
        $model  = (new FakeUser())->forceFill(['id' => 1, 'name' => null, 'status' => 'active']);
        $result = $transformer->callFormatModel($model, $format['*']);

        // v2 rename applied
        $this->assertArrayHasKey('fullName', $result);
        $this->assertArrayNotHasKey('name', $result);
        // v3 default filled the null name before rename
        $this->assertSame('Anonymous', $result['fullName']);
        // v3 guard hid status despite v2 appending it
        $this->assertArrayNotHasKey('status', $result);
    }
}
