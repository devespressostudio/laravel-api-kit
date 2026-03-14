<?php

namespace Devespresso\LaravelApiKit\Exceptions;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthorisationException extends \Exception
{
    protected ?string $view;

    public function __construct(
        ?string $message = null,
        int $code = 403,
        ?string $view = null
    ) {
        parent::__construct($message ?? Response::$statusTexts[$code] ?? 'Forbidden', $code);

        $this->view = $view;
    }

    /**
     * Renders the exception as an HTTP response.
     *
     * JSON requests receive a structured error payload.
     * Non-JSON requests are rendered via a view if one is set, otherwise aborted.
     */
    public function render(Request $request): Response|View
    {
        if (! $request->acceptsJson()) {
            abort_if(! $this->view, $this->getCode(), $this->getMessage());

            return view($this->view, [
                'code' => $this->getCode(),
                'message' => $this->getMessage(),
            ]);
        }

        return response([
            'code' => $this->getCode(),
            'status' => 'error',
            'message' => $this->getMessage(),
        ], $this->getCode());
    }
}
