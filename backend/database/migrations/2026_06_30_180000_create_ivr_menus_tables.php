<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ivr_menus', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('slug', 128);
            $table->text('description')->nullable();
            $table->string('status', 32)->default('active');
            $table->text('greeting_text')->nullable();
            // Audio playback is intentionally out of scope for this foundation
            // slice, so the audio path stays nullable until a real media flow
            // is introduced by a later PBX/media integration stage.
            $table->string('greeting_audio_path')->nullable();
            $table->unsignedInteger('repeat_count')->default(1);
            $table->unsignedInteger('input_timeout_seconds')->default(5);
            $table->unsignedInteger('max_invalid_attempts')->default(3);
            $table->string('timeout_action_type', 32)->default('repeat');
            // Timeout and invalid-input destinations are stored as explicit
            // routing references so validation can stay tenant-aware instead of
            // hiding graph state inside unstructured JSON.
            $table->string('timeout_destination_type', 32)->nullable();
            $table->unsignedBigInteger('timeout_destination_id')->nullable();
            $table->string('invalid_action_type', 32)->default('repeat');
            $table->string('invalid_destination_type', 32)->nullable();
            $table->unsignedBigInteger('invalid_destination_id')->nullable();
            $table->json('settings')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // IVR menus are tenant-owned because route resolution must never
            // traverse into another tenant's IVR, queue, or ring-group graph.
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('ivr_options', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->foreignId('ivr_menu_id')->constrained('ivr_menus')->cascadeOnDelete();
            $table->string('digit', 8);
            $table->string('label');
            $table->string('destination_type', 32);
            $table->unsignedBigInteger('destination_id')->nullable();
            $table->unsignedInteger('priority')->default(1);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            // The tenant key is duplicated here to make option-level isolation
            // cheap to validate and to keep orphaned options from crossing
            // tenancy boundaries even when menus are queried by direct ID.
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'ivr_menu_id', 'digit'], 'ivr_menu_digit_unique');
            $table->index(['tenant_id', 'ivr_menu_id', 'is_active', 'priority'], 'ivr_options_menu_priority_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ivr_options');
        Schema::dropIfExists('ivr_menus');
    }
};
