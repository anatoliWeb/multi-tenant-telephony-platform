<?php

namespace App\Models;

use App\Enums\CallLogs\CallBillingStatus;
use App\Enums\CallLogs\CallDisposition;
use App\Enums\Telephony\TelephonyCallDirection;
use App\Enums\Telephony\TelephonyCallStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CallLog extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'provider_id',
        'provider_call_id',
        'correlation_id',
        'idempotency_key',
        'direction',
        'status',
        'disposition',
        'from_number',
        'from_normalized_number',
        'to_number',
        'to_normalized_number',
        'caller_user_id',
        'callee_user_id',
        'caller_extension_id',
        'callee_extension_id',
        'caller_phone_number_id',
        'callee_phone_number_id',
        'caller_contact_id',
        'callee_contact_id',
        'started_at',
        'ringing_at',
        'answered_at',
        'ended_at',
        'ringing_seconds',
        'talk_seconds',
        'billable_seconds',
        'total_seconds',
        'hangup_cause',
        'failure_code',
        'failure_message',
        'billing_status',
        'rated_at',
        'currency',
        'cost_amount',
        'recording_available',
        'metadata',
    ];

    protected $casts = [
        'direction' => TelephonyCallDirection::class,
        'status' => TelephonyCallStatus::class,
        'disposition' => CallDisposition::class,
        'billing_status' => CallBillingStatus::class,
        'recording_available' => 'boolean',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'ringing_at' => 'datetime',
        'answered_at' => 'datetime',
        'ended_at' => 'datetime',
        'rated_at' => 'datetime',
        'ringing_seconds' => 'integer',
        'talk_seconds' => 'integer',
        'billable_seconds' => 'integer',
        'total_seconds' => 'integer',
        'cost_amount' => 'decimal:4',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(CallEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function callerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caller_user_id');
    }

    public function calleeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'callee_user_id');
    }

    public function callerExtension(): BelongsTo
    {
        return $this->belongsTo(Extension::class, 'caller_extension_id');
    }

    public function calleeExtension(): BelongsTo
    {
        return $this->belongsTo(Extension::class, 'callee_extension_id');
    }

    public function callerPhoneNumber(): BelongsTo
    {
        return $this->belongsTo(PhoneNumber::class, 'caller_phone_number_id');
    }

    public function calleePhoneNumber(): BelongsTo
    {
        return $this->belongsTo(PhoneNumber::class, 'callee_phone_number_id');
    }

    public function callerContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'caller_contact_id');
    }

    public function calleeContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'callee_contact_id');
    }
}
