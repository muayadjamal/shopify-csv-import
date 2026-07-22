<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Enums\ProductImportStatus;
use App\Exceptions\InvalidProductRowException;
use App\Models\ImportRun;
use App\Services\Shopify\ShopifyClient;
use App\Services\Shopify\ShopifyException;
use App\Support\Csv\ProductCsvReader;
use App\Support\Shopify\ProductRowValidator;
use App\Support\Shopify\ShopifyProductMapper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Parses an uploaded product CSV and imports every row into Shopify, attaching
 * each created product to the configured collection.
 *
 * The job is safe to retry: per-row results are keyed by row number, rows that
 * already succeeded are skipped, and run counters are recomputed from those
 * results rather than incremented.
 */
class ProcessImportRun implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 3600;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public readonly ImportRun $importRun) {}

    public function handle(ShopifyClient $shopify): void
    {
        $run = $this->importRun;

        $run->update([
            'status' => ImportStatus::Processing,
            'started_at' => $run->started_at ?? now(),
            'error_message' => null,
        ]);

        $path = Storage::disk($run->disk)->path($run->path);
        $reader = ProductCsvReader::fromPath($path);
        $run->update(['total_rows' => $reader->count()]);

        foreach ($reader->rows() as $rowNumber => $record) {
            $this->importRow($shopify, $rowNumber, $record);
            $this->refreshCounts();
        }

        $run->update([
            'status' => ImportStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, string|null>  $record
     */
    private function importRow(ShopifyClient $shopify, int $rowNumber, array $record): void
    {
        $existing = $this->importRun->products()->firstWhere('row_number', $rowNumber);

        if ($existing?->status === ProductImportStatus::Success) {
            return;
        }

        $product = ShopifyProductMapper::map($record);

        // Reuse a product created on a previous attempt so a retry never
        // creates a duplicate — it resumes at the collection step instead.
        $productId = $existing?->shopify_product_id;

        $attributes = [
            'handle' => $product['handle'] ?? null,
            'title' => $product['title'] ?? null,
            'sku' => $product['variants'][0]['sku'] ?? null,
            'shopify_product_id' => $productId,
            'payload' => $product,
        ];

        try {
            ProductRowValidator::validate($product);

            if ($productId === null) {
                $created = $shopify->createProduct($product);
                $productId = (int) ($created['id'] ?? 0);

                // Persist the id immediately: if attaching to the collection
                // fails below, the product is not orphaned or re-created.
                $attributes['shopify_product_id'] = $productId;
                $this->recordRow($rowNumber, $attributes + ['status' => ProductImportStatus::Failed]);
            }

            $shopify->addProductToCollection($productId);

            $this->recordRow($rowNumber, [
                ...$attributes,
                'status' => ProductImportStatus::Success,
                'error_message' => null,
            ]);
        } catch (InvalidProductRowException|ShopifyException $e) {
            $this->recordRow($rowNumber, [
                ...$attributes,
                'status' => ProductImportStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function recordRow(int $rowNumber, array $attributes): void
    {
        $this->importRun->products()->updateOrCreate(
            ['row_number' => $rowNumber],
            $attributes,
        );
    }

    /**
     * Recompute run counters from the persisted per-row results so progress is
     * accurate even across retries.
     */
    private function refreshCounts(): void
    {
        $this->importRun->update([
            'processed_rows' => $this->importRun->products()->count(),
            'successful_rows' => $this->importRun->products()
                ->where('status', ProductImportStatus::Success)->count(),
            'failed_rows' => $this->importRun->products()
                ->where('status', ProductImportStatus::Failed)->count(),
        ]);
    }

    /**
     * Mark the run as failed when the job ultimately gives up (e.g. an
     * unreadable file or repeated fatal errors).
     */
    public function failed(Throwable $exception): void
    {
        $this->importRun->update([
            'status' => ImportStatus::Failed,
            'error_message' => $exception->getMessage(),
            'completed_at' => now(),
        ]);
    }
}
