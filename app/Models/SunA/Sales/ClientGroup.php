<?php

namespace App\Models\SunA\Sales;

use App\Models\SunA\Sales\Concerns\ScopedToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** サンエー印刷 売上レポート: 得意先の名寄せグループ（統合後の表示名）。SUNASALES_PLAN1.md §17 */
class ClientGroup extends Model
{
    use ScopedToCompany;

    protected $connection = 'sales';

    protected $table = 'suna_sl_client_groups';

    protected $fillable = ['company_id', 'name'];

    public function members(): HasMany
    {
        return $this->hasMany(ClientGroupMember::class, 'group_id');
    }
}
