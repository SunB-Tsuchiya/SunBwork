<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispatchProfile extends Model
{
    protected $fillable = [
        'user_id',
        'agency_name',
        'contract_start',
        'contract_end',
        'notes',
        'is_hidden',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'contract_start' => 'date',
            'contract_end'   => 'date',
            'is_hidden'      => 'boolean',
            'is_active'      => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
