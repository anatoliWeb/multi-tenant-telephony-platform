<?php

use App\Enums\Rbac\PermissionScope;
use App\Enums\Rbac\RoleScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->string('scope', 32)->default(RoleScope::Platform->value)->after('name');
            $table->string('scope_reference', 64)->default(RoleScope::Platform->value)->after('scope');
            $table->uuid('tenant_id')->nullable()->after('scope_reference');
            $table->boolean('is_system')->default(false)->after('description');
            $table->boolean('is_protected')->default(false)->after('is_system');
        });

        Schema::table('permissions', function (Blueprint $table): void {
            $table->string('scope', 32)->default(PermissionScope::Platform->value)->after('name');
            $table->string('scope_reference', 64)->default(PermissionScope::Platform->value)->after('scope');
        });

        Schema::table('role_user', function (Blueprint $table): void {
            $table->string('scope_reference', 64)->default(RoleScope::Platform->value)->after('role_id');
            $table->uuid('tenant_id')->nullable()->after('scope_reference');
        });

        DB::table('roles')->update([
            'scope' => RoleScope::Platform->value,
            'scope_reference' => RoleScope::Platform->value,
            'is_system' => true,
            'is_protected' => false,
        ]);

        DB::table('permissions')->update([
            'scope' => PermissionScope::Platform->value,
            'scope_reference' => PermissionScope::Platform->value,
        ]);

        DB::table('role_user')->update([
            'scope_reference' => RoleScope::Platform->value,
        ]);

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropUnique('roles_name_unique');
            $table->unique(['scope_reference', 'name'], 'roles_scope_reference_name_unique');
            $table->index(['scope', 'tenant_id'], 'roles_scope_tenant_idx');
        });

        Schema::table('permissions', function (Blueprint $table): void {
            $table->dropUnique('permissions_name_unique');
            $table->unique(['scope', 'name'], 'permissions_scope_name_unique');
            $table->index(['scope'], 'permissions_scope_idx');
        });

        Schema::table('role_user', function (Blueprint $table): void {
            $table->index(['user_id'], 'role_user_user_idx');
        });

        Schema::table('role_user', function (Blueprint $table): void {
            $table->dropPrimary('role_user_primary');
            $table->primary(['user_id', 'role_id', 'scope_reference'], 'role_user_primary');
            $table->index(['tenant_id'], 'role_user_tenant_idx');
        });
    }

    public function down(): void
    {
        Schema::table('role_user', function (Blueprint $table): void {
            $table->dropPrimary('role_user_primary');
            $table->dropIndex('role_user_tenant_idx');
            $table->dropIndex('role_user_user_idx');
            $table->primary(['user_id', 'role_id'], 'role_user_primary');
            $table->dropColumn(['scope_reference', 'tenant_id']);
        });

        Schema::table('permissions', function (Blueprint $table): void {
            $table->dropUnique('permissions_scope_name_unique');
            $table->dropIndex('permissions_scope_idx');
            $table->unique('name', 'permissions_name_unique');
            $table->dropColumn(['scope', 'scope_reference']);
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropUnique('roles_scope_reference_name_unique');
            $table->dropIndex('roles_scope_tenant_idx');
            $table->unique('name', 'roles_name_unique');
            $table->dropColumn(['scope', 'scope_reference', 'tenant_id', 'is_system', 'is_protected']);
        });
    }
};
