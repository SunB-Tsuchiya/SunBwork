<?php

namespace App\Models\SunA\Sales;

use App\Models\SunA\Sales\Concerns\ScopedToCompany;
use Illuminate\Database\Eloquent\Model;

/** サンエー印刷 売上レポート: 取込の取り消し用の、変更前の行（取込の確定時に保存。SUNASALES_PLAN1.md §16） */
class ImportSnapshot extends Model
{
    use ScopedToCompany;

    public $timestamps = false;

    protected $connection = 'sales';

    protected $table = 'suna_sl_import_snapshots';

    protected $fillable = [
        'company_id', 'import_id', 'kind', 'fact_id', 'client_key', 'client_name', 'sales_year', 'sales_month',
        'department_key', 'section_key', 'amount', 'order_count', 'fact_import_id', 'fact_created_at', 'fact_updated_at',
    ];
}
