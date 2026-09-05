<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;

class SalesClientGroup extends Model
{
    protected $connection = 'sales';

    protected $fillable = [
        'company_id',
        'name',
        'created_by',
        'updated_by',
    ];

    public function members()
    {
        return $this->hasMany(SalesClientGroupMember::class);
    }
}
