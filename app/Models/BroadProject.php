<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BroadProject extends Model
{
    protected $fillable = [
        'company_id', 'department_id', 'name', 'client_id', 'fiscal_year', 'is_active',
    ];

    protected $casts = [
        'fiscal_year' => 'integer',
        'is_active' => 'boolean',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }
}
