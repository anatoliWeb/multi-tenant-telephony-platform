<?php

namespace App\Console\Traits;

use App\Console\Support\ConsoleFormatter;

trait InteractsWithConsoleUi
{
    protected function renderSection(string $title): void
    {
        $this->newLine();
        $this->line(ConsoleFormatter::section($title));
        $this->line(str_repeat('-', max(10, strlen($title))));
    }

    protected function renderSuccess(string $message): void
    {
        $this->line(ConsoleFormatter::success($message));
    }

    protected function renderWarning(string $message): void
    {
        $this->line(ConsoleFormatter::warning($message));
    }

    protected function renderError(string $message): void
    {
        $this->line(ConsoleFormatter::error($message));
    }
}
