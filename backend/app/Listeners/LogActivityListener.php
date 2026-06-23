<?php

namespace App\Listeners;

use App\Events\SystemActionEvent;
use App\Services\ActivityService;

/**
 * Listener responsible for logging system activity.
 *
 * WHY:
 * Decouples activity logging from business logic by using events.
 * This allows any part of the system to dispatch an event without
 * directly depending on ActivityService.
 *
 * As a result:
 * - improves separation of concerns
 * - makes logging reusable across modules
 * - simplifies future extensions (e.g. logging to external systems)
 */
class LogActivityListener
{
    /**
     * Activity service instance.
     *
     * WHY:
     * Centralized service ensures consistent logging format
     * and prevents duplication of logging logic.
     */
    protected ActivityService $activityService;

    /**
     * Inject dependencies.
     *
     * WHY:
     * Dependency injection keeps the listener testable
     * and avoids tight coupling to concrete implementations.
     */
    public function __construct(ActivityService $activityService)
    {
        $this->activityService = $activityService;
    }

    /**
     * Handle incoming system action event.
     *
     * WHY:
     * Listener reacts to domain events and records them
     * without affecting the original workflow.
     *
     * This ensures that logging failures do not break
     * the main business operation.
     */
    public function handle(SystemActionEvent $event): void
    {
        // WHY:
        // Delegating logging to a dedicated service ensures
        // consistent structure and future scalability (e.g. queues, external logs)
        $this->activityService->log(
            $event->userId,
            $event->action,
            $event->description,
            $event->meta
        );
    }
}
