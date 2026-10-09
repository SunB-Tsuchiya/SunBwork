<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;

class SalesExpenseImport extends Model
{
    protected $connection = 'sales';

    protected $fillable = [
        'company_id',
        'fiscal_year',
        'version',
        'original_filename',
        'file_sha256',
        'status',
        'imported_by',
        'imported_at',
        'category_count',
        'line_item_count',
        'total_amount',
        'warnings',
    ];

    protected $casts = [
        'fiscal_year' => 'integer',
        'version' => 'integer',
        'imported_at' => 'datetime',
        'category_count' => 'integer',
        'line_item_count' => 'integer',
        'total_amount' => 'decimal:2',
        'warnings' => 'array',
    ];

    public function lineItems()
    {
        return $this->hasMany(SalesExpenseLineItem::class);
    }
}
