<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtensionCredential extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'extension_id',
        'username',
        'secret_encrypted',
        'secret_hint',
        'version',
        'rotated_by',
        'rotated_at',
    ];

    protected $casts = [
        'rotated_at' => 'datetime',
    ];

    protected $hidden = [
        'secret_encrypted',
    ];

    public function extension(): BelongsTo
    {
        return $this->belongsTo(Extension::class);
    }

    public function rotatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rotated_by');
    }
}
