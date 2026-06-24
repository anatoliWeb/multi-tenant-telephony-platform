<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatWebhookEndpoint extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'name',
        'url',
        'secret',
        'events',
        'is_active',
        'status',
        'failure_count',
        'created_by',
        'last_used_at',
        'last_success_at',
        'last_failure_at',
        'metadata',
    ];

    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'last_success_at' => 'datetime',
        'last_failure_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'secret',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(ChatWebhookDelivery::class, 'webhook_endpoint_id');
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $field = $field ?? $this->getRouteKeyName();

        return $this->newQuery()
            ->where($field, $value)
            ->first();
    }
}
