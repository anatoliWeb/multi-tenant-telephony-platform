<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_numbers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->string('number', 32);
            $table->string('normalized_number', 32);
            $table->string('display_number', 32);
            $table->string('type', 32)->default('did');
            $table->string('status', 32)->default('available');
            $table->string('assignment_status', 32)->default('unassigned');
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('provider_name', 64)->nullable();
            $table->string('provider_reference', 128)->nullable();
            $table->string('country_code', 8)->nullable();
            $table->json('capabilities')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->string('primary_assignment_key', 64)->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('assigned_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['tenant_id', 'normalized_number']);
            $table->unique('primary_assignment_key');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'assigned_user_id']);
            $table->index(['tenant_id', 'assigned_user_id', 'is_primary']);
            $table->index(['tenant_id', 'provider_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_numbers');
    }
};
