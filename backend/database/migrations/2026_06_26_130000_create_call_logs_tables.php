<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id');
            $table->string('provider_id', 32);
            $table->string('provider_call_id', 128);
            $table->string('correlation_id', 64)->nullable();
            $table->string('idempotency_key', 128)->nullable();
            $table->string('direction', 24);
            $table->string('status', 24);
            $table->string('disposition', 24)->nullable();
            $table->string('from_number', 64)->nullable();
            $table->string('from_normalized_number', 32)->nullable();
            $table->string('to_number', 64)->nullable();
            $table->string('to_normalized_number', 32)->nullable();
            $table->unsignedBigInteger('caller_user_id')->nullable();
            $table->unsignedBigInteger('callee_user_id')->nullable();
            $table->unsignedBigInteger('caller_extension_id')->nullable();
            $table->unsignedBigInteger('callee_extension_id')->nullable();
            $table->unsignedBigInteger('caller_phone_number_id')->nullable();
            $table->unsignedBigInteger('callee_phone_number_id')->nullable();
            $table->unsignedBigInteger('caller_contact_id')->nullable();
            $table->unsignedBigInteger('callee_contact_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ringing_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('ringing_seconds')->default(0);
            $table->unsignedInteger('talk_seconds')->default(0);
            $table->unsignedInteger('billable_seconds')->default(0);
            $table->unsignedInteger('total_seconds')->default(0);
            $table->string('hangup_cause', 64)->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->string('failure_message', 255)->nullable();
            $table->string('billing_status', 24)->default('unrated');
            $table->timestamp('rated_at')->nullable();
            $table->string('currency', 3)->nullable();
            $table->decimal('cost_amount', 12, 4)->nullable();
            $table->boolean('recording_available')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('caller_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('callee_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('caller_extension_id')->references('id')->on('extensions')->nullOnDelete();
            $table->foreign('callee_extension_id')->references('id')->on('extensions')->nullOnDelete();
            $table->foreign('caller_phone_number_id')->references('id')->on('phone_numbers')->nullOnDelete();
            $table->foreign('callee_phone_number_id')->references('id')->on('phone_numbers')->nullOnDelete();
            $table->foreign('caller_contact_id')->references('id')->on('contacts')->nullOnDelete();
            $table->foreign('callee_contact_id')->references('id')->on('contacts')->nullOnDelete();

            $table->unique(['tenant_id', 'provider_id', 'provider_call_id']);
            $table->index(['tenant_id', 'started_at']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'direction']);
            $table->index(['tenant_id', 'caller_user_id']);
            $table->index(['tenant_id', 'callee_user_id']);
            $table->index(['tenant_id', 'from_normalized_number']);
            $table->index(['tenant_id', 'to_normalized_number']);
        });

        Schema::create('call_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id');
            $table->unsignedBigInteger('call_log_id');
            $table->string('provider_event_id', 128);
            $table->string('provider_id', 32);
            $table->string('type', 32);
            $table->timestamp('occurred_at')->nullable();
            $table->unsignedInteger('sequence')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('call_log_id')->references('id')->on('call_logs')->cascadeOnDelete();
            $table->unique(['tenant_id', 'provider_id', 'provider_event_id']);
            $table->index(['call_log_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_events');
        Schema::dropIfExists('call_logs');
    }
};
