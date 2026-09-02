<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;

class SalesAuditLog extends Model
{
    protected $connection = 'sales';

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'target_type',
        'target_id',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];
}
