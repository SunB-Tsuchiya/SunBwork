<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;

class SalesDepartmentDefinition extends Model
{
    protected $connection = 'sales';

    protected $fillable = [
        'company_id',
        'key',
        'label',
        'sort_order',
    ];
}
