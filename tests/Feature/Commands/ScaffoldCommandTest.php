<?php

namespace Devespresso\LaravelApiKit\Tests\Feature\Commands;

use Devespresso\LaravelApiKit\Tests\TestCase;
use Illuminate\Support\Facades\File;

class ScaffoldCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        // Clean up generated files after each test
        File::deleteDirectory(base_path('App'));

        parent::tearDown();
    }

    public function test_it_scaffolds_all_components(): void
    {
        $this->artisan('devespresso:api-kit:scaffold', ['name' => 'Post'])
            ->assertSuccessful();

        $this->assertFileExists(base_path('App/Models/Post.php'));
        $this->assertFileExists(base_path('App/Repositories/PostRepository.php'));
        $this->assertFileExists(base_path('App/Http/Controllers/PostController.php'));
        $this->assertFileExists(base_path('App/Transformers/PostTransformer.php'));
        $this->assertFileExists(base_path('App/Http/Requests/PostRequest.php'));
        $this->assertFileExists(base_path('App/Services/Authorisation/PostAuthorisationService.php'));
        $this->assertFileExists(base_path('App/Services/Filters/PostFilterService.php'));
    }

    public function test_model_extends_base_model_and_uses_filtering_trait(): void
    {
        $this->artisan('devespresso:api-kit:scaffold', ['name' => 'Post']);

        $content = File::get(base_path('App/Models/Post.php'));

        $this->assertStringContainsString('namespace App\Models;', $content);
        $this->assertStringContainsString('class Post extends Model', $content);
        $this->assertStringContainsString('use HasFactory, EnableDatabaseFiltering;', $content);
        $this->assertStringContainsString('PostFilterService::class', $content);
        $this->assertStringContainsString('protected $fillable = [];', $content);
        $this->assertStringContainsString('protected function casts(): array', $content);
    }

    public function test_repository_extends_base_repository(): void
    {
        $this->artisan('devespresso:api-kit:scaffold', ['name' => 'Post']);

        $content = File::get(base_path('App/Repositories/PostRepository.php'));

        $this->assertStringContainsString('namespace App\Repositories;', $content);
        $this->assertStringContainsString('class PostRepository extends BaseRepository', $content);
    }

    public function test_controller_extends_api_controller_with_route_model_binding(): void
    {
        $this->artisan('devespresso:api-kit:scaffold', ['name' => 'Post']);

        $content = File::get(base_path('App/Http/Controllers/PostController.php'));

        $this->assertStringContainsString('namespace App\Http\Controllers;', $content);
        $this->assertStringContainsString('class PostController extends ApiController', $content);
        $this->assertStringContainsString('use App\Models\Post;', $content);
        $this->assertStringContainsString('use App\Http\Requests\PostRequest;', $content);
        $this->assertStringContainsString('Post $post', $content);
        $this->assertStringContainsString('public function index(PostRequest $request)', $content);
        $this->assertStringContainsString('public function show(PostRequest $request, Post $post)', $content);
        $this->assertStringContainsString('public function store(PostRequest $request)', $content);
        $this->assertStringContainsString('public function update(PostRequest $request, Post $post)', $content);
        $this->assertStringContainsString('public function destroy(PostRequest $request, Post $post)', $content);
    }

    public function test_transformer_extends_base_transformer(): void
    {
        $this->artisan('devespresso:api-kit:scaffold', ['name' => 'Post']);

        $content = File::get(base_path('App/Transformers/PostTransformer.php'));

        $this->assertStringContainsString('namespace App\Transformers;', $content);
        $this->assertStringContainsString('class PostTransformer extends BaseTransformer', $content);
        $this->assertStringContainsString("'*' => [", $content);
    }

    public function test_request_extends_base_request_with_callable_rules(): void
    {
        $this->artisan('devespresso:api-kit:scaffold', ['name' => 'Post']);

        $content = File::get(base_path('App/Http/Requests/PostRequest.php'));

        $this->assertStringContainsString('namespace App\Http\Requests;', $content);
        $this->assertStringContainsString('class PostRequest extends BaseRequest', $content);
        $this->assertStringContainsString("'store' => fn () => []", $content);
        $this->assertStringContainsString("'update' => fn () => []", $content);
    }

    public function test_authorisation_extends_base_with_main_property(): void
    {
        $this->artisan('devespresso:api-kit:scaffold', ['name' => 'Post']);

        $content = File::get(base_path('App/Services/Authorisation/PostAuthorisationService.php'));

        $this->assertStringContainsString('namespace App\Services\Authorisation;', $content);
        $this->assertStringContainsString('class PostAuthorisationService extends BaseAuthorisationService', $content);
        $this->assertStringContainsString("'post'", $content);
    }

    public function test_filter_service_extends_base_filter_service(): void
    {
        $this->artisan('devespresso:api-kit:scaffold', ['name' => 'Post']);

        $content = File::get(base_path('App/Services/Filters/PostFilterService.php'));

        $this->assertStringContainsString('namespace App\Services\Filters;', $content);
        $this->assertStringContainsString('class PostFilterService extends BaseFilterService', $content);
        $this->assertStringContainsString('$sortColumns', $content);
        $this->assertStringContainsString('setConditions', $content);
    }

    public function test_only_option_generates_specified_components(): void
    {
        $this->artisan('devespresso:api-kit:scaffold', [
            'name' => 'Post',
            '--only' => 'model,transformer',
        ])->assertSuccessful();

        $this->assertFileExists(base_path('App/Models/Post.php'));
        $this->assertFileExists(base_path('App/Transformers/PostTransformer.php'));

        $this->assertFileDoesNotExist(base_path('App/Repositories/PostRepository.php'));
        $this->assertFileDoesNotExist(base_path('App/Http/Controllers/PostController.php'));
        $this->assertFileDoesNotExist(base_path('App/Http/Requests/PostRequest.php'));
        $this->assertFileDoesNotExist(base_path('App/Services/Authorisation/PostAuthorisationService.php'));
        $this->assertFileDoesNotExist(base_path('App/Services/Filters/PostFilterService.php'));
    }

    public function test_except_option_skips_specified_components(): void
    {
        $this->artisan('devespresso:api-kit:scaffold', [
            'name' => 'Post',
            '--except' => 'model,controller',
        ])->assertSuccessful();

        $this->assertFileDoesNotExist(base_path('App/Models/Post.php'));
        $this->assertFileDoesNotExist(base_path('App/Http/Controllers/PostController.php'));

        $this->assertFileExists(base_path('App/Repositories/PostRepository.php'));
        $this->assertFileExists(base_path('App/Transformers/PostTransformer.php'));
        $this->assertFileExists(base_path('App/Http/Requests/PostRequest.php'));
        $this->assertFileExists(base_path('App/Services/Authorisation/PostAuthorisationService.php'));
        $this->assertFileExists(base_path('App/Services/Filters/PostFilterService.php'));
    }

    public function test_it_does_not_overwrite_existing_files_without_force(): void
    {
        $this->artisan('devespresso:api-kit:scaffold', ['name' => 'Post']);

        $originalContent = File::get(base_path('App/Models/Post.php'));

        // Modify the file
        File::put(base_path('App/Models/Post.php'), '<?php // modified');

        // Run again without --force
        $this->artisan('devespresso:api-kit:scaffold', ['name' => 'Post']);

        $this->assertSame('<?php // modified', File::get(base_path('App/Models/Post.php')));
    }

    public function test_force_option_overwrites_existing_files(): void
    {
        $this->artisan('devespresso:api-kit:scaffold', ['name' => 'Post']);

        // Modify the file
        File::put(base_path('App/Models/Post.php'), '<?php // modified');

        // Run again with --force
        $this->artisan('devespresso:api-kit:scaffold', ['name' => 'Post', '--force' => true]);

        $content = File::get(base_path('App/Models/Post.php'));
        $this->assertStringContainsString('class Post extends Model', $content);
    }

    public function test_it_converts_name_to_studly_case(): void
    {
        $this->artisan('devespresso:api-kit:scaffold', ['name' => 'blog_post'])
            ->assertSuccessful();

        $this->assertFileExists(base_path('App/Models/BlogPost.php'));

        $content = File::get(base_path('App/Models/BlogPost.php'));
        $this->assertStringContainsString('class BlogPost extends Model', $content);

        $controllerContent = File::get(base_path('App/Http/Controllers/BlogPostController.php'));
        $this->assertStringContainsString('BlogPost $blogPost', $controllerContent);
    }

    public function test_it_uses_custom_paths_from_config(): void
    {
        config()->set('devespressoApi.paths.models', 'App\\Domain\\Models\\');
        config()->set('devespressoApi.paths.repositories', 'App\\Domain\\Repositories\\');

        $this->artisan('devespresso:api-kit:scaffold', [
            'name' => 'Post',
            '--only' => 'model,repository',
        ])->assertSuccessful();

        $this->assertFileExists(base_path('App/Domain/Models/Post.php'));
        $this->assertFileExists(base_path('App/Domain/Repositories/PostRepository.php'));

        $content = File::get(base_path('App/Domain/Models/Post.php'));
        $this->assertStringContainsString('namespace App\Domain\Models;', $content);

        // Clean up custom path
        File::deleteDirectory(base_path('App/Domain'));
    }

    public function test_output_lists_created_files(): void
    {
        $this->artisan('devespresso:api-kit:scaffold', ['name' => 'Post'])
            ->expectsOutputToContain('Scaffolding Post')
            ->expectsOutputToContain('Done!')
            ->assertSuccessful();
    }

    public function test_output_lists_skipped_files(): void
    {
        $this->artisan('devespresso:api-kit:scaffold', ['name' => 'Post']);

        $this->artisan('devespresso:api-kit:scaffold', ['name' => 'Post'])
            ->expectsOutputToContain('Skipped')
            ->assertSuccessful();
    }
}
