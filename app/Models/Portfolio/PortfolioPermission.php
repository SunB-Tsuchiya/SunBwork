<?php

namespace App\Models\Portfolio;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** ポートフォリオ機能の個人別利用許可（売上分析の許可と同じ形） */
class PortfolioPermission extends Model
{
    protected $fillable = ['user_id', 'enabled', 'granted_by', 'granted_at'];

    protected $casts = [
        'enabled' => 'boolean',
        'granted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /** 指定ユーザーが許可オンか（SuperAdmin の常時可は呼び出し側で判定する） */
    public static function isEnabledFor(int $userId): bool
    {
        return static::where('user_id', $userId)->where('enabled', true)->exists();
    }
}
