<?php

namespace App\Models;

use App\Enums\Contacts\ContactStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'first_name',
        'last_name',
        'display_name',
        'company_name',
        'job_title',
        'notes',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => ContactStatus::class,
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function phones(): HasMany
    {
        return $this->hasMany(ContactPhone::class)->orderByDesc('is_primary')->orderBy('id');
    }

    public function emails(): HasMany
    {
        return $this->hasMany(ContactEmail::class)->orderByDesc('is_primary')->orderBy('id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ContactTag::class, 'contact_contact_tag');
    }

    public function outboundCallLogs(): HasMany
    {
        return $this->hasMany(CallLog::class, 'caller_contact_id');
    }

    public function inboundCallLogs(): HasMany
    {
        return $this->hasMany(CallLog::class, 'callee_contact_id');
    }
}
