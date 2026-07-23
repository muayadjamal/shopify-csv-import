<?php

use App\Jobs\ProcessImportRun;
use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

test('guests are redirected to login', function () {
    $this->get('/imports')->assertRedirect(route('login'));
    $this->post('/imports')->assertRedirect(route('login'));
});

test('a user can upload a csv which queues an import', function () {
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

    expect($run->original_filename)->toBe('products.csv');
    Storage::disk('local')->assertExists($run->path);

    Bus::assertDispatched(ProcessImportRun::class, fn ($job) => $job->importRun->is($run));
});

test('it rejects non-csv files', function () {
    Bus::fake();

    $file = UploadedFile::fake()->create('document.pdf', 20, 'application/pdf');

    $this->actingAs(User::factory()->create())
        ->post('/imports', ['file' => $file])
        ->assertSessionHasErrors('file');

    expect(ImportRun::query()->count())->toBe(0);
    Bus::assertNothingDispatched();
});

test('it requires a file', function () {
    $this->actingAs(User::factory()->create())
        ->post('/imports', [])
        ->assertSessionHasErrors('file');
});
