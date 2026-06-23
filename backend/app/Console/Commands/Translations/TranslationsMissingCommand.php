<?php

namespace App\Console\Commands\Translations;

use App\Console\Commands\BaseCommand;
use App\Services\Translation\TranslationInspectionService;

class TranslationsMissingCommand extends BaseCommand
{
    protected $signature = 'translations:missing {--limit=50 : Number of missing records to display}';

    protected $description = 'Show untranslated / missing translation rows.';

    public function __construct(
        protected TranslationInspectionService $translationInspection
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->renderSection('Missing Translations');

        $rows = $this->translationInspection->missing((int) $this->option('limit'));

        if (empty($rows)) {
            $this->renderSuccess('No missing translations found.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Locale', 'Group', 'Key', 'Source', 'Auto', 'Updated'],
            array_map(static fn ($row) => [
                $row['id'],
                $row['locale'],
                $row['group'],
                $row['key'],
                $row['source'],
                $row['auto_generated'],
                $row['updated_at'],
            ], $rows)
        );

        $this->renderWarning('Found missing/untranslated entries.');

        return self::SUCCESS;
    }
}
