<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;

class SalesExpenseLineItem extends Model
{
    protected $connection = 'sales';

    protected $fillable = [
        'sales_expense_import_id',
        'department_key',
        'category',
        'category_order',
        'vendor_name',
        'calendar_year',
        'calendar_month',
        'amount',
        'is_shared_split',
        'subtotal_group',
        'subtotal_label',
    ];

    protected $casts = [
        'calendar_year' => 'integer',
        'calendar_month' => 'integer',
        'amount' => 'decimal:2',
        'is_shared_split' => 'boolean',
        'category_order' => 'integer',
        'subtotal_group' => 'integer',
    ];

    public function import()
    {
        return $this->belongsTo(SalesExpenseImport::class, 'sales_expense_import_id');
    }
}
