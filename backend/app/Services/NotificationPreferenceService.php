<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserNotificationPreference;

class NotificationPreferenceService
{
    /**
     * @return array<string, bool>
     */
    public function defaults(): array
    {
        return [
            'system.enabled' => true,
            'realtime.enabled' => true,
            'email.enabled' => true,
            'activity.enabled' => true,
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function getForUser(User $user): array
    {
        $stored = $this->getStoredPreferences($user);

        return $this->mergeWithDefaults($stored);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, bool>
     */
    public function updateForUser(User $user, array $input): array
    {
        $allowed = array_keys($this->defaults());
        $merged = $this->getForUser($user);

        foreach ($allowed as $key) {
            if (array_key_exists($key, $input)) {
                $merged[$key] = (bool) $input[$key];
            }
        }

        UserNotificationPreference::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['preferences' => $merged],
        );

        return $merged;
    }

    public function isEnabled(User $user, string $key): bool
    {
        return (bool) ($this->getForUser($user)[$key] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getStoredPreferences(User $user): array
    {
        $record = UserNotificationPreference::query()
            ->where('user_id', $user->id)
            ->first();

        if (!$record) {
            return [];
        }

        return is_array($record->preferences) ? $record->preferences : [];
    }

    /**
     * @param array<string, mixed> $stored
     * @return array<string, bool>
     */
    protected function mergeWithDefaults(array $stored): array
    {
        $result = $this->defaults();

        foreach (array_keys($result) as $key) {
            if (array_key_exists($key, $stored)) {
                $result[$key] = (bool) $stored[$key];
            }
        }

        return $result;
    }
}

