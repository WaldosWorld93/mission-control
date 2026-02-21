<?php

namespace App\Console\Commands;

use App\Models\TaskArtifact;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ScopingQueries;
use Illuminate\Support\Facades\Storage;

class CleanupUnconfirmedArtifacts extends Command
{
    protected $signature = 'artifacts:cleanup-unconfirmed';

    protected $description = 'Delete unconfirmed artifacts older than 1 hour';

    public function handle(): int
    {
        $disk = Storage::disk(config('filesystems.artifact_disk'));

        $artifacts = TaskArtifact::withoutGlobalScopes()
            ->whereNull('confirmed_at')
            ->where('created_at', '<', now()->subHour())
            ->get();

        $count = 0;

        foreach ($artifacts as $artifact) {
            if ($artifact->storage_path && $disk->exists($artifact->storage_path)) {
                $disk->delete($artifact->storage_path);
            }

            $artifact->delete();
            $count++;
        }

        $this->info("Deleted {$count} unconfirmed artifact(s).");

        return self::SUCCESS;
    }
}
