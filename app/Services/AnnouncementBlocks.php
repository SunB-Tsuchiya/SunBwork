<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * お知らせの対象範囲ブロック（サン・ブレーン専用）。
 * 全社 / 本社（情報出版）/ 小台（製版・オンデマンド）。admin ロールはどのブロックにも含める。
 */
class AnnouncementBlocks
{
    public const ALL = 'all';

    /** ブロック → 所属部署名 */
    public const DEPARTMENTS = [
        'honsha' => ['情報出版'],
        'odai' => ['製版', 'オンデマンド'],
    ];

    /** ブロック選択を表示・適用するのは、送信者の所属がサン・ブレーンのときだけ */
    public static function appliesTo(User $sender): bool
    {
        $companyId = $sender->isSuperAdmin()
            ? (session('superadmin_context.company_id') ?? $sender->company_id)
            : $sender->company_id;

        return Company::whereKey($companyId)->value('company_type') === 'sunbrain';
    }

    /** リクエストの target_block を検証済みの値にする（対象外の送信者は常に全社） */
    public static function fromRequest(Request $request, User $sender): string
    {
        $block = $request->input('target_block');

        return self::appliesTo($sender) && isset(self::DEPARTMENTS[$block]) ? $block : self::ALL;
    }

    /** ユーザー検索をブロックで絞る（all は無加工） */
    public static function constrain(Builder $query, string $block): Builder
    {
        if (! isset(self::DEPARTMENTS[$block])) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($block) {
            $q->where('user_role', 'admin')
                ->orWhereHas('department', fn (Builder $d) => $d->whereIn('name', self::DEPARTMENTS[$block]));
        });
    }

    /** そのユーザーが含まれるブロック（画面での絞り込み用） */
    public static function blocksOf(User $user): array
    {
        if ($user->user_role === 'admin') {
            return array_keys(self::DEPARTMENTS);
        }
        $name = $user->department?->name;

        return array_keys(array_filter(self::DEPARTMENTS, fn ($names) => in_array($name, $names, true)));
    }
}
