<?php

namespace App\Console\Commands;

use App\Services\WordPressSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncWordPress extends Command
{
    protected $signature = 'hub:sync-wordpress {--test : Only test the WordPress connection}';

    protected $description = 'Import member dates of birth, profile notes and staff notes from the WordPress Areterra Hub';

    public function handle(WordPressSyncService $sync): int
    {
        try {
            if ($this->option('test')) {
                $result = $sync->testConnection();
                $this->info("Connected. {$result['members_available']} member records are available.");

                return self::SUCCESS;
            }

            $result = $sync->syncMembers();
            $this->table(['Result', 'Count'], [
                ['Members received', $result['members_received']],
                ['Members created', $result['members_created']],
                ['Members updated', $result['members_updated']],
                ['Notes created', $result['notes_created']],
                ['Notes updated', $result['notes_updated']],
            ]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
