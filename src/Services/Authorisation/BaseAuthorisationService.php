<?php

namespace Devespresso\LaravelApiKit\Services\Authorisation;

use Devespresso\LaravelApiKit\Exceptions\AuthorisationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;

class BaseAuthorisationService
{
    /**
     * When true, errors are collected into $errors instead of throwing exceptions.
     * Call skipExceptions() to enable this mode.
     */
    protected bool $skipExceptions = false;

    protected array $errors = [];

    protected ?Authenticatable $user = null;

    protected array $properties = [];

    /** The key within $properties treated as the primary subject of authorisation checks. */
    protected ?string $mainProperty = null;

    /**
     * Gets a property by key.
     */
    public function getProperty(string $property): mixed
    {
        return $this->properties[$property] ?? null;
    }

    /**
     * Gets the main property value.
     *
     * @throws \RuntimeException if mainProperty has not been defined.
     */
    public function getMainProperty(): mixed
    {
        if (! $this->mainProperty || ! array_key_exists($this->mainProperty, $this->properties)) {
            throw new \RuntimeException('Main property ['.($this->mainProperty ?? 'null').'] has not been set on ['.static::class.'].');
        }

        return $this->properties[$this->mainProperty];
    }

    /**
     * Sets the value of the main property.
     */
    public function setMainProperty(mixed $property): self
    {
        $this->properties[$this->mainProperty] = $property;

        return $this;
    }

    /**
     * Replaces all properties.
     */
    public function setProperties(array $properties = []): self
    {
        $this->properties = $properties;

        return $this;
    }

    /**
     * Sets the user to authorise against.
     */
    public function setUser(?Authenticatable $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Switches to error-collection mode — errors are added to $errors instead of throwing.
     */
    public function skipExceptions(): self
    {
        $this->skipExceptions = true;

        return $this;
    }

    /**
     * Asserts that the given property (or the main property) belongs to the provided owner.
     *
     * @param  Authenticatable|object  $owner       The owner to check against.
     * @param  string                  $foreignKey  The foreign key on the model (e.g. 'user_id', 'team_id').
     * @param  string                  $ownerKey    The key to read from the owner (e.g. 'id').
     * @param  string|null             $property    A specific property to check; uses the main property if null.
     */
    public function doesItBelongTo(
        object $owner,
        string $foreignKey = 'user_id',
        string $ownerKey = 'id',
        ?string $property = null
    ): self {
        $model = $property ? $this->getProperty($property) : $this->getMainProperty();

        $ownerId = $owner instanceof Authenticatable
            ? $owner->getAuthIdentifier()
            : $owner->{$ownerKey};

        if (! $model?->{$foreignKey} || $model->{$foreignKey} !== $ownerId) {
            $this->error('Item does not belong to the provided owner.');
        }

        return $this;
    }

    /**
     * Convenience wrapper — asserts the property belongs to the current user via user_id.
     */
    public function doesItBelongToUser(?string $property = null): self
    {
        if (! $this->user) {
            $this->error('Sorry, user has not been set.');

            return $this;
        }

        return $this->doesItBelongTo($this->user, 'user_id', 'id', $property);
    }

    /**
     * Verifies the given password against the current user's stored password.
     */
    public function passwordVerification(?string $password): self
    {
        if (! $password) {
            $this->error('No password was provided.');

            return $this;
        }

        if (! $this->user || ! Hash::check($password, $this->user->getAuthPassword())) {
            $this->error('The provided credentials are incorrect.');
        }

        return $this;
    }

    /**
     * Asserts that a user is currently authenticated via the auth guard.
     */
    public function requireUser(): self
    {
        if (! auth()->check()) {
            $this->error('Not authenticated.', 401);
        }

        return $this;
    }

    /**
     * Returns all collected errors.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Returns true if no errors have been collected.
     */
    public function isValid(): bool
    {
        return count($this->errors) === 0;
    }

    /**
     * Either throws an AuthorisationException or collects the error, depending on skipExceptions mode.
     *
     * @throws AuthorisationException
     */
    protected function error(string $message, int $code = 403): void
    {
        if ($this->skipExceptions) {
            $this->errors[] = $message;

            return;
        }

        throw new AuthorisationException($message, $code);
    }
}
