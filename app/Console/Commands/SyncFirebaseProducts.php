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
    protected $signature = 'products:sync-firebase';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync products from Firebase Firestore to PostgreSQL';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Firebase product sync...');

        $service = new FirebaseProductSyncService();
        $result = $service->sync();

        if ($result->success) {
            $this->info('✓ Sync completed successfully');
            $this->info("  Created: {$result->created}");
            $this->info("  Updated: {$result->updated}");
            $this->info("  Deleted: {$result->deleted}");
            return Command::SUCCESS;
        } else {
            $this->error('✗ Sync failed: ' . $result->error);
            return Command::FAILURE;
        }
    }
}
