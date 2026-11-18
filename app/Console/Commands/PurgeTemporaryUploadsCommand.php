<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeTemporaryUploadsCommand extends Command
{
    protected $signature = 'documents:purge-tmp-uploads';

    protected $description = 'Purge stale temporary uploaded files older than the configured retention threshold';

    public function handle(): int
    {
        $hours = max(1, (int) config('documents.tmp_upload_retention_hours', 24));
        $cutoff = now()->subHours($hours)->getTimestamp();
        $disk = Storage::disk('tmp_uploads');
        $deleted = 0;

        foreach ($disk->allFiles() as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
                $deleted++;
            }
        }

        $this->info("Purged {$deleted} stale temporary upload file(s).");

        return self::SUCCESS;
    }
}
