<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LogLevel;
use Database\Factories\ImportLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single logged event that occurred during an import run.
 *
 * @property int $id
 * @property int $import_run_id
 * @property int|null $imported_product_id
 * @property LogLevel $level
 * @property string $event
 * @property string $message
 * @property array<string, mixed>|null $context
 * @property Carbon|null $created_at
 */
class ImportLog extends Model
{
    /** @use HasFactory<ImportLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'import_run_id',
        'imported_product_id',
        'level',
        'event',
        'message',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'level' => LogLevel::class,
            'context' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ImportRun, $this>
     */
    public function importRun(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class);
    }

    /**
     * @return BelongsTo<ImportedProduct, $this>
     */
    public function importedProduct(): BelongsTo
    {
        return $this->belongsTo(ImportedProduct::class);
    }
}
