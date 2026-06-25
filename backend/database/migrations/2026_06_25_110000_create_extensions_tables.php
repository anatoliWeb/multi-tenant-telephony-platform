<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extensions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->string('number', 16);
            $table->string('label')->nullable();
            $table->string('status', 32)->default('active');
            $table->string('provisioning_status', 32)->default('pending');
            $table->string('registration_status', 32)->default('unknown');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('endpoint_key')->nullable();
            $table->string('provider_name', 64)->nullable();
            $table->string('provider_resource_id')->nullable();
            $table->string('credential_username', 64)->nullable();
            $table->timestamp('last_provisioned_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'number']);
            $table->unique(['tenant_id', 'endpoint_key']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'assigned_user_id']);
            $table->index(['tenant_id', 'assigned_contact_id']);
        });

        Schema::create('extension_credentials', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id');
            $table->foreignId('extension_id')->constrained('extensions')->cascadeOnDelete();
            $table->string('username', 64);
            $table->text('secret_encrypted');
            $table->string('secret_hint', 16)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('rotated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rotated_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'extension_id']);
            $table->index(['tenant_id', 'username']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extension_credentials');
        Schema::dropIfExists('extensions');
    }
};
