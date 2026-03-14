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

`config/devespressoApi.php`

```php
return [
    'pagination' => [
        'with_pages' => false, // true = paginate() (with total), false = simplePaginate()
    ],
    'paths' => [
        'models'        => 'App\\Models\\',
        'transformers'  => 'App\\Transformers\\',
        'repositories'  => 'App\\Repositories\\',
    ],
    'auto_select'      => true,  // auto SELECT columns from transformer format
    'auto_eager_load'  => true,  // auto eager-load relations from transformer format
    'transformers' => [
        'prefixes' => [
            'hidden_attributes' => ':',  // exclude from output
            'custom_attributes' => '-',  // computed value, not a DB column
            'unmerged_format'   => '_',  // format not merged with the * wildcard
        ],
    ],
];
```

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
```

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
            '-word_count',         // custom attribute (computed)
            ':internal_notes',     // hidden (excluded from output)
            'author' => [          // nested relation
                'id',
                'name',
                ':password',       // hidden within the relation
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

    // Computed attributes resolved via methods (used with the '-' prefix)
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
| `:attribute` | Hidden — excluded from output. On a relation key, still eager-loaded for SELECT purposes but not returned. |
| `-attribute` | Custom — value resolved via the `$customAttributes` map instead of reading from the database. |

#### Format Key Prefixes

| Format key | Behaviour |
|---|---|
| `*` | Wildcard — always included, merged with the matched route key |
| `show`, `index`, etc. | Merged on top of `*` for that controller method |
| `_index` | Returned standalone — does **not** merge with `*` |

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
}
```

Available methods:

```php
$repo->index($data, $user);          // filtered, paginated list
$repo->get($id);                     // single record
$repo->create($attributes);          // create with hooks
$repo->update($model, $attributes);  // update with hooks
$repo->delete($model);               // delete
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

Response format:

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
