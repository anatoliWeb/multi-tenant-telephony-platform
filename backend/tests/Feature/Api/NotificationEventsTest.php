<?php

namespace Tests\Feature\Api;

use App\Actions\Notifications\CreateNotificationAction;
use App\Events\Notifications\NotificationCreated;
use App\Jobs\Notifications\CreateNotificationJob;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NotificationEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_service_create_dispatches_notification_created_event(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $this->actingAs($actor, 'web');

        Event::fakeFor(function () use ($target): void {
            /** @var NotificationService $notificationService */
            $notificationService = app(NotificationService::class);

            $notificationService->createForUser(
                $target,
                'Domain notification',
                'Created from service',
                ['channel' => 'api-test']
            );

            Event::assertDispatched(NotificationCreated::class, function (NotificationCreated $event) use ($target): bool {
                return $event->notifiableId === $target->id
                    && $event->title === 'Domain notification'
                    && $event->message === 'Created from service'
                    && $event->actorId !== null;
            });
        });
    }

    public function test_notification_job_handle_dispatches_notification_created_event(): void
    {
        $target = User::factory()->create();
        $job = new CreateNotificationJob(
            userId: $target->id,
            title: 'Queued domain notification',
            message: 'Created from job',
            data: ['channel' => 'queue-test'],
        );

        Event::fakeFor(function () use ($job): void {
            $job->handle(app(CreateNotificationAction::class));

            Event::assertDispatched(NotificationCreated::class, function (NotificationCreated $event): bool {
                return $event->title === 'Queued domain notification'
                    && $event->message === 'Created from job'
                    && $event->actorId === null;
            });
        });
    }

    public function test_notification_created_event_payload_omits_sensitive_values(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $this->actingAs($actor, 'web');

        Event::fakeFor(function () use ($target): void {
            /** @var NotificationService $notificationService */
            $notificationService = app(NotificationService::class);
            $notificationService->createForUser(
                $target,
                'Audit notification',
                'Audit message',
                ['secret' => 'do-not-log'],
            );

            Event::assertDispatched(NotificationCreated::class, function (NotificationCreated $event): bool {
                return $event->title === 'Audit notification'
                    && $event->message === 'Audit message'
                    && !property_exists($event, 'token')
                    && !property_exists($event, 'plainTextToken')
                    && !property_exists($event, 'secret');
            });
        });
    }
}
