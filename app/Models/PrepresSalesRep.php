<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrepresSalesRep extends Model
{
    protected $table = 'prepress_sales_reps';

    protected $fillable = ['name', 'company'];

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(
            Department::class,
            'prepress_sales_rep_department',
            'sales_rep_id',
            'department_id'
        );
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(PrepressTicket::class, 'sales_rep_id');
    }
}
