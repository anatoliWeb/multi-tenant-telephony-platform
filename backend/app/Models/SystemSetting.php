<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hierarchical runtime platform setting.
 *
 * WHY THIS MODEL EXISTS:
 * Settings are stored as data instead of hardcoded config values so the platform
 * can safely evolve at runtime without deployments.
 *
 * Architecture goals:
 * - runtime configuration
 * - frontend/backend separation
 * - feature flags
 * - hierarchical overrides
 * - effective value resolution
 * - tenant-ready inheritance
 * - future user preferences
 * - dynamic admin-managed configuration
 *
 * Resolution priority example:
 *
 * user override
 *   ↓
 * permission override
 *   ↓
 * role override
 *   ↓
 * global default
 *
 * IMPORTANT:
 * This model stores raw setting records only.
 *
 * Effective value resolution should happen through:
 * - SettingsResolverService
 * - SettingsService
 *
 * NOT directly through model queries.
 */
class SystemSetting extends Model
{
    public const CHANNEL_FRONTEND = 'frontend';
    public const CHANNEL_BACKEND = 'backend';

    /*
    |--------------------------------------------------------------------------
    | Scope Constants
    |--------------------------------------------------------------------------
    |
    | These constants are used by:
    | - effective value previews
    | - inheritance resolution
    | - future tenant architecture
    | - debugging
    */

    public const SCOPE_GLOBAL = 'global';
    public const SCOPE_ROLE = 'role';
    public const SCOPE_PERMISSION = 'permission';
    public const SCOPE_USER = 'user';

    /*
    |--------------------------------------------------------------------------
    | Typed Setting Constants
    |--------------------------------------------------------------------------
    |
    | Centralized setting type declarations.
    |
    | Prevents string duplication across:
    | - services
    | - forms
    | - validation
    | - API resources
    */

    public const TYPE_STRING = 'string';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_INTEGER = 'integer';
    public const TYPE_FLOAT = 'float';
    public const TYPE_JSON = 'json';
    public const TYPE_ARRAY = 'array';

    public const DEFAULT_GROUP = 'general';
    public const DEFAULT_PRIORITY = 100;

    /** @var array<int, string> */
    public const CHANNELS = [
        self::CHANNEL_FRONTEND,
        self::CHANNEL_BACKEND,
    ];

    /** @var array<int, string> */
    public const SCOPES = [
        self::SCOPE_GLOBAL,
        self::SCOPE_ROLE,
        self::SCOPE_PERMISSION,
        self::SCOPE_USER,
    ];

    /** @var array<int, string> */
    public const TYPES = [
        self::TYPE_STRING,
        self::TYPE_BOOLEAN,
        self::TYPE_INTEGER,
        self::TYPE_FLOAT,
        self::TYPE_JSON,
        self::TYPE_ARRAY,
    ];

    /**
     * Mass assignable attributes.
     */
    protected $fillable = [
        'scope_user_id',
        'scope_role_id',
        'scope_permission_id',

        'key',
        'label',
        'group',
        'description',

        'type',
        'value',
        'default_value',

        'is_frontend',
        'is_backend',
        'is_public',
        'is_encrypted',

        'priority',
        'inheritance_source',

        'is_active',
        'is_system',

        'created_by',
        'updated_by',
    ];

    /**
     * Native attribute casting.
     */
    protected $casts = [
        'is_frontend' => 'boolean',
        'is_backend' => 'boolean',
        'is_public' => 'boolean',
        'is_encrypted' => 'boolean',

        'is_active' => 'boolean',
        'is_system' => 'boolean',

        'priority' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Only active settings.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Frontend-visible settings only.
     */
    public function scopeFrontend(Builder $query): Builder
    {
        return $query->where('is_frontend', true);
    }

    /**
     * Backend-visible settings only.
     */
    public function scopeBackend(Builder $query): Builder
    {
        return $query->where('is_backend', true);
    }

    /**
     * Public-safe settings only.
     *
     * IMPORTANT:
     * Public does NOT automatically mean exposed.
     * Exposure decisions belong to services/resources.
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Effective Scope Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Determine effective scope type.
     *
     * Used for:
     * - effective value preview UI
     * - debugging
     * - inheritance visualization
     */
    public function getScopeTypeAttribute(): string
    {
        if ($this->scope_user_id) {
            return self::SCOPE_USER;
        }

        if ($this->scope_permission_id) {
            return self::SCOPE_PERMISSION;
        }

        if ($this->scope_role_id) {
            return self::SCOPE_ROLE;
        }

        return self::SCOPE_GLOBAL;
    }

    /**
     * Whether setting acts as global default.
     */
    public function isGlobal(): bool
    {
        return ! $this->scope_user_id
            && ! $this->scope_role_id
            && ! $this->scope_permission_id;
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * User-specific override owner.
     */
    public function scopeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scope_user_id');
    }

    /**
     * Role-based override owner.
     */
    public function scopeRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'scope_role_id');
    }

    /**
     * Permission-based override owner.
     */
    public function scopePermission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'scope_permission_id');
    }

    /**
     * User who created setting.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who last updated setting.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
