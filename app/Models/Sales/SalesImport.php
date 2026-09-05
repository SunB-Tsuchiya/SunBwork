<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;

class SalesImport extends Model
{
    protected $connection = 'sales';

    protected $fillable = [
        'company_id',
        'department_key',
        'source_type',
        'source_year',
        'source_month',
        'source_month_end',
        'version',
        'original_filename',
        'file_sha256',
        'status',
        'imported_by',
        'imported_at',
        'order_count',
        'detail_count',
        'total_amount',
        'warnings',
    ];

    protected $casts = [
        'source_year' => 'integer',
        'source_month' => 'integer',
        'source_month_end' => 'integer',
        'version' => 'integer',
        'imported_at' => 'datetime',
        'order_count' => 'integer',
        'detail_count' => 'integer',
        'total_amount' => 'decimal:2',
        'warnings' => 'array',
    ];

    public function orders()
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function activeMonths()
    {
        return $this->hasMany(SalesActiveMonth::class);
    }
}
