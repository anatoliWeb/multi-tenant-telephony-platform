<?php

namespace App\Console\Commands\Translations;

use App\Console\Commands\BaseCommand;
use App\Services\Translation\TranslationCacheService;

class TranslationsCacheClearCommand extends BaseCommand
{
    protected $signature = 'translations:cache-clear {--force : Skip confirmation prompt}';

    protected $description = 'Clear translation runtime/preload caches.';

    public function __construct(
        protected TranslationCacheService $translationCache
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->renderSection('Translations Cache Clear');

        if (! $this->option('force') && ! $this->confirmOrAbort('Clear translation caches now?', false)) {
            return self::SUCCESS;
        }

        $this->translationCache->flush();
        $this->renderSuccess('Translation cache cleared.');

        return self::SUCCESS;
    }
}
