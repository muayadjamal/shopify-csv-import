<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessImportRun;
use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/imports')->assertRedirect(route('login'));
        $this->post('/imports')->assertRedirect(route('login'));
    }

    public function test_a_user_can_upload_a_csv_which_queues_an_import(): void
    {
        Bus::fake();
        Storage::fake('local');

        $file = UploadedFile::fake()->createWithContent(
            'products.csv',
            "Handle,Title,Variant Price\nx,X,1.00\n",
        );

        $response = $this->actingAs(User::factory()->create())
            ->post('/imports', ['file' => $file]);

        $run = ImportRun::query()->sole();

        $response->assertRedirect(route('imports.show', $run));
        $response->assertSessionHas('success');

        $this->assertSame('products.csv', $run->original_filename);
        Storage::disk('local')->assertExists($run->path);

        Bus::assertDispatched(ProcessImportRun::class, fn ($job) => $job->importRun->is($run));
    }

    public function test_it_rejects_non_csv_files(): void
    {
        Bus::fake();

        $file = UploadedFile::fake()->create('document.pdf', 20, 'application/pdf');

        $this->actingAs(User::factory()->create())
            ->post('/imports', ['file' => $file])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, ImportRun::query()->count());
        Bus::assertNothingDispatched();
    }

    public function test_it_requires_a_file(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/imports', [])
            ->assertSessionHasErrors('file');
    }
}
