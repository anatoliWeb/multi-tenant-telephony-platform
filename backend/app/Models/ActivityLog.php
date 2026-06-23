<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Activity log model.
 *
 * Stores system events for audit and dashboard.
 */
class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'description',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    /**
     * Related user.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
