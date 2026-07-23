<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ImportStatus;
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
        $userId = request()->user()->id;
        $runs = fn () => ImportRun::query()->where('user_id', $userId);

        $ongoing = $runs()
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
                'runs' => $runs()->count(),
                'completed' => $runs()->where('status', ImportStatus::Completed)->count(),
                'processing' => $runs()->whereIn('status', [ImportStatus::Pending, ImportStatus::Processing])->count(),
                'failed' => $runs()->where('status', ImportStatus::Failed)->count(),
                'products_imported' => (int) $runs()->sum('successful_rows'),
                'failed_rows' => (int) $runs()->sum('failed_rows'),
            ],
            'ongoing' => $ongoing,
        ]);
    }
}
