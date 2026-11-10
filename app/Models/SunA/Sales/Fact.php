<?php

namespace App\Models\SunA\Sales;

use App\Models\SunA\Sales\Concerns\ScopedToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * サンエー印刷 売上レポート: 売上の事実（得意先 × 年(暦年) × 月）。登録済みの月だけ行がある。
 * 担当部門は年ごと（行ごと）に持つ。
 */
class Fact extends Model
{
    use ScopedToCompany;

    protected $connection = 'sales';

    protected $table = 'suna_sl_facts';

    protected $fillable = [
        'company_id', 'client_key', 'client_name', 'sales_year', 'sales_month',
        'department_key', 'section_key', 'amount', 'order_count', 'import_id',
    ];

    protected function casts(): array
    {
        return ['sales_year' => 'integer', 'sales_month' => 'integer', 'amount' => 'float', 'order_count' => 'integer'];
    }
}
