<?php

namespace App\Models\SunA\Sales;

use App\Models\SunA\Sales\Concerns\ScopedToCompany;
use Illuminate\Database\Eloquent\Model;

/** サンエー印刷 売上レポート: 名寄せグループのメンバー（売上の行の client_key）。1つの得意先は1つのグループにだけ所属 */
class ClientGroupMember extends Model
{
    use ScopedToCompany;

    protected $connection = 'sales';

    protected $table = 'suna_sl_client_group_members';

    protected $fillable = ['company_id', 'group_id', 'client_key', 'client_name'];
}
