<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_queues', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('slug', 128);
            $table->text('description')->nullable();
            $table->string('strategy', 32)->default('ring_all');
            $table->string('status', 32)->default('active');
            $table->unsignedInteger('max_wait_time_seconds')->default(300);
            $table->unsignedInteger('ring_timeout_seconds')->default(20);
            $table->unsignedInteger('retry_delay_seconds')->default(5);
            $table->unsignedInteger('max_attempts')->default(3);
            $table->string('music_on_hold', 128)->nullable();
            $table->boolean('announce_position')->default(false);
            $table->boolean('announce_estimated_wait')->default(false);
            $table->string('overflow_destination_type', 32)->nullable();
            $table->unsignedBigInteger('overflow_destination_id')->nullable();
            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'strategy']);

            // The overflow target is stored as a polymorphic routing reference so
            // future IVR, queue, and endpoint destinations can be added without
            // changing queue records or rewriting route metadata.
            $table->index(['tenant_id', 'overflow_destination_type', 'overflow_destination_id'], 'call_queues_overflow_index');
        });

        Schema::create('call_queue_members', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->foreignId('call_queue_id')->constrained('call_queues')->cascadeOnDelete();
            $table->string('member_type', 32);
            $table->unsignedBigInteger('member_id');
            $table->foreignId('extension_id')->nullable()->constrained('extensions')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('priority')->default(1);
            $table->unsignedInteger('penalty')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_paused')->default(false);
            $table->timestamp('paused_at')->nullable();
            $table->string('pause_reason', 255)->nullable();
            $table->timestamp('last_call_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            // Queue membership is tenant-scoped separately from the queue to
            // prevent cross-tenant member injection through direct IDs.
            $table->unique(['tenant_id', 'call_queue_id', 'member_type', 'member_id'], 'call_queue_member_target_unique');
            $table->index(['tenant_id', 'call_queue_id', 'is_active', 'is_paused', 'priority'], 'call_queue_member_state_index');
        });

        Schema::create('queue_member_pauses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->foreignId('call_queue_id')->constrained('call_queues')->cascadeOnDelete();
            $table->foreignId('call_queue_member_id')->constrained('call_queue_members')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('reason', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            // Pause history is kept per tenant so audit trails cannot bleed across
            // tenant boundaries even when support staff are browsing many tenants.
            $table->index(['tenant_id', 'call_queue_id', 'call_queue_member_id', 'ended_at'], 'queue_member_pauses_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_member_pauses');
        Schema::dropIfExists('call_queue_members');
        Schema::dropIfExists('call_queues');
    }
};
