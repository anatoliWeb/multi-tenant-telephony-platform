<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ring_groups', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('slug', 128);
            $table->text('description')->nullable();
            $table->string('strategy', 32)->default('simultaneous');
            $table->string('status', 32)->default('active');
            $table->unsignedInteger('ring_timeout_seconds')->default(20);
            $table->unsignedInteger('max_ring_duration_seconds')->default(120);
            $table->string('failover_destination_type', 32)->nullable();
            $table->unsignedBigInteger('failover_destination_id')->nullable();
            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'strategy']);
        });

        Schema::create('ring_group_members', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->foreignId('ring_group_id')->constrained('ring_groups')->cascadeOnDelete();
            $table->string('member_type', 32);
            $table->foreignId('extension_id')->nullable()->constrained('extensions')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('priority')->default(1);
            $table->unsignedInteger('delay_seconds')->default(0);
            $table->unsignedInteger('timeout_seconds')->default(20);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'ring_group_id', 'member_type', 'extension_id', 'user_id'], 'ring_group_member_target_unique');
            $table->index(['tenant_id', 'ring_group_id', 'is_active', 'priority'], 'ring_group_members_group_priority_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ring_group_members');
        Schema::dropIfExists('ring_groups');
    }
};
