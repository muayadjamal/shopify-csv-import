<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ImportStatus;
use App\Http\Requests\StoreImportRequest;
use App\Http\Resources\ImportRunResource;
use App\Jobs\ProcessImportRun;
use App\Models\ImportRun;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    /**
     * List every import run, most recent first.
     */
    public function index(): Response
    {
        $runs = ImportRun::query()
            ->latest()
            ->limit(50)
            ->get();

        return Inertia::render('imports/index', [
            'runs' => ImportRunResource::collection($runs)->resolve(),
        ]);
    }

    /**
     * Show the CSV upload form.
     */
    public function create(): Response
    {
        return Inertia::render('imports/create');
    }

    /**
     * Store the uploaded CSV and queue it for asynchronous import.
     */
    public function store(StoreImportRequest $request): RedirectResponse
    {
        $file = $request->file('file');

        $run = ImportRun::create([
            'original_filename' => $file->getClientOriginalName(),
            'disk' => 'local',
            'path' => $file->store('imports', 'local'),
            'status' => ImportStatus::Pending,
        ]);

        ProcessImportRun::dispatch($run);

        return redirect()
            ->route('imports.show', $run)
            ->with('success', 'Your file was uploaded and the import has started.');
    }

    /**
     * Show a single import run and its per-row results.
     */
    public function show(ImportRun $importRun): Response
    {
        $importRun->load(['products' => fn ($query) => $query->orderBy('row_number')]);

        return Inertia::render('imports/show', [
            'run' => (new ImportRunResource($importRun))->resolve(),
        ]);
    }

    /**
     * JSON status endpoint polled by the import detail page.
     */
    public function status(ImportRun $importRun): ImportRunResource
    {
        $importRun->load(['products' => fn ($query) => $query->orderBy('row_number')]);

        return new ImportRunResource($importRun);
    }
}
