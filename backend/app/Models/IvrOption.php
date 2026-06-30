<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IvrOption extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'ivr_menu_id',
        'digit',
        'label',
        'destination_type',
        'destination_id',
        'priority',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'destination_id' => 'integer',
        'priority' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(IvrMenu::class, 'ivr_menu_id');
    }

    public function destinationMenu(): BelongsTo
    {
        return $this->belongsTo(IvrMenu::class, 'destination_id');
    }
}
