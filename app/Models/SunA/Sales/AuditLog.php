<?php

namespace App\Models\SunA\Sales;

use App\Models\SunA\Sales\Concerns\ScopedToCompany;
use Illuminate\Database\Eloquent\Model;

/** サンエー印刷 売上レポート: 監査ログ。context に得意先名・金額などの内容を入れない（件数・IDのみ） */
class AuditLog extends Model
{
    use ScopedToCompany;

    const UPDATED_AT = null;

    protected $connection = 'sales';

    protected $table = 'suna_sl_audit_logs';

    protected $fillable = ['company_id', 'user_id', 'action', 'target_type', 'target_id', 'context'];

    protected function casts(): array
    {
        return ['context' => 'array'];
    }
}
