<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ImportRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ImportRun
 */
class ImportRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_filename' => $this->original_filename,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
                'is_finished' => $this->status->isFinished(),
            ],
            'totals' => [
                'total' => $this->total_rows,
                'processed' => $this->processed_rows,
                'successful' => $this->successful_rows,
                'failed' => $this->failed_rows,
            ],
            'progress' => $this->progress(),
            'error_message' => $this->error_message,
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'products' => $this->whenLoaded(
                'products',
                fn () => ImportedProductResource::collection($this->products)->resolve(),
            ),
        ];
    }
}
