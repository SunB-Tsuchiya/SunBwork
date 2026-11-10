<?php

namespace App\Models\SunA\Sales;

use App\Models\SunA\Sales\Concerns\ScopedToCompany;
use Illuminate\Database\Eloquent\Model;

/** サンエー印刷 売上レポート: 取込1回（ファイル1つ）の記録。ファイル自体は保存しない */
class Import extends Model
{
    use ScopedToCompany;

    protected $connection = 'sales';

    protected $table = 'suna_sl_imports';

    protected $fillable = [
        'company_id', 'format', 'original_filename', 'file_sha256', 'status', 'imported_by', 'imported_at',
        'year_min', 'year_max', 'row_count', 'added_count', 'changed_count', 'removed_count', 'unchanged_count',
        'replace_years', 'warnings', 'undone_at', 'undone_by',
    ];

    protected function casts(): array
    {
        return ['imported_at' => 'datetime', 'undone_at' => 'datetime', 'replace_years' => 'array', 'warnings' => 'array'];
    }
}
