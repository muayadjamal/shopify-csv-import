<?php

declare(strict_types=1);

namespace App\Services\Shopify;

use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Raised when the Shopify Admin API returns an error response.
 */
class ShopifyException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
    ) {
        parent::__construct($message);
    }

    public static function fromResponse(Response $response): self
    {
        return new self(
            self::formatErrors($response) ?? 'Shopify request failed.',
            $response->status(),
        );
    }

    /**
     * Flatten Shopify's `errors` payload (string, list, or field => messages map)
     * into a single readable message.
     */
    private static function formatErrors(Response $response): ?string
    {
        $errors = $response->json('errors');

        if (is_string($errors)) {
            return $errors;
        }

        if (is_array($errors)) {
            $messages = [];

            foreach ($errors as $field => $value) {
                $value = is_array($value) ? implode(', ', $value) : (string) $value;
                $messages[] = is_string($field) && ! is_numeric($field)
                    ? "{$field}: {$value}"
                    : $value;
            }

            return implode('; ', $messages);
        }

        return null;
    }
}
