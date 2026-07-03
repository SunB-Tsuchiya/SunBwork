<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPresenceStatus extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'comment',
        'updated_by_id',
        'status_source',
        'status_changed_at',
        'sort_order',
        'is_hidden',
    ];

    protected $casts = [
        'is_hidden' => 'boolean',
        'sort_order' => 'integer',
        'status_changed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }
}
