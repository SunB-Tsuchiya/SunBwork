<?php

namespace App\Models\SunA\Sales;

use App\Models\SunA\Sales\Concerns\ScopedToCompany;
use Illuminate\Database\Eloquent\Model;

/** サンエー印刷 売上レポート: 取込ごとの変更ログ（変更のあった行だけ） */
class ImportChange extends Model
{
    use ScopedToCompany;

    public $timestamps = false;

    protected $connection = 'sales';

    protected $table = 'suna_sl_import_changes';

    protected $fillable = [
        'company_id', 'import_id', 'client_key', 'client_name', 'sales_year', 'sales_month',
        'kind', 'field', 'old_value', 'new_value',
    ];
}
