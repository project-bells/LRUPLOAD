<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DeleteRequestService;
use Illuminate\Console\Command;

class PurgeExpiredSoftDeletedDocumentsCommand extends Command
{
    protected $signature = 'documents:purge-expired-trash';

    protected $description = 'Permanently delete soft-deleted documents older than the admin-configured retention period while preserving Audit Logs';

    public function handle(DeleteRequestService $deleteRequestService): int
    {
        $days = $deleteRequestService->getSoftDeleteRetentionDays();
        if ($days <= 0) {
            $this->info('Auto-purge of soft-deleted documents is disabled (retention_days = 0).');

            return self::SUCCESS;
        }

        $count = $deleteRequestService->purgeExpiredSoftDeletedDocuments();
        $this->info("Permanently purged {$count} expired soft-deleted document(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
