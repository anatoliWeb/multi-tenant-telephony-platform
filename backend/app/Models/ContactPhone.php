<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactPhone extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'contact_id',
        'label',
        'raw_number',
        'normalized_number',
        'extension',
        'is_primary',
        'is_sms_capable',
        'is_active',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_sms_capable' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
