<?php

namespace App\Console\Commands\Translations;

use App\Console\Commands\BaseCommand;
use App\Services\Translation\TranslationExportService;

class TranslationsExportCommand extends BaseCommand
{
    protected $signature = 'translations:export
        {--locale=* : Export specific locale(s)}
        {--path=storage/app/translations : Output directory}';

    protected $description = 'Export runtime translations using unified frontend-ready JSON contract.';

    public function __construct(
        protected TranslationExportService $translationExportService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->renderSection('Translations Export');

        $exports = $this->translationExportService->export(
            (array) $this->option('locale'),
            (string) $this->option('path')
        );

        if (empty($exports)) {
            $this->renderWarning('No locales found in system_translations.');
            return self::SUCCESS;
        }

        foreach ($exports as $export) {
            $this->line("- [{$export['locale']}] {$export['path']}");
        }

        $this->renderSuccess('Translation export completed (json foundation).');

        return self::SUCCESS;
    }
}
