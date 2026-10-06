<?php

namespace App\Console\Commands;

use App\Support\AutomationEngine;
use Illuminate\Console\Command;

class RunAutomations extends Command
{
    protected $signature = 'hub:run-automations';
    protected $description = 'Run scheduled Areterra automation recipes';

    public function handle(AutomationEngine $engine): int
    {
        $this->info('Scanned '.$engine->scanScheduled().' eligible records.');
        return self::SUCCESS;
    }
}
