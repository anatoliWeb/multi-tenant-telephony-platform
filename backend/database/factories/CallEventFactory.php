<?php

namespace Database\Factories;

use App\Enums\CallLogs\CallEventType;
use App\Models\CallEvent;
use App\Models\CallLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CallEvent>
 */
class CallEventFactory extends Factory
{
    protected $model = CallEvent::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => null,
            'call_log_id' => CallLog::factory(),
            'provider_event_id' => 'provider-event-'.Str::lower(Str::random(12)),
            'provider_id' => 'fake',
            'type' => CallEventType::CallCreated->value,
            'occurred_at' => now(),
            'sequence' => 1,
            'payload' => ['status' => 'created'],
            'created_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (CallEvent $event): void {
            if ($event->tenant_id === null && $event->callLog) {
                $event->tenant_id = $event->callLog->tenant_id;
            }
        });
    }
}
