<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ImportRun;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Alerts admin users, via Filament database notifications (the panel bell),
 * when an import finishes with failed rows or fails outright.
 */
class ImportFailuresNotification
{
    public static function dispatchFor(ImportRun $run): void
    {
        // Only admins can see the panel bell — notify them (oversight), not
        // every user (which would leak other users' import filenames).
        $admins = User::query()->where('is_admin', true)->get();

        if ($admins->isEmpty()) {
            return;
        }

        $body = $run->failed_rows > 0
            ? "{$run->failed_rows} row(s) failed while importing {$run->original_filename}."
            : "The import of {$run->original_filename} failed.";

        Notification::make()
            ->title('Import needs attention')
            ->body($body)
            ->danger()
            ->actions([
                Action::make('view')
                    ->url(route('filament.admin.resources.import-runs.view', $run))
                    ->markAsRead(),
            ])
            ->sendToDatabase($admins);
    }
}
