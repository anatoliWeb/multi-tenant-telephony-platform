<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates queue infrastructure tables.
 *
 * WHY:
 * These tables provide the foundation for Laravel queue processing,
 * asynchronous jobs, batching, retries, and failure tracking.
 *
 * Queue architecture enables:
 * - background processing
 * - email dispatching
 * - realtime broadcasting
 * - activity processing
 * - notifications
 * - imports/exports
 * - heavy async workloads
 *
 * IMPORTANT:
 * Queue systems are critical for scalable enterprise applications because
 * they decouple expensive operations from HTTP request lifecycle.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Jobs Queue Table
        |--------------------------------------------------------------------------
        |
        | Stores pending queue jobs waiting for workers.
        */

        Schema::create('jobs', function (Blueprint $table): void {

            /*
            |--------------------------------------------------------------------------
            | Primary Identifier
            |--------------------------------------------------------------------------
            */

            $table->id()
                ->comment('Primary unique identifier.');

            /*
            |--------------------------------------------------------------------------
            | Queue Channel
            |--------------------------------------------------------------------------
            |
            | Examples:
            | - default
            | - emails
            | - notifications
            | - broadcasts
            */

            $table->string('queue')
                ->index()
                ->comment('Queue channel name.');

            /*
            |--------------------------------------------------------------------------
            | Serialized Job Payload
            |--------------------------------------------------------------------------
            */

            $table->longText('payload')
                ->comment('Serialized queued job payload.');

            /*
            |--------------------------------------------------------------------------
            | Retry Tracking
            |--------------------------------------------------------------------------
            */

            $table->unsignedSmallInteger('attempts')
                ->comment('Number of processing attempts.');

            /*
            |--------------------------------------------------------------------------
            | Reservation Tracking
            |--------------------------------------------------------------------------
            |
            | Indicates when worker reserved the job.
            */

            $table->unsignedInteger('reserved_at')
                ->nullable()
                ->comment('Unix timestamp when job was reserved.');

            /*
            |--------------------------------------------------------------------------
            | Availability Timestamp
            |--------------------------------------------------------------------------
            */

            $table->unsignedInteger('available_at')
                ->comment('Unix timestamp when job becomes available.');

            /*
            |--------------------------------------------------------------------------
            | Creation Timestamp
            |--------------------------------------------------------------------------
            */

            $table->unsignedInteger('created_at')
                ->comment('Unix timestamp when job was created.');

            /*
            |--------------------------------------------------------------------------
            | Performance Indexes
            |--------------------------------------------------------------------------
            */

            $table->index(
                ['queue', 'reserved_at'],
                'jobs_queue_reserved_idx'
            );

            $table->index(
                ['available_at'],
                'jobs_available_at_idx'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Job Batches Table
        |--------------------------------------------------------------------------
        |
        | Stores Laravel batch processing metadata.
        */

        Schema::create('job_batches', function (Blueprint $table): void {

            /*
            |--------------------------------------------------------------------------
            | Batch Identifier
            |--------------------------------------------------------------------------
            */

            $table->string('id')
                ->primary()
                ->comment('Unique batch identifier.');

            /*
            |--------------------------------------------------------------------------
            | Batch Metadata
            |--------------------------------------------------------------------------
            */

            $table->string('name')
                ->comment('Human-readable batch name.');

            /*
            |--------------------------------------------------------------------------
            | Batch Counters
            |--------------------------------------------------------------------------
            */

            $table->integer('total_jobs')
                ->comment('Total jobs assigned to batch.');

            $table->integer('pending_jobs')
                ->comment('Remaining pending jobs.');

            $table->integer('failed_jobs')
                ->comment('Number of failed jobs.');

            /*
            |--------------------------------------------------------------------------
            | Failed Job References
            |--------------------------------------------------------------------------
            */

            $table->longText('failed_job_ids')
                ->comment('Serialized failed job identifiers.');

            /*
            |--------------------------------------------------------------------------
            | Batch Runtime Options
            |--------------------------------------------------------------------------
            */

            $table->mediumText('options')
                ->nullable()
                ->comment('Serialized batch runtime options.');

            /*
            |--------------------------------------------------------------------------
            | Lifecycle Timestamps
            |--------------------------------------------------------------------------
            */

            $table->integer('cancelled_at')
                ->nullable()
                ->comment('Unix timestamp when batch was cancelled.');

            $table->integer('created_at')
                ->comment('Unix timestamp when batch was created.');

            $table->integer('finished_at')
                ->nullable()
                ->comment('Unix timestamp when batch completed.');

            /*
            |--------------------------------------------------------------------------
            | Performance Indexes
            |--------------------------------------------------------------------------
            */

            $table->index(
                ['created_at'],
                'job_batches_created_at_idx'
            );

            $table->index(
                ['finished_at'],
                'job_batches_finished_at_idx'
            );
        });

        /*
        |--------------------------------------------------------------------------
        | Failed Jobs Table
        |--------------------------------------------------------------------------
        |
        | Stores permanently failed queue jobs.
        */

        Schema::create('failed_jobs', function (Blueprint $table): void {

            /*
            |--------------------------------------------------------------------------
            | Primary Identifier
            |--------------------------------------------------------------------------
            */

            $table->id()
                ->comment('Primary unique identifier.');

            /*
            |--------------------------------------------------------------------------
            | Failure UUID
            |--------------------------------------------------------------------------
            */

            $table->string('uuid')
                ->unique()
                ->comment('Globally unique failed job identifier.');

            /*
            |--------------------------------------------------------------------------
            | Queue Connection Metadata
            |--------------------------------------------------------------------------
            */

            $table->string('connection')
                ->comment('Queue connection name.');

            $table->string('queue')
                ->comment('Queue channel name.');

            /*
            |--------------------------------------------------------------------------
            | Serialized Job Payload
            |--------------------------------------------------------------------------
            */

            $table->longText('payload')
                ->comment('Serialized failed job payload.');

            /*
            |--------------------------------------------------------------------------
            | Exception Information
            |--------------------------------------------------------------------------
            */

            $table->longText('exception')
                ->comment('Captured exception stack trace.');

            /*
            |--------------------------------------------------------------------------
            | Failure Timestamp
            |--------------------------------------------------------------------------
            */

            $table->timestamp('failed_at')
                ->useCurrent()
                ->comment('Timestamp when job permanently failed.');

            /*
            |--------------------------------------------------------------------------
            | Performance Indexes
            |--------------------------------------------------------------------------
            */

            $table->index(
                ['failed_at'],
                'failed_jobs_failed_at_idx'
            );

            $table->index(
                ['queue'],
                'failed_jobs_queue_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');

        Schema::dropIfExists('job_batches');

        Schema::dropIfExists('failed_jobs');
    }
};
