<?php

namespace ShadrackJm\Snippe\Exceptions;

use Exception;

class SnippeException extends Exception
{
    /**
     * The full error response from the API.
     */
    protected array $response;

    /**
     * The machine-readable API error code (e.g. "unauthorized", "validation_error").
     */
    protected ?string $errorCode;

    public function __construct(
        string $message,
        int $httpCode = 0,
        ?string $errorCode = null,
        array $response = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $httpCode, $previous);

        $this->errorCode = $errorCode;
        $this->response  = $response;
    }

    /**
     * Create an exception from a failed HTTP response.
     */
    public static function fromResponse(\Illuminate\Http\Client\Response $response): static
    {
        $body      = $response->json() ?? [];
        $message   = $body['message'] ?? $body['error'] ?? 'Unknown API error';
        $errorCode = $body['error_code'] ?? $body['code'] ?? null;

        return new static(
            message: $message,
            httpCode: $response->status(),
            errorCode: $errorCode,
            response: $body,
        );
    }

    /**
     * The machine-readable API error code.
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * The full raw API error response body.
     */
    public function getResponse(): array
    {
        return $this->response;
    }
}
