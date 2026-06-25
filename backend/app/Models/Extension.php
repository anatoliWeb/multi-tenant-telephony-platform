<?php

namespace App\Models;

use App\Enums\Extensions\ExtensionProvisioningStatus;
use App\Enums\Extensions\ExtensionRegistrationStatus;
use App\Enums\Extensions\ExtensionStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Extension extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'number',
        'label',
        'status',
        'provisioning_status',
        'registration_status',
        'assigned_user_id',
        'assigned_contact_id',
        'endpoint_key',
        'provider_name',
        'provider_resource_id',
        'credential_username',
        'last_provisioned_at',
        'created_by',
        'updated_by',
        'metadata',
    ];

    protected $casts = [
        'status' => ExtensionStatus::class,
        'provisioning_status' => ExtensionProvisioningStatus::class,
        'registration_status' => ExtensionRegistrationStatus::class,
        'last_provisioned_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function assignedContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'assigned_contact_id');
    }

    public function credential(): HasOne
    {
        return $this->hasOne(ExtensionCredential::class);
    }
}
