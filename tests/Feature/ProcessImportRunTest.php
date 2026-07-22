<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ImportStatus;
use App\Enums\ProductImportStatus;
use App\Jobs\ProcessImportRun;
use App\Models\ImportRun;
use App\Services\Shopify\ShopifyClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessImportRunTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'shopify.store_url' => 'https://test.myshopify.com',
            'shopify.access_token' => 'test-token',
            'shopify.api_version' => '2025-07',
            'shopify.collection_id' => 123456,
        ]);
    }

    public function test_it_imports_valid_rows_and_records_failures(): void
    {
        Http::fake([
            '*/products.json' => Http::response(['product' => ['id' => 555]], 201),
            '*/collects.json' => Http::response(['collect' => ['id' => 999]], 201),
        ]);

        $run = $this->makeRunFromFixture();

        (new ProcessImportRun($run))->handle(app(ShopifyClient::class));

        $run->refresh();

        $this->assertSame(ImportStatus::Completed, $run->status);
        $this->assertSame(3, $run->total_rows);
        $this->assertSame(3, $run->processed_rows);
        $this->assertSame(2, $run->successful_rows);
        $this->assertSame(1, $run->failed_rows);
        $this->assertNotNull($run->completed_at);

        $this->assertSame(3, $run->products()->count());

        $failed = $run->products()->where('status', ProductImportStatus::Failed)->sole();
        $this->assertSame('missing-price', $failed->handle);
        $this->assertNull($failed->shopify_product_id);
        $this->assertStringContainsString('Title is required', (string) $failed->error_message);

        $success = $run->products()->where('status', ProductImportStatus::Success)->first();
        $this->assertSame(555, $success->shopify_product_id);

        // Two successful products => two create calls and two collection attachments.
        Http::assertSentCount(4);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/products.json'));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/collects.json'));
    }

    public function test_it_is_idempotent_and_skips_already_imported_rows_on_retry(): void
    {
        Http::fake([
            '*/products.json' => Http::response(['product' => ['id' => 555]], 201),
            '*/collects.json' => Http::response(['collect' => ['id' => 999]], 201),
        ]);

        $run = $this->makeRunFromFixture();

        (new ProcessImportRun($run))->handle(app(ShopifyClient::class));
        Http::assertSentCount(4);

        // Re-running must not create the successful products again.
        (new ProcessImportRun($run))->handle(app(ShopifyClient::class));
        Http::assertSentCount(4);

        $run->refresh();
        $this->assertSame(2, $run->successful_rows);
        $this->assertSame(3, $run->products()->count());
    }

    public function test_it_resumes_at_the_collection_step_without_recreating_the_product(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('imports/one.csv', "Handle,Title,Variant Price\nx,X,1.00\n");

        $run = ImportRun::factory()->create(['disk' => 'local', 'path' => 'imports/one.csv']);

        Http::fake([
            '*/products.json' => Http::response(['product' => ['id' => 777]], 201),
            '*/collects.json' => Http::sequence()
                ->push(['errors' => 'collection temporarily unavailable'], 500)
                ->push(['collect' => ['id' => 9]], 201),
        ]);

        // First attempt: the product is created but attaching to the collection fails.
        (new ProcessImportRun($run))->handle(app(ShopifyClient::class));

        $product = $run->products()->sole();
        $this->assertSame(ProductImportStatus::Failed, $product->status);
        $this->assertSame(777, $product->shopify_product_id);

        // Retry: the product must not be created again — only the collection attach re-runs.
        (new ProcessImportRun($run))->handle(app(ShopifyClient::class));

        $product->refresh();
        $this->assertSame(ProductImportStatus::Success, $product->status);

        // 1 create + 2 collect attempts (fail then succeed) = 3 requests, no duplicate create.
        Http::assertSentCount(3);
    }

    public function test_it_marks_the_run_failed_when_the_file_is_unreadable(): void
    {
        $run = ImportRun::factory()->create([
            'disk' => 'local',
            'path' => 'imports/does-not-exist.csv',
        ]);

        $job = new ProcessImportRun($run);

        try {
            $job->handle(app(ShopifyClient::class));
        } catch (\Throwable $e) {
            $job->failed($e);
        }

        $run->refresh();
        $this->assertSame(ImportStatus::Failed, $run->status);
        $this->assertNotNull($run->error_message);
    }

    private function makeRunFromFixture(): ImportRun
    {
        Storage::fake('local');
        Storage::disk('local')->put('imports/products.csv', file_get_contents(__DIR__.'/../Fixtures/products.csv'));

        return ImportRun::factory()->create([
            'disk' => 'local',
            'path' => 'imports/products.csv',
        ]);
    }
}
