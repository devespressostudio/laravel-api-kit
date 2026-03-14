<?php

namespace Devespresso\LaravelApiKit\Controllers;

use Devespresso\LaravelApiKit\Repositories\BaseRepository;
use Devespresso\LaravelApiKit\Transformers\BaseTransformer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ApiController
{
    protected string $status = 'success';

    protected int $code = 200;

    protected array $data = [];

    protected array $pagination = [];

    protected string $statusMessage = '';

    /** Class string or instance of the transformer to use. Resolved automatically if not set. */
    protected string|object|null $transformer = null;

    /** Class string or instance of the repository to use. Resolved automatically if not set. */
    protected string|object|null $repository = null;

    /** Set to false to disable auto-resolution of the repository. */
    protected bool $autoResolveRepository = true;

    /** Set to false to disable auto-resolution of the transformer. */
    protected bool $autoResolveTransformer = true;

    protected ?BaseRepository $resolvedRepository = null;

    public function __construct()
    {
        if ($this->autoResolveRepository) {
            $this->resolvedRepository = $this->resolveRepository();
        }
    }

    /**
     * Sets the HTTP status code and optionally a custom message.
     * Automatically sets status to "error" for 4xx/5xx codes.
     */
    protected function setCode(int $code, ?string $message = null): self
    {
        $this->code = $code;

        if ($this->code >= 400) {
            $this->status = 'error';
        }

        $this->statusMessage = $message ?? '';

        return $this;
    }

    /**
     * Overrides the auto-resolved transformer with a specific class.
     */
    protected function setTransformer(string $transformer): self
    {
        $this->transformer = $transformer;

        return $this;
    }

    /**
     * Transforms and sets the response data.
     * Automatically extracts pagination metadata from LengthAwarePaginator instances.
     */
    protected function setData(
        $data,
        ?string $wrapper = null,
        ?string $format = null
    ): self {
        if (! $this->autoResolveTransformer) {
            throw new \RuntimeException('Cannot call setData() when autoResolveTransformer is disabled on ['.static::class.'].');
        }

        $transformer = $this->resolveTransformer();

        $this->data[$wrapper ?? $transformer->wrapper] = $transformer->transformData($data, $format);

        if ($data instanceof LengthAwarePaginator) {
            $this->pagination['pagination'] = $this->getPagination($data);
        }

        return $this;
    }

    /**
     * Builds and returns the JSON response.
     *
     * @param  array  $response  Additional data to merge into the response payload.
     * @param  bool   $override  When true, later keys override earlier ones (array_merge).
     *                           When false, conflicting keys are merged recursively (array_merge_recursive).
     */
    protected function respond(array $response = [], bool $override = true): JsonResponse
    {
        $payload = [
            'code' => $this->code,
            'status' => $this->status,
            'message' => $this->getStatusMessage(),
        ];

        if ($override) {
            $merged = array_merge($payload, $this->data, $response, $this->pagination);
        } else {
            $merged = array_merge_recursive($payload, $this->data, $response, $this->pagination);
        }

        return response()->json($merged, $this->code);
    }

    /**
     * Returns the status message, falling back to the standard HTTP status text.
     */
    protected function getStatusMessage(): string
    {
        return $this->statusMessage ?: Response::$statusTexts[$this->code];
    }

    /**
     * Extracts pagination metadata from a paginator instance.
     */
    protected function getPagination(LengthAwarePaginator $data): array
    {
        return [
            'current_page' => $data->currentPage(),
            'from' => $data->firstItem(),
            'last_page' => $data->lastPage(),
            'next_page_url' => $data->nextPageUrl(),
            'per_page' => $data->perPage(),
            'prev_page_url' => $data->previousPageUrl(),
            'to' => $data->lastItem(),
            'total' => $data->total(),
        ];
    }

    /**
     * Resolves the transformer instance.
     * Uses $transformer class string if set, otherwise infers from the controller name.
     * Example: UserController → {transformers_path}\UserTransformer
     *
     * @throws \RuntimeException if the resolved class does not exist.
     */
    protected function resolveTransformer(): BaseTransformer
    {
        if ($this->transformer instanceof BaseTransformer) {
            return $this->transformer;
        }

        $class = $this->transformer ?? (string) Str::of(class_basename($this))
            ->prepend(config('devespressoApi.paths.transformers'))
            ->replace('Controller', 'Transformer');

        if (! class_exists($class)) {
            throw new \RuntimeException("Transformer class [{$class}] could not be found for [".static::class.'].');
        }

        return resolve($class);
    }

    /**
     * Resolves the repository instance.
     * Uses $repository class string if set, otherwise infers from the controller name.
     * Example: UserController → {repositories_path}\UserRepository
     *
     * @throws \RuntimeException if the resolved class does not exist.
     */
    protected function resolveRepository(): BaseRepository
    {
        if ($this->repository instanceof BaseRepository) {
            return $this->repository;
        }

        $class = $this->repository ?? (string) Str::of(class_basename($this))
            ->prepend(config('devespressoApi.paths.repositories'))
            ->replace('Controller', 'Repository');

        if (! class_exists($class)) {
            throw new \RuntimeException("Repository class [{$class}] could not be found for [".static::class.'].');
        }

        return resolve($class);
    }
}
