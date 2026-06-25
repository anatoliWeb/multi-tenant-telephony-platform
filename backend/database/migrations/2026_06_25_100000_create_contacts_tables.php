<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name');
            $table->string('company_name')->nullable();
            $table->string('job_title')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 32)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'display_name']);
            $table->index(['tenant_id', 'company_name']);
            $table->index(['tenant_id', 'created_by']);
        });

        Schema::create('contact_phones', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('label', 64)->default('work');
            $table->string('raw_number', 64);
            $table->string('normalized_number', 32);
            $table->string('extension', 32)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_sms_capable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'normalized_number']);
            $table->index(['contact_id', 'is_primary']);
            $table->index(['tenant_id', 'raw_number']);
        });

        Schema::create('contact_emails', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('label', 64)->default('work');
            $table->string('email');
            $table->string('normalized_email');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'normalized_email']);
            $table->index(['contact_id', 'is_primary']);
        });

        Schema::create('contact_tags', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'name']);
        });

        Schema::create('contact_contact_tag', function (Blueprint $table): void {
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('contact_tag_id')->constrained('contact_tags')->cascadeOnDelete();

            $table->primary(['contact_id', 'contact_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_contact_tag');
        Schema::dropIfExists('contact_tags');
        Schema::dropIfExists('contact_emails');
        Schema::dropIfExists('contact_phones');
        Schema::dropIfExists('contacts');
    }
};
