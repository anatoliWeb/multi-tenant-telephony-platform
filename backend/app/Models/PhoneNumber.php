<?php

namespace App\Models;

use App\Enums\PhoneNumbers\PhoneNumberAssignmentStatus;
use App\Enums\PhoneNumbers\PhoneNumberStatus;
use App\Enums\PhoneNumbers\PhoneNumberType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhoneNumber extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'number',
        'normalized_number',
        'display_number',
        'type',
        'status',
        'assignment_status',
        'assigned_user_id',
        'is_primary',
        'provider_name',
        'provider_reference',
        'country_code',
        'capabilities',
        'metadata',
        'primary_assignment_key',
        'purchased_at',
        'activated_at',
        'released_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'type' => PhoneNumberType::class,
        'status' => PhoneNumberStatus::class,
        'assignment_status' => PhoneNumberAssignmentStatus::class,
        'is_primary' => 'boolean',
        'capabilities' => 'array',
        'metadata' => 'array',
        'purchased_at' => 'datetime',
        'activated_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function outboundCallLogs(): HasMany
    {
        return $this->hasMany(CallLog::class, 'caller_phone_number_id');
    }

    public function inboundCallLogs(): HasMany
    {
        return $this->hasMany(CallLog::class, 'callee_phone_number_id');
    }
}
