<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClerkEvent extends Model
{
    protected $fillable = [
        'company_id', 'user_id', 'title', 'description', 'starts_at', 'ends_at', 'all_day',
        'color_key', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'all_day'      => 'boolean',
            'starts_at'    => 'datetime',
            'ends_at'      => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
