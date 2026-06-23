<?php

namespace App\Console\Commands;

use App\Console\Helpers\KeyValueTableHelper;
use App\Console\Traits\InteractsWithConsoleUi;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\ProgressBar;

abstract class BaseCommand extends Command
{
    use InteractsWithConsoleUi;

    /**
     * @param array<string, scalar|null> $items
     */
    protected function renderSummary(array $items, string $title = 'Summary'): void
    {
        $this->renderSection($title);
        $this->table(['Metric', 'Value'], KeyValueTableHelper::fromAssoc($items));
    }

    protected function confirmOrAbort(string $question, bool $default = false): bool
    {
        if (! $this->confirm($question, $default)) {
            $this->renderWarning('Operation cancelled.');
            return false;
        }

        return true;
    }

    protected function withProgress(int $max, callable $callback): mixed
    {
        $bar = $this->output->createProgressBar($max);
        $bar->start();

        try {
            $result = $callback($bar);
        } finally {
            $bar->finish();
            $this->newLine();
        }

        return $result;
    }
}
