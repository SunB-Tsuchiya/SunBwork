<?php

namespace App\Models\SunA\Sales;

use App\Models\SunA\Sales\Concerns\ScopedToCompany;
use Illuminate\Database\Eloquent\Model;

/** サンエー印刷 売上レポート: 営業部門マスタ（部と課の2段。課は第三部のみ） */
class Department extends Model
{
    use ScopedToCompany;

    protected $connection = 'sales';

    protected $table = 'suna_sl_departments';

    protected $fillable = ['company_id', 'key', 'parent_key', 'label', 'source_label', 'is_direct', 'sort_order'];

    protected function casts(): array
    {
        return ['is_direct' => 'boolean', 'sort_order' => 'integer'];
    }
}
