<?php

namespace App\Events;

/**
 * System action event.
 *
 * Universal event for logging system activity.
 */
class SystemActionEvent
{
    public ?int $userId;
    public string $action;
    public ?string $description;
    public array $meta;

    public function __construct(
        ?int $userId,
        string $action,
        ?string $description = null,
        array $meta = []
    ) {
        $this->userId = $userId;
        $this->action = $action;
        $this->description = $description;
        $this->meta = $meta;
    }
}
