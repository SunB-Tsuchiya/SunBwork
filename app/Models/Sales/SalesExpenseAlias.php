<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;

/** 経費の名寄せ対応表（別名→正式名）。Phase24 */
class SalesExpenseAlias extends Model
{
    public const KIND_CATEGORY = 'category';

    public const KIND_VENDOR = 'vendor';

    protected $connection = 'sales';

    protected $fillable = [
        'company_id',
        'kind',
        'department_key',
        'alias_name',
        'canonical_name',
        'created_by',
    ];
}
