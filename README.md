# Laravel API Kit

A Laravel package that provides a complete data filtering, transformation, and API response system. Drop it into any Laravel application to get automatic query filtering, model transformation, pagination, sorting, authorisation, and CRUD repositories — all driven by simple class conventions.

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12

## Installation

```bash
composer require devespresso/laravel-api-kit
```

Publish the config file:

```bash
php artisan vendor:publish --provider="Devespresso\LaravelApiKit\LaravelApiKitServiceProvider"
```

## Configuration

Publish the config file to `config/devespressoApi.php`:

```bash
php artisan vendor:publish --provider="Devespresso\LaravelApiKit\LaravelApiKitServiceProvider"
```

---

### `pagination.with_pages`

Controls the default pagination method used when no `pagination_type` is passed in the request.

```php
'pagination' => [
    'with_pages' => false, // false = simplePaginate() | true = paginate() (includes total count)
],
```

---

### `paths`

Namespaces used to auto-resolve models, transformers, and repositories from class names. Change these if your project uses a non-standard structure.

```php
'paths' => [
    'models'       => 'App\\Models\\',        // UserRepository → App\Models\User
    'transformers' => 'App\\Transformers\\',  // UserController → App\Transformers\UserTransformer
    'repositories' => 'App\\Repositories\\', // UserController → App\Repositories\UserRepository
],
```

---

### `auto_select`

When `true`, the filter service reads the active transformer format and automatically adds a `SELECT` clause to the query — only fetching columns that are actually needed. Prevents `SELECT *` without any manual effort.

```php
'auto_select' => true,
```

Set to `false` to let Eloquent fall back to `SELECT *`, or when you need full manual control over selected columns.

---

### `auto_eager_load`

When `true`, any relation defined as a nested array in the transformer format is automatically eager-loaded with its own scoped `SELECT`. Eliminates N+1 queries without writing `->with()` manually.

```php
'auto_eager_load' => true,
```

Set to `false` to manage eager loading manually in your filter service or controller.

---

### `transformers.prefixes`

Single-character prefixes used in transformer `$formats` arrays to control how attributes are treated. All three are fully configurable — if any clash with your attribute names, change them here and the entire package will use your values automatically.

```php
'transformers' => [
    'prefixes' => [
        'hidden_attributes' => '!', // selected from DB but excluded from the JSON response
        'custom_attributes' => '@', // computed via a transformer method, not read from the DB
        'unmerged_format'   => '_', // format key that is returned as-is, not merged with '*'
    ],
],
```

| Key | Default | Effect |
|---|---|---|
| `hidden_attributes` | `!` | Attribute is SELECTed but stripped from the response |
| `custom_attributes` | `@` | Attribute value is resolved via `$customAttributes` map |
| `unmerged_format` | `_` | Format key is not merged with the `*` wildcard format |

---

## Core Components

### 1. `EnableDatabaseFiltering` Trait

Add to any Eloquent model to enable filtering:

```php
use Devespresso\LaravelApiKit\Traits\EnableDatabaseFiltering;

class Post extends Model
{
    use EnableDatabaseFiltering;

    protected $defaultFilterService = PostFilterService::class; // optional

    protected $searchableColumns = ['title', 'body']; // used by the search scope
}
```

Call `filter()` from a controller:

```php
$posts = Post::filter($request->validated(), $request->user());
```

`filter()` accepts two optional extra parameters:

```php
Post::filter(
    $request->validated(),  // request data — drives filter methods and sorting
    $request->user(),       // authenticated user — controls admin-only filters
    $query,                 // pre-scoped Builder — useful when you need to apply
                            // base constraints before the filter service runs
    $extras,                // arbitrary key/value context — accessible inside
                            // filter methods via $this->getExtraProperty('key')
);
```

**Pre-scoping the query with a parent resource (`$query`):**

```php
// Only show posts belonging to the current team — enforced before filters run
$query = Post::where('team_id', $team->id);

$posts = Post::filter($request->validated(), $request->user(), $query);
```

**Passing context into filter methods (`$extras`):**

```php
$posts = Post::filter(
    $request->validated(),
    $request->user(),
    query: null,
    extras: ['team' => $team]
);

// Inside PostFilterService — read the extra value via getExtraProperty():
public function setConditions(): void
{
    $team = $this->getExtraProperty('team');
    $this->query->where('visibility', $team->default_visibility);
}
```

**Using `$this->user` inside the filter service:**

`$this->user` holds the authenticated user passed as the second argument to `filter()`. It is available anywhere in the filter service — `setConditions()`, filter methods, and any custom method you add to the subclass.

```php
public function setConditions(): void
{
    // Scope results to the authenticated user
    $this->query->where('user_id', $this->user->id);
}

public function status(string $value): void
{
    // Only admins can filter by draft status
    if ($value === 'draft' && !$this->user?->isAdmin()) {
        return;
    }

    $this->query->where('status', $value);
}
```

---

### 2. `BaseFilterService`

Create a filter service per model by extending `BaseFilterService`. Each key in the incoming request data is camelCased and dispatched to a matching method on the service.

```php
use Devespresso\LaravelApiKit\Services\Filters\BaseFilterService;

class PostFilterService extends BaseFilterService
{
    // Columns users are allowed to sort by
    protected $sortColumns = ['created_at', 'updated_at', 'id', 'title'];

    // Alias => real column name mappings for sort
    protected $customSortColumns = ['date' => 'created_at'];

    // Default sort when no 'sort' key is in the request
    protected $defaultSortingColumn = ['created_at,desc'];

    // Methods that cannot be triggered by request data
    protected $guardedMethods = ['sensitiveMethod'];

    // Methods only accessible to admin users (requires isAdmin() on user model)
    protected $adminMethods = ['includeTrashed'];

    // Methods always applied, regardless of request data
    protected $autoApply = ['onlyPublished' => true];

    // Baseline constraints always added to the query
    protected function setConditions(): void
    {
        $this->query->where('team_id', $this->user->team_id);
    }

    // Called when request data contains 'status'
    public function status(string $value): void
    {
        $this->query->where('status', $value);
    }

    // Called when request data contains 'author_id'
    public function authorId(int $value): void
    {
        $this->query->where('user_id', $value);
    }

    // Always applied via $autoApply
    public function onlyPublished(bool $value): void
    {
        $this->query->where('published', true);
    }

    // Only callable by admin users
    public function includeTrashed(bool $value): void
    {
        if ($value) {
            $this->query->withTrashed();
        }
    }
}
```

#### Available helpers inside filter methods

```php
$this->getDataValue('key', $default); // get a value from request data
$this->dataHasValue('key', 'value');  // check if key equals a specific value
$this->dataHasKeys(['key1', 'key2']); // check all keys are present
$this->getExtraProperty('tenant_id'); // get a value from $extras
$this->with(['comments', 'tags']);    // eager load relations
$this->withCount(['comments']);       // eager load relation counts
$this->disableConditions();           // skip setConditions() for the next filter() call — useful in admin or internal contexts
$this->setSelect(['id', 'title']);    // override the auto-selected columns; has no effect when auto_select is disabled
```

#### Method Dispatch and Security

The filter service works by taking each key in the incoming request data, converting it to camelCase, and calling the matching public method on the service if it exists. For example, passing `author_id=5` in the request will automatically call `$this->authorId(5)`.

This means **any public method on your filter service subclass is callable from request data by default**. The package protects against this in four ways:

**1. Base class methods are always blocked**

All public methods defined on `BaseFilterService` itself (e.g. `setData`, `setQuery`, `filter`) are automatically guarded and can never be triggered by request data. `sort` and `search` are intentionally excluded from this list so they remain dispatchable.

**2. Protected methods are automatically blocked**

Only `public` methods can be dispatched. If you define a method as `protected` on your subclass, it will never be triggered by request data — no configuration needed. Use this as a natural way to write internal helper methods without worrying about accidental exposure:

```php
protected function applyTeamScope(): void
{
    // safe — cannot be triggered from request data
    $this->query->where('team_id', $this->user->team_id);
}
```

**3. `$guardedMethods` — block specific public methods on your subclass**

Use this to explicitly prevent public methods on your subclass from being triggered by request data:

```php
protected $guardedMethods = ['internalScope', 'sensitiveMethod'];
```

Any method listed here will be silently skipped even if a matching key is present in the request.

**4. `$adminMethods` — restrict methods to admin users only**

Methods listed here are only dispatched if the authenticated user has an `isAdmin()` method that returns `true`. For all other users the method is silently skipped:

```php
protected $adminMethods = ['includeTrashed', 'byTeam'];
```

> **Rule of thumb:** keep internal helpers `protected`. If a public method should not be triggerable from a request key, add it to `$guardedMethods`. If it should only be available to admins, add it to `$adminMethods`.

#### Auto-Apply

Methods listed in `$autoApply` are always dispatched regardless of what is in the request data. They run after the request-driven filters and cannot be skipped by the caller:

```php
protected $autoApply = ['onlyPublished' => true];

public function onlyPublished(bool $value): void
{
    $this->query->where('published', true);
}
```

Use this for constraints that must always be enforced — scoping to active records, filtering by tenant, etc.

#### Pagination

Control pagination via request data:

| `pagination_type` | Result |
|---|---|
| _(not set)_ | `simplePaginate()` or `paginate()` based on config |
| `simple` | `simplePaginate()` — no total count query |
| `paginate` | `paginate()` — includes total count |
| `none` | `get()` — returns all results |

```
GET /posts?pagination_type=none&per_page=50
```

#### Sorting

```
GET /posts?sort=created_at,desc
GET /posts?sort[]=title,asc&sort[]=created_at,desc
```

---

### 3. `BaseTransformer`

Controls which model attributes are included in API responses and how they are formatted. The transformer is resolved automatically from the model name (`PostTransformer` for `Post`), or set explicitly via `$transformer` on the filter service or controller.

```php
use Devespresso\LaravelApiKit\Transformers\BaseTransformer;

class PostTransformer extends BaseTransformer
{
    protected $formats = [
        // Always included
        '*' => [
            'id',
            'title',
            'status',
            '@word_count',         // custom attribute (computed)
            '!internal_notes',     // hidden (excluded from output)
            'author' => [          // nested relation
                'id',
                'name',
                '!password',       // hidden within the relation
            ],
        ],

        // Merged with * on the show route
        'show' => [
            'body',
            'created_at',
        ],

        // Returned as-is on index — does NOT merge with *
        '_index' => [
            'id',
            'title',
        ],
    ];

    // Rename output keys
    protected $renames = [
        '*' => ['created_at' => 'createdAt'],     // global rename
        'author.name' => 'authorName',             // path-specific rename
    ];

    // Format attribute values
    protected $formatters = [
        '*' => ['status' => 'formatStatus'],       // global formatter
        'author.name' => ['toUpper'],              // path-specific formatter
    ];

    // Computed attributes resolved via methods (used with the '@' prefix)
    protected $customAttributes = [
        'word_count' => 'getWordCount',
    ];

    // Default values when an attribute is null
    protected $defaults = [
        '*' => ['status' => 'draft'],              // global scalar default
        'author.bio' => 'getBioDefault',           // path-specific method default
    ];

    // Conditionally hide attributes based on the current user/context
    protected $guarded = [
        '*' => ['salary' => 'isNotAdmin'],         // global guard
        'user.secret' => 'isNotOwner',             // path-specific guard
    ];

    // Custom attribute methods (called with the model)
    public function getWordCount($model): int
    {
        return str_word_count($model->body ?? '');
    }

    // Formatter methods
    public function formatStatus($value): string
    {
        return ucfirst($value);
    }

    // Guard methods (return true to hide, false to show)
    public function isNotAdmin($model): bool
    {
        return !auth()->user()?->isAdmin();
    }
}
```

#### Attribute Prefixes

| Prefix | Meaning |
|---|---|
| `!attribute` | Hidden — excluded from output. On a relation key, still eager-loaded for SELECT purposes but not returned. |
| `@attribute` | Custom — value resolved via the `$customAttributes` map instead of reading from the database. |

> All prefixes are configurable via `config/devespressoApi.php` under `transformers.prefixes`. If your attribute names clash with the defaults, change them there and the entire package will use your values automatically.

#### Format Key Prefixes

| Format key | Behaviour |
|---|---|
| `*` | Wildcard — always included, merged with the matched route key |
| `show`, `index`, etc. | Merged on top of `*` for that controller method |
| `_index` | Returned standalone — does **not** merge with `*` |

#### `$wrapper`

The `$wrapper` property controls the key name used to wrap the transformed data in the response. If not set, it defaults to `'data'`:

```php
protected $wrapper = 'post';
// produces: {"post": {...}} instead of {"data": {...}}
```

The wrapper can also be overridden per-call from the controller via `setData($post, 'post')`.

#### Transformer-Driven Query

When `auto_select` and `auto_eager_load` are enabled, the filter service reads your transformer's `$formats` definition and automatically builds an optimised query — no `SELECT *`, no N+1.

Given this transformer:

```php
class PostTransformer extends BaseTransformer
{
    protected $formats = [
        '*' => [
            'id',
            'title',
            'status',
            'user_id',       // foreign key — must be included so Laravel can match
                             // the eager-loaded authors. Use '!user_id' instead if
                             // you want it selected but hidden from the response.
            '@word_count',   // computed attribute — excluded from SELECT entirely
            '!team_id',      // hidden from output — but still SELECTed (useful for auth checks)
            'author' => [    // relation — auto eager-loaded
                'id',
                'name',
                '!email',    // hidden from output — but still SELECTed
            ],
        ],
    ];
}
```

> **Important:** always include the foreign key that connects the relation (e.g. `user_id` on posts) in your transformer format. Without it, the column won't be selected and the eager-loaded relation will return empty. Use the plain key to include it in the response, or prefix it with `!` to select it silently.

Calling `Post::filter($request->validated(), $request->user())` generates exactly:

```sql
SELECT posts.id, posts.title, posts.status, posts.user_id, posts.team_id
FROM posts
WHERE ...

-- one eager-load query, no N+1:
SELECT users.id, users.name, users.email
FROM users
WHERE users.id IN (1, 2, 3, ...)
```

And the JSON response includes only what was declared as visible — `!` prefixed fields are fetched but stripped from the output:

```json
{
    "posts": [
        {
            "id": 1,
            "title": "Hello World",
            "status": "published",
            "user_id": 5,
            "word_count": 42,
            "author": {
                "id": 5,
                "name": "Alice"
            }
        }
    ]
}
```

> `team_id` and `email` were selected but hidden via `!` — they never appear in the response. `user_id` is selected and visible since it was declared without a prefix. If you changed it to `'!user_id'` in the transformer, it would still be selected but would disappear from the response.

The Eloquent equivalent you would otherwise write by hand:

```php
Post::select('posts.id', 'posts.title', 'posts.status', 'posts.user_id', 'posts.team_id')
    ->with(['author' => fn ($q) => $q->select('users.id', 'users.name', 'users.email')])
    ->where('team_id', $user->team_id)
    ->where('published', true)
    ->simplePaginate($perPage);
```

With the package, that query is derived automatically from the transformer — you never write it, and it stays in sync with your response format as the transformer evolves.

---

### 4. `BaseRepository`

Provides standard CRUD with lifecycle hooks. Automatically resolves the model from the repository class name (`PostRepository` → `Post`).

```php
use Devespresso\LaravelApiKit\Repositories\BaseRepository;

class PostRepository extends BaseRepository
{
    // Optional: override auto-resolved model
    protected $model = Post::class;

    // Hooks
    protected function beforeCreate(array &$attributes): void
    {
        $attributes['slug'] = Str::slug($attributes['title']);
    }

    protected function afterCreated(Model $model, array $attributes): void
    {
        event(new PostCreated($model));
    }

    protected function beforeUpdate(?Model $model, array &$attributes): void
    {
        if (isset($attributes['title'])) {
            $attributes['slug'] = Str::slug($attributes['title']);
        }
    }

    protected function afterUpdated(Model $model, array $attributes): void
    {
        Cache::forget("post:{$model->id}");
    }

    protected function beforeDelete(Model $model): void
    {
        // runs before delete
    }

    protected function afterDeleted(Model $model): void
    {
        // runs after delete
    }
}
```

Available methods:

```php
$repo->index($data, $user);          // filtered, paginated list
$repo->get($id);                     // single record
$repo->create($attributes);          // create with hooks
$repo->update($model, $attributes);  // update with hooks
$repo->delete($model);               // delete with hooks
```

To skip hooks for a single operation, chain `withoutHooks()` before the call. The skip list resets automatically after each operation.

```php
// Skip all hooks
$repo->withoutHooks()->delete($model);

// Skip specific hooks only
$repo->withoutHooks('afterCreated')->create($attributes);
$repo->withoutHooks('beforeUpdate', 'afterUpdated')->update($model, $attributes);
```

---

### 5. `ApiController`

Base controller for JSON API responses. Automatically resolves a transformer and repository from the controller class name.

```php
use Devespresso\LaravelApiKit\Controllers\ApiController;

class PostController extends ApiController
{
    public function index(PostRequest $request): JsonResponse
    {
        return $this->setData(
            $this->repository->index($request->validated(), $request->user())
        )->respond();
    }

    public function store(PostRequest $request): JsonResponse
    {
        return $this->setData(
            $this->repository->create($request->validated())
        )->respond();
    }

    public function destroy(Post $post): JsonResponse
    {
        $this->repository->delete($post);

        return $this->setCode(204)->respond();
    }
}
```

When there is no model data to return, call `respond()` directly:

```php
return $this->setCode(204)->respond();
```

You can also pass an array to `respond()` to merge additional data into the response, or override existing keys entirely:

```php
// Merge extra keys into the response
return $this->setData($post)->respond(['meta' => ['generated_at' => now()]]);

// Override a key set by setData()
return $this->setData($post)->respond(['post' => $customPayload], override: true);
```

#### `setData()` optional parameters

`setData()` accepts two optional arguments that give you finer control over the response shape:

- **`$wrapper`** — overrides the key name used to wrap the data in the response. By default the transformer's own `$wrapper` value is used. Passing a string replaces it for that call:

    ```php
    return $this->setData($post, 'post')->respond();
    // produces: {"post": {...}} instead of the transformer default
    ```

- **`$format`** — selects a specific format key from the transformer's `$formats` array instead of auto-detecting from the current route action:

    ```php
    return $this->setData($post, format: 'show')->respond();
    // uses the 'show' format from PostTransformer
    ```

#### Overriding the transformer at runtime

Use `setTransformer()` to swap out the auto-resolved transformer for a specific call. Useful when one controller needs to serve multiple models or formats:

```php
return $this->setTransformer(SummaryTransformer::class)
    ->setData($post)
    ->respond();
```

#### `setCode()` and error responses

`setCode()` automatically sets `status` to `"error"` for any code >= 400. An optional second argument sets a custom message:

```php
return $this->setCode(404, 'Post not found')->respond();
// {"code": 404, "status": "error", "message": "Post not found"}
```

#### Disabling auto-resolution

Both `$autoResolveRepository` and `$autoResolveTransformer` can be set to `false` on the subclass to disable auto-resolution when you want full manual control:

```php
class PostController extends ApiController
{
    protected bool $autoResolveRepository = false;
    protected bool $autoResolveTransformer = false;
}
```

Default response format:

```json
{
    "code": 200,
    "status": "success",
    "message": "OK",
    "data": { ... },
    "pagination": { ... }
}
```

---

### 6. `BaseRequest`

Auto-dispatches validation rules and authorization per controller method. Includes built-in rules for pagination and sorting on all list endpoints.

```php
use Devespresso\LaravelApiKit\Requests\BaseRequest;

class PostRequest extends BaseRequest
{
    protected function actionsRules(): array
    {
        return [
            'store' => [
                'title' => ['required', 'string', 'max:255'],
                'body'  => ['required', 'string'],
            ],
            'update' => fn () => [
                'title' => ['sometimes', 'string', 'max:255'],
                'body'  => ['sometimes', 'string'],
            ],
        ];
    }

    // Optional per-action authorization
    protected function storeAuth(): bool
    {
        return $this->user()->can('create', Post::class);
    }
}
```

Built-in rules available on all requests (from `indexRules()`):

| Key | Rule |
|---|---|
| `sort` | string |
| `per_page` | integer, min:1, max:100 |
| `with_pages` | boolean |
| `pagination_type` | in:paginate,none,simple |

---

### 7. `BaseAuthorisationService`

Property-based authorisation checks, usable standalone or from filter services.

```php
use Devespresso\LaravelApiKit\Services\Authorisation\BaseAuthorisationService;

class PostAuthorisationService extends BaseAuthorisationService
{
    protected $mainProperty = 'post';
}

// In a controller or service:
$auth = (new PostAuthorisationService())
    ->setUser($user)
    ->setProperties(['post' => $post])
    ->doesItBelongToUser()         // asserts post->user_id === $user->id
    ->requireUser()                // asserts user is authenticated
    ->passwordVerification($password);
```

Use `skipExceptions()` to collect errors instead of throwing:

```php
$auth = (new PostAuthorisationService())
    ->skipExceptions()
    ->setUser($user)
    ->setProperties(['post' => $post])
    ->doesItBelongToUser();

if (!$auth->isValid()) {
    return response()->json(['errors' => $auth->getErrors()], 403);
}
```

---

## Running Tests

```bash
composer test
```

---

## License

MIT
