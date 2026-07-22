<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ImportedProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ImportedProduct
 */
class ImportedProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'row_number' => $this->row_number,
            'handle' => $this->handle,
            'title' => $this->title,
            'sku' => $this->sku,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
            ],
            'shopify_product_id' => $this->shopify_product_id,
            'shopify_admin_url' => $this->shopifyAdminUrl(),
            'error_message' => $this->error_message,
        ];
    }

    private function shopifyAdminUrl(): ?string
    {
        if (! $this->shopify_product_id) {
            return null;
        }

        $store = rtrim((string) config('shopify.store_url'), '/');

        return "{$store}/admin/products/{$this->shopify_product_id}";
    }
}
