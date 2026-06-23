<?php

namespace App\Console\Support;

class ConsoleFormatter
{
    public static function section(string $title): string
    {
        return sprintf('<fg=cyan;options=bold>%s</>', $title);
    }

    public static function success(string $message): string
    {
        return sprintf('<fg=green>%s</>', $message);
    }

    public static function warning(string $message): string
    {
        return sprintf('<fg=yellow>%s</>', $message);
    }

    public static function error(string $message): string
    {
        return sprintf('<fg=red>%s</>', $message);
    }
}
