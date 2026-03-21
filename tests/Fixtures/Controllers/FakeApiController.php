<?php

namespace Devespresso\LaravelApiKit\Tests\Fixtures\Controllers;

use Devespresso\LaravelApiKit\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

class FakeApiController extends ApiController
{
    protected bool $autoResolveRepository = false;

    protected bool $autoResolveTransformer = false;

    public function respondWithDefaults(): JsonResponse
    {
        return $this->respond();
    }

    public function respondWithRawData(mixed $value, string $key = 'data'): JsonResponse
    {
        return $this->setRawData($value, $key)->respond();
    }

    public function respondWithMeta(array $meta): JsonResponse
    {
        return $this->setMeta($meta)->respond();
    }

    public function respondWithAddMeta(string $key, mixed $value): JsonResponse
    {
        return $this->addMeta($key, $value)->respond();
    }

    public function respondWithMultipleMeta(array ...$pairs): JsonResponse
    {
        foreach ($pairs as [$key, $value]) {
            $this->addMeta($key, $value);
        }

        return $this->respond();
    }

    public function respondWithCreated(array $response = []): JsonResponse
    {
        return $this->respondCreated($response);
    }

    public function respondWithNoContent(): JsonResponse
    {
        return $this->respondNoContent();
    }

    public function respondWithCode(int $code, ?string $message = null): JsonResponse
    {
        return $this->setCode($code, $message)->respond();
    }

    public function respondWithAppendTo(mixed $value, string $key = 'data'): self
    {
        return $this->appendTo($value, $key);
    }

    public function callSetData(mixed $data, ?string $wrapper = null, ?string $format = null): self
    {
        return $this->setData($data, $wrapper, $format);
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }
}
