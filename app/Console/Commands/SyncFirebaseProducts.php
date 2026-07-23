<?php

namespace App\Console\Commands;

use App\Services\FirebaseProductSyncService;
use Illuminate\Console\Command;

class SyncFirebaseProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:sync-from-firestore {--dry-run : Preview changes without writing to MySQL}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync products from Firebase Firestore to the Laravel database';

    /**
     * Execute the console command.
     */
    public function handle(FirebaseProductSyncService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $this->info($dryRun
            ? 'Previewing Firestore product sync (dry-run)...'
            : 'Starting Firestore product sync...');

        $result = $service->sync($dryRun);

        if ($result->success) {
            $this->info('✓ Sync completed successfully');
            $this->info("  Created: {$result->created}");
            $this->info("  Updated: {$result->updated}");
            $this->info("  Skipped: {$result->skipped}");
            $this->info("  Failed: {$result->failed}");

            return Command::SUCCESS;
        }

        $this->error('✗ Sync failed: '.($result->error ?? 'one or more documents could not be synchronized'));
        $this->info("  Created: {$result->created}");
        $this->info("  Updated: {$result->updated}");
        $this->info("  Skipped: {$result->skipped}");
        $this->info("  Failed: {$result->failed}");

        return Command::FAILURE;
    }
}
