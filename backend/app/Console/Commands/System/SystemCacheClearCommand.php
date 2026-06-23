<?php

namespace App\Console\Commands\System;

use App\Console\Commands\BaseCommand;
use Illuminate\Support\Facades\Artisan;

class SystemCacheClearCommand extends BaseCommand
{
    protected $signature = 'system:cache-clear {--force : Skip confirmation}';

    protected $description = 'Clear application caches via optimize:clear.';

    public function handle(): int
    {
        $this->renderSection('System Cache Clear');

        if (! $this->option('force') && ! $this->confirmOrAbort('Run optimize:clear now?', false)) {
            return self::SUCCESS;
        }

        Artisan::call('optimize:clear');
        $this->line(trim(Artisan::output()));

        $this->renderSuccess('System cache clear completed.');

        return self::SUCCESS;
    }
}
