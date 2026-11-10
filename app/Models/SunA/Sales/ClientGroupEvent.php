<?php

namespace App\Models\SunA\Sales;

use App\Models\SunA\Sales\Concerns\ScopedToCompany;
use Illuminate\Database\Eloquent\Model;

/** サンエー印刷 売上レポート: 名寄せの変更の記録（誰がいつ、どの得意先を統合・解除したか） */
class ClientGroupEvent extends Model
{
    use ScopedToCompany;

    const UPDATED_AT = null;

    protected $connection = 'sales';

    protected $table = 'suna_sl_client_group_events';

    protected $fillable = ['company_id', 'user_id', 'action', 'group_id', 'group_name', 'client_key', 'client_name'];
}
