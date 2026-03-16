<?php

namespace Devespresso\LaravelApiKit\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ScaffoldCommand extends Command
{
    protected $signature = 'devespresso:api-kit:scaffold
        {name : The name of the resource (e.g. Post, BlogPost)}
        {--only= : Comma-separated list of components to generate (model,repository,controller,transformer,request,authorisation,filter-service)}
        {--except= : Comma-separated list of components to skip}
        {--force : Overwrite existing files}';

    protected $description = 'Scaffold a full API resource (model, repository, controller, transformer, request, authorisation, filter service)';

    protected Filesystem $files;

    protected array $scaffoldComponents = [
        'model',
        'repository',
        'controller',
        'transformer',
        'request',
        'authorisation',
        'filter-service',
    ];

    protected array $created = [];

    protected array $skipped = [];

    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $scaffoldComponents = $this->resolveComponents();

        $this->info("Scaffolding {$name}...");
        $this->newLine();

        foreach ($scaffoldComponents as $component) {
            $this->generate($component, $name);
        }

        $this->newLine();

        if (count($this->created)) {
            $this->info('Created:');
            foreach ($this->created as $file) {
                $this->line("  <fg=green>✓</> {$file}");
            }
        }

        if (count($this->skipped)) {
            $this->newLine();
            $this->warn('Skipped (already exist, use --force to overwrite):');
            foreach ($this->skipped as $file) {
                $this->line("  <fg=yellow>-</> {$file}");
            }
        }

        $this->newLine();
        $this->info('Done!');

        return self::SUCCESS;
    }

    protected function resolveComponents(): array
    {
        if ($only = $this->option('only')) {
            return array_intersect($this->scaffoldComponents, explode(',', $only));
        }

        if ($except = $this->option('except')) {
            return array_diff($this->scaffoldComponents, explode(',', $except));
        }

        return $this->scaffoldComponents;
    }

    protected function generate(string $component, string $name): void
    {
        $method = 'generate' . Str::studly(str_replace('-', ' ', $component));

        if (method_exists($this, $method)) {
            $this->$method($name);
        }
    }

    protected function generateModel(string $name): void
    {
        $namespace = $this->getNamespace('models');
        $filterServiceNamespace = $this->getNamespace('filter_services');
        $class = $name;

        $this->writeFile(
            $this->getPath($namespace, $class),
            $this->buildStub('model', [
                '{{ namespace }}' => $namespace,
                '{{ class }}' => $class,
                '{{ filterServiceNamespace }}' => $filterServiceNamespace,
            ]),
            "{$namespace}\\{$class}"
        );
    }

    protected function generateRepository(string $name): void
    {
        $namespace = $this->getNamespace('repositories');
        $class = "{$name}Repository";

        $this->writeFile(
            $this->getPath($namespace, $class),
            $this->buildStub('repository', [
                '{{ namespace }}' => $namespace,
                '{{ class }}' => $class,
            ]),
            "{$namespace}\\{$class}"
        );
    }

    protected function generateController(string $name): void
    {
        $namespace = $this->getNamespace('controllers');
        $requestNamespace = $this->getNamespace('requests');
        $modelNamespace = $this->getNamespace('models');
        $class = "{$name}Controller";

        $this->writeFile(
            $this->getPath($namespace, $class),
            $this->buildStub('controller', [
                '{{ namespace }}' => $namespace,
                '{{ class }}' => $class,
                '{{ name }}' => $name,
                '{{ modelVariable }}' => Str::camel($name),
                '{{ modelNamespace }}' => $modelNamespace,
                '{{ requestNamespace }}' => $requestNamespace,
            ]),
            "{$namespace}\\{$class}"
        );
    }

    protected function generateTransformer(string $name): void
    {
        $namespace = $this->getNamespace('transformers');
        $class = "{$name}Transformer";

        $this->writeFile(
            $this->getPath($namespace, $class),
            $this->buildStub('transformer', [
                '{{ namespace }}' => $namespace,
                '{{ class }}' => $class,
            ]),
            "{$namespace}\\{$class}"
        );
    }

    protected function generateRequest(string $name): void
    {
        $namespace = $this->getNamespace('requests');
        $class = "{$name}Request";

        $this->writeFile(
            $this->getPath($namespace, $class),
            $this->buildStub('request', [
                '{{ namespace }}' => $namespace,
                '{{ class }}' => $class,
            ]),
            "{$namespace}\\{$class}"
        );
    }

    protected function generateAuthorisation(string $name): void
    {
        $namespace = $this->getNamespace('authorisation');
        $class = "{$name}AuthorisationService";
        $mainProperty = Str::camel($name);

        $this->writeFile(
            $this->getPath($namespace, $class),
            $this->buildStub('authorisation', [
                '{{ namespace }}' => $namespace,
                '{{ class }}' => $class,
                '{{ mainProperty }}' => $mainProperty,
            ]),
            "{$namespace}\\{$class}"
        );
    }

    protected function generateFilterService(string $name): void
    {
        $namespace = $this->getNamespace('filter_services');
        $class = "{$name}FilterService";

        $this->writeFile(
            $this->getPath($namespace, $class),
            $this->buildStub('filter-service', [
                '{{ namespace }}' => $namespace,
                '{{ class }}' => $class,
            ]),
            "{$namespace}\\{$class}"
        );
    }

    protected function buildStub(string $stub, array $replacements): string
    {
        $content = $this->files->get(__DIR__ . "/../stubs/{$stub}.stub");

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    protected function writeFile(string $path, string $content, string $label): void
    {
        if ($this->files->exists($path) && ! $this->option('force')) {
            $this->skipped[] = $label;

            return;
        }

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $content);

        $this->created[] = $label;
    }

    protected function getNamespace(string $configKey): string
    {
        return rtrim(config("devespressoApi.paths.{$configKey}", $this->defaultNamespace($configKey)), '\\');
    }

    protected function getPath(string $namespace, string $class): string
    {
        $relativePath = str_replace('\\', '/', $namespace) . '/' . $class . '.php';

        return base_path($relativePath);
    }

    protected function defaultNamespace(string $configKey): string
    {
        return match ($configKey) {
            'models' => 'App\\Models',
            'repositories' => 'App\\Repositories',
            'controllers' => 'App\\Http\\Controllers',
            'transformers' => 'App\\Transformers',
            'requests' => 'App\\Http\\Requests',
            'authorisation' => 'App\\Services\\Authorisation',
            'filter_services' => 'App\\Services\\Filters',
            default => 'App',
        };
    }
}
