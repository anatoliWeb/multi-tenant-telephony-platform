<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueMonitoringCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_queue_status_command_returns_success_and_expected_metrics(): void
    {
        $this->artisan('system:queue-status')
            ->expectsOutputToContain('Queue Diagnostics')
            ->expectsOutputToContain('queue_connection')
            ->expectsOutputToContain('failed_jobs_count')
            ->expectsOutputToContain('redis_status')
            ->assertExitCode(0);
    }
}

