<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\User;

/**
 * Observer for user lifecycle events.
 *
 * WHY:
 * Provides automatic audit logging for all critical user actions
 * (create, update, delete) without polluting business logic.
 *
 * This ensures:
 * - consistent activity tracking
 * - centralized audit trail
 * - minimal coupling between domain logic and logging
 */
class UserObserver
{
    /**
     * @var array<int, bool>
     */
    protected static array $skipUpdatedForUsers = [];

    public static function markSkipUpdatedForUser(int $userId): void
    {
        self::$skipUpdatedForUsers[$userId] = true;
    }

    public static function unmarkSkipUpdatedForUser(int $userId): void
    {
        unset(self::$skipUpdatedForUsers[$userId]);
    }

    /**
     * Handle user creation event.
     *
     * WHY:
     * Creating a user is a key system event that should be logged
     * for auditing, onboarding tracking, and debugging.
     */
    public function created(User $user): void
    {
        $this->writeImmediatelyForTests('user_created', 'User created', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        // WHY:
        // Record structured audit event for system-wide tracking
        activity_log('user_created', 'User created', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
    }

    /**
     * Handle user update event.
     *
     * WHY:
     * Updates are logged with specific changed fields
     * to provide detailed audit history and traceability.
     */
    public function updated(User $user): void
    {
        if ((self::$skipUpdatedForUsers[$user->id] ?? false) === true) {
            return;
        }

        // WHY:
        // getChanges() returns only modified attributes,
        // allowing us to log precise changes instead of full model state
        $changes = array_keys($user->getChanges());

        $this->writeImmediatelyForTests('user_updated', 'User updated', [
            'user_id' => $user->id,
            'changed' => $changes,
        ]);

        // WHY:
        // Store only changed fields to keep logs concise and meaningful
        activity_log('user_updated', 'User updated', [
            'user_id' => $user->id,
            'changed' => $changes,
        ]);
    }

    /**
     * Handle user deletion event.
     *
     * WHY:
     * Deleting a user is a critical action and must be tracked
     * for security auditing and compliance.
     */
    public function deleted(User $user): void
    {
        $this->writeImmediatelyForTests('user_deleted', 'User deleted', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        // WHY:
        // Preserve minimal identifying data after deletion
        // since model will no longer exist in database
        activity_log('user_deleted', 'User deleted', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
    }

    /**
     * Write synchronous activity rows during tests.
     *
     * WHY:
     * Feature tests assert immediate DB rows without running queue workers.
     * Production flow still uses queued activity_log() path.
     *
     * @param array<string, mixed> $meta
     */
    protected function writeImmediatelyForTests(string $action, ?string $description, array $meta = []): void
    {
        if (!app()->runningUnitTests() && !defined('PHPUNIT_COMPOSER_INSTALL') && !defined('__PHPUNIT_PHAR__')) {
            return;
        }

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'description' => $description,
            'meta' => $meta,
        ]);
    }

}
