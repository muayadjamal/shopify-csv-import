<?php

declare(strict_types=1);

namespace App\Support\Shopify;

use App\Exceptions\InvalidProductRowException;
use Illuminate\Support\Facades\Validator;

/**
 * Validates a mapped Shopify product payload before it is sent to the API,
 * so obviously broken rows fail fast with a readable reason instead of a
 * cryptic API error.
 */
class ProductRowValidator
{
    /**
     * @param  array<string, mixed>  $product  Payload produced by {@see ShopifyProductMapper}.
     *
     * @throws InvalidProductRowException
     */
    public static function validate(array $product): void
    {
        $validator = Validator::make($product, [
            'title' => ['required', 'string', 'max:255'],
            'variants.0.price' => ['required', 'numeric', 'min:0'],
        ], [
            'title.required' => 'Title is required.',
            'variants.0.price.required' => 'Variant price is required.',
            'variants.0.price.numeric' => 'Variant price must be a number.',
            'variants.0.price.min' => 'Variant price cannot be negative.',
        ]);

        if ($validator->fails()) {
            throw new InvalidProductRowException(
                implode(' ', $validator->errors()->all()),
            );
        }
    }
}
