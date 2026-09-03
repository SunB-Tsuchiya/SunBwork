<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    protected $connection = 'sales';

    protected $fillable = [
        'sales_import_id',
        'order_number',
        'client_name',
        'product_name',
        'plate_date',
        'sales_year',
        'sales_month',
        'order_amount',
        'unallocated_amount',
    ];

    // JST/UTC混在バグを避けるため date-only カラムは date:Y-m-d を使う（AGENTS.md参照）
    protected $casts = [
        'plate_date' => 'date:Y-m-d',
        'sales_year' => 'integer',
        'sales_month' => 'integer',
        'order_amount' => 'decimal:2',
        // M列合計とN列受注金額の差額（隠さず保持・提示する。Codexレビュー6.2 Medium-1）
        'unallocated_amount' => 'decimal:2',
    ];

    public function salesImport()
    {
        return $this->belongsTo(SalesImport::class);
    }

    public function details()
    {
        return $this->hasMany(SalesOrderDetail::class);
    }
}
