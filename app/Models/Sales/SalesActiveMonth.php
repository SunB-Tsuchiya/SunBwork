<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;

class SalesActiveMonth extends Model
{
    protected $connection = 'sales';

    protected $fillable = [
        'company_id',
        'department_key',
        'sales_year',
        'sales_month',
        'sales_import_id',
        'activated_by',
        'activated_at',
    ];

    protected $casts = [
        'sales_year' => 'integer',
        'sales_month' => 'integer',
        'activated_at' => 'datetime',
    ];

    public function salesImport()
    {
        return $this->belongsTo(SalesImport::class);
    }
}
