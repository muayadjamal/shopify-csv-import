<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductImportStatus;
use Database\Factories\ImportedProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The per-row result of importing one CSV line into Shopify.
 *
 * @property int $id
 * @property int $import_run_id
 * @property int $row_number
 * @property string|null $handle
 * @property string|null $title
 * @property string|null $sku
 * @property ProductImportStatus $status
 * @property int|null $shopify_product_id
 * @property string|null $error_message
 * @property array<string, mixed>|null $payload
 */
class ImportedProduct extends Model
{
    /** @use HasFactory<ImportedProductFactory> */
    use HasFactory;

    protected $fillable = [
        'import_run_id',
        'row_number',
        'handle',
        'title',
        'sku',
        'status',
        'shopify_product_id',
        'error_message',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductImportStatus::class,
            'row_number' => 'integer',
            'shopify_product_id' => 'integer',
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ImportRun, $this>
     */
    public function importRun(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class);
    }
}
