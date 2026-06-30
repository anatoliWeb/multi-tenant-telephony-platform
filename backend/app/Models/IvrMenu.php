<?php

namespace App\Models;

use App\Enums\Ivr\IvrMenuStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IvrMenu extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'name',
        'slug',
        'description',
        'status',
        'greeting_text',
        'greeting_audio_path',
        'repeat_count',
        'input_timeout_seconds',
        'max_invalid_attempts',
        'timeout_action_type',
        'timeout_destination_type',
        'timeout_destination_id',
        'invalid_action_type',
        'invalid_destination_type',
        'invalid_destination_id',
        'settings',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => IvrMenuStatus::class,
        'repeat_count' => 'integer',
        'input_timeout_seconds' => 'integer',
        'max_invalid_attempts' => 'integer',
        'timeout_destination_id' => 'integer',
        'invalid_destination_id' => 'integer',
        'settings' => 'array',
        'metadata' => 'array',
    ];

    public function options(): HasMany
    {
        return $this->hasMany(IvrOption::class);
    }

    public function activeOptions(): HasMany
    {
        return $this->options()->where('is_active', true);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
