<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesAnalysisPermission extends Model
{
    protected $fillable = [
        'user_id',
        'enabled',
        'granted_by',
        'granted_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'granted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
