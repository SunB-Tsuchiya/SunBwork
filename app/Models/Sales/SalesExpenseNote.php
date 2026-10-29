<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;

/** 経費メモ（科目・支払先・月へのコメント）。Phase24-5 */
class SalesExpenseNote extends Model
{
    protected $connection = 'sales';

    protected $fillable = [
        'company_id',
        'fiscal_year',
        'department_key',
        'category',
        'vendor_name',
        'calendar_month',
        'body',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'fiscal_year' => 'integer',
        'calendar_month' => 'integer',
    ];
}
