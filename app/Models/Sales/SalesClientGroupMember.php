<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;

class SalesClientGroupMember extends Model
{
    protected $connection = 'sales';

    protected $fillable = [
        'sales_client_group_id',
        'company_id',
        'client_name',
        'normalized_name',
    ];

    public function group()
    {
        return $this->belongsTo(SalesClientGroup::class, 'sales_client_group_id');
    }
}
