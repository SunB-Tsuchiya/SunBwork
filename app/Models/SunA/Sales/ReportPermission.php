<?php

namespace App\Models\SunA\Sales;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * サンエー印刷 売上レポートの利用許可（通常DB。portfolio_permissions と同形）。
 * enabled = 閲覧できる、can_import = 取込もできる。会社は users.company_id で決まる（このテーブルに company_id は無い）。
 */
class ReportPermission extends Model
{
    protected $table = 'suna_sl_report_permissions';

    protected $fillable = ['user_id', 'enabled', 'can_import', 'granted_by', 'granted_at'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'can_import' => 'boolean', 'granted_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
