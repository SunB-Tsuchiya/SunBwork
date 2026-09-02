<?php

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;

class SalesOrderDetail extends Model
{
    protected $connection = 'sales';

    protected $fillable = [
        'sales_order_id',
        'source_row_number',
        'client_name',
        'product_name',
        'part_name',
        'category',
        'item_name',
        'progress',
        'remarks',
        'format_size',
        'color_count',
        'quantity',
        'unit_price',
        'line_amount',
        'order_amount_component',
        'plate_date',
    ];

    protected $casts = [
        'source_row_number' => 'integer',
        'plate_date' => 'date:Y-m-d',
        'color_count' => 'decimal:2',
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'line_amount' => 'decimal:2',
        'order_amount_component' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }
}
