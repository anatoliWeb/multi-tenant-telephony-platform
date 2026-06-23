<?php

namespace App\Console\Commands\Translations;

use App\Console\Commands\BaseCommand;
use App\Services\Translation\TranslationInspectionService;

class TranslationsStatsCommand extends BaseCommand
{
    protected $signature = 'translations:stats';

    protected $description = 'Display translation inventory statistics.';

    public function __construct(
        protected TranslationInspectionService $translationInspection
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->renderSummary(
            $this->translationInspection->stats(),
            'Translation Stats'
        );

        return self::SUCCESS;
    }
}
