<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ImportStatus;
use App\Enums\ProductImportStatus;
use App\Models\ImportedProduct;
use App\Models\ImportRun;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('dashboard', [
            'stats' => $this->stats()->getData(true),
        ]);
    }

    /**
     * Live dashboard metrics, polled by the dashboard page.
     */
    public function stats(): JsonResponse
    {
        $runs = ImportRun::query();

        $ongoing = ImportRun::query()
            ->whereIn('status', [ImportStatus::Pending, ImportStatus::Processing])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (ImportRun $run) => [
                'id' => $run->id,
                'original_filename' => $run->original_filename,
                'status' => $run->status->label(),
                'progress' => $run->progress(),
                'processed' => $run->processed_rows,
                'total' => $run->total_rows,
            ]);

        return response()->json([
            'totals' => [
                'runs' => (clone $runs)->count(),
                'completed' => (clone $runs)->where('status', ImportStatus::Completed)->count(),
                'processing' => (clone $runs)->whereIn('status', [ImportStatus::Pending, ImportStatus::Processing])->count(),
                'failed' => (clone $runs)->where('status', ImportStatus::Failed)->count(),
                'products_imported' => ImportedProduct::where('status', ProductImportStatus::Success)->count(),
                'failed_rows' => ImportedProduct::where('status', ProductImportStatus::Failed)->count(),
            ],
            'ongoing' => $ongoing,
        ]);
    }
}
