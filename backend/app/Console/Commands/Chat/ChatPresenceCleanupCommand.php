<?php

namespace App\Console\Commands\Chat;

use App\Console\Commands\BaseCommand;
use App\Services\Chat\ChatPresenceService;

class ChatPresenceCleanupCommand extends BaseCommand
{
    protected $signature = 'chat:presence:cleanup {--older-than-seconds=}';

    protected $description = 'Mark stale chat presence devices as inactive.';

    public function __construct(
        protected ChatPresenceService $chatPresenceService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $option = $this->option('older-than-seconds');
        $olderThanSeconds = is_numeric($option) ? (int) $option : null;

        $threshold = $olderThanSeconds ?? $this->chatPresenceService->getPresenceStaleThresholdSeconds();
        $affected = $this->chatPresenceService->cleanupStalePresence($olderThanSeconds);

        $this->renderSummary([
            'stale_threshold_seconds' => $threshold,
            'devices_marked_inactive' => $affected,
        ], 'Chat Presence Cleanup');

        return self::SUCCESS;
    }
}

