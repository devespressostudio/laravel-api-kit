<?php

namespace Devespresso\LaravelApiKit\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * BaseRequest provides automatic rule and authorization dispatch based on the
 * current route action (controller method name).
 *
 * ## How it works
 *
 * Given a controller method like `store`, this class will automatically look for:
 *   - `storeRules()`  — validation rules specific to that action
 *   - `storeAuth()`   — authorization logic specific to that action
 *
 * If neither is found, rules default to an empty array and authorization defaults to `true`.
 *
 * ## Defining rules
 *
 * There are two ways to define rules for an action, and they can be combined:
 *
 * **1. Method-based (recommended for complex/lazy rules):**
 * Define a protected method named `{action}Rules()` in your subclass.
 * This is evaluated lazily — only called when that action is active.
 *
 *   protected function storeRules(): array
 *   {
 *       return ['email' => ['required', Rule::unique('users')]];
 *   }
 *
 * **2. actionsRules() map (good for simple static rules):**
 * Return a keyed array from `actionsRules()`. Values can be plain arrays or
 * closures — closures are resolved lazily so expensive rules (e.g. DB lookups)
 * are only initialised when that action is matched.
 *
 *   protected function actionsRules(): array
 *   {
 *       return [
 *           'store' => fn() => ['name' => ['required', 'string']],
 *           'update' => ['name' => ['sometimes', 'string']],
 *       ];
 *   }
 *
 * **Merge order:** `actionsRules()` provides the base; method-based rules take
 * priority and will override any overlapping keys.
 *
 * ## Defining authorization
 *
 * Define a protected method named `{action}Auth()` returning a bool:
 *
 *   protected function storeAuth(): bool
 *   {
 *       return $this->user()->can('create-posts');
 *   }
 *
 * If no `{action}Auth()` method exists, the request is authorized by default.
 */
abstract class BaseRequest extends FormRequest
{
    /**
     * Dispatches authorization to a method named `{action}Auth()` if it exists.
     * Falls back to `true` (authorized) when no such method is defined.
     */
    public function authorize(): bool
    {
        $method = $this->methodNameFor('Auth');

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        return true;
    }

    /**
     * Resolves validation rules for the current route action.
     *
     * Rules from `actionsRules()` are used as the base, then merged with any
     * rules returned by a `{action}Rules()` method, which takes priority.
     */
    public function rules(): array
    {
        $action = $this->getRouteAction();
        $actionsRules = $this->actionsRules();

        // Start with the base rules defined in actionsRules()
        $rules = array_key_exists($action, $actionsRules)
            ? $this->getActionRule($action)
            : [];

        // Method-based rules (e.g. storeRules()) take priority, overriding keys from actionsRules()
        $method = $this->methodNameFor('Rules');

        if (method_exists($this, $method)) {
            $rules = array_merge($rules, $this->$method());
        }

        return $rules;
    }

    /**
     * Default rules applied to all index (listing) actions.
     * Supports pagination and sorting out of the box.
     * Override in your subclass to customise.
     */
    protected function indexRules(): array
    {
        return [
            'sort' => ['string'],
            'per_page' => ['integer', 'min:1', 'max:100'],
            'with_pages' => ['boolean'],
            'pagination_type' => ['string', 'in:paginate,none,simple'],
        ];
    }

    /**
     * Builds the method name for the current route action by appending a suffix.
     * e.g. action "store" + ending "Rules" → "storeRules"
     */
    protected function methodNameFor(string $ending): string
    {
        return $this->getRouteAction().$ending;
    }

    /**
     * Resolves the current route action name (the controller method, e.g. "store").
     * Returns an empty string for closure routes or non-standard action strings,
     * which safely prevents any method or action lookup from matching.
     */
    protected function getRouteAction(): string
    {
        $action = Route::currentRouteAction();

        if (! $action || ! str_contains($action, '@')) {
            return '';
        }

        return (string) Str::of($action)->afterLast('@');
    }

    /**
     * Define the validation rules for each controller action.
     *
     * Return an array keyed by action name. Values can be:
     *   - A plain array of rules
     *   - A closure returning an array of rules (evaluated lazily)
     *
     * Example:
     *   return [
     *       'store'  => fn() => ['email' => ['required', Rule::unique('users')]],
     *       'update' => ['name' => ['sometimes', 'string']],
     *   ];
     */
    abstract protected function actionsRules(): array;

    /**
     * Retrieves and resolves the rules for a given action.
     * Closures are called lazily so expensive rules are only initialised when needed.
     */
    protected function getActionRule(string $action): array
    {
        $rule = $this->actionsRules()[$action] ?? [];

        return is_callable($rule) ? $rule() : $rule;
    }
}
