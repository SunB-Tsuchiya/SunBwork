<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 申請機能（交通費など）を、誰が使えるか（申請者管理のスイッチ） */
class UserApplicationAccess extends Model
{
    protected $table = 'user_application_access';

    /** 申請の種類（feature_key => 表示）。申請を増やしたら、ここに足す */
    public const FEATURES = [
        'transport' => [
            'name'        => '交通費',
            'description' => '月ごとの交通費を入力・申請します。スイッチを ON にした人に、ユーザー用のタブ「申請」が出ます。',
        ],
    ];

    protected $fillable = ['user_id', 'feature_key', 'enabled', 'changed_by'];

    protected $casts = ['enabled' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** その人が使える申請の種類（キーの配列）。設定 enforce_access が false なら、全員がすべて使える（ローカルでの確認用） */
    public static function enabledKeys(User $user): array
    {
        if (!config('applications.enforce_access', true)) {
            return array_keys(self::FEATURES);
        }
        return self::where('user_id', $user->id)->where('enabled', true)
            ->pluck('feature_key')->intersect(array_keys(self::FEATURES))->values()->all();
    }

    public static function allows(User $user, string $featureKey): bool
    {
        return in_array($featureKey, self::enabledKeys($user), true);
    }
}
