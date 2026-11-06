<?php

namespace App\Services\Portfolio;

use App\Models\Portfolio\PortfolioCollection;
use App\Models\Portfolio\PortfolioItem;
use App\Models\Portfolio\PortfolioPermission;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * ポートフォリオの権限判定（2026-11-06 ユーザー確定。役割を「作る人」と「見る人」に分ける）。
 *
 *  - 作成・編集・削除: **本人だけ**（利用許可がオンのユーザー。試用中は SuperAdmin も自分の分だけ）。
 *    Admin ロールは自分のポートフォリオを持たない＝一切書き込めない。
 *  - 閲覧: 本人に加え、SuperAdmin / SB の Admin は SB のメンバー分を閲覧できる
 *    （Admin は同じ会社のメンバーのみ。SuperAdmin は SB 全員）。問題のある作品は本人に修正・削除を依頼する運用。
 *  - サンエー印刷（general）は誰も使えない（ルート側の deny_general でも弾く）。
 */
class PortfolioAccess
{
    /** 機能そのものを使えるか（画面に入れるか） */
    public static function canUse(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        if ($user->company?->company_type === 'general') {
            return false;
        }

        return $user->isAdmin() || PortfolioPermission::isEnabledFor($user->id);
    }

    /** 他メンバーのポートフォリオを閲覧できる立場か（Admin / SuperAdmin） */
    public static function isViewer(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isAdmin();
    }

    /** $actor が $ownerId のポートフォリオを閲覧してよいか */
    public static function canView(User $actor, int $ownerId): bool
    {
        if ($actor->id === $ownerId) {
            return self::canUse($actor);
        }
        if (! self::isViewer($actor)) {
            return false;
        }

        $owner = User::with('company:id,company_type')->find($ownerId);
        if (! $owner || $owner->company?->company_type !== 'sunbrain') {
            return false;
        }

        return $actor->isSuperAdmin() || $actor->company_id === $owner->company_id;
    }

    /** $actor が $ownerId のポートフォリオを作成・編集・削除してよいか（本人のみ。Admin ロールは不可） */
    public static function canEdit(User $actor, int $ownerId): bool
    {
        return $actor->id === $ownerId && ! $actor->isAdmin() && self::canUse($actor);
    }

    /** 閲覧用: リクエストの対象オーナー（owner_id 未指定なら本人）。閲覧権限がなければ 403 */
    public static function viewOwnerId(Request $request): int
    {
        $ownerId = (int) ($request->input('owner_id') ?: $request->user()->id);
        abort_unless(self::canView($request->user(), $ownerId), 403, 'このポートフォリオは閲覧できません。');

        return $ownerId;
    }

    /** 書き込み用: 対象は常に本人。他メンバー宛の指定や Admin の書き込みは 403 */
    public static function editOwnerId(Request $request): int
    {
        $ownerId = (int) ($request->input('owner_id') ?: $request->user()->id);
        abort_unless(self::canEdit($request->user(), $ownerId), 403, '作品の追加・編集・削除はご本人のみ可能です。修正が必要な場合は本人に依頼してください。');

        return $ownerId;
    }

    /** 閲覧用に作品を取得（閲覧権限がなければ 404。存在の有無を漏らさない） */
    public static function viewableItem(Request $request, int $id): PortfolioItem
    {
        $item = PortfolioItem::findOrFail($id);
        abort_unless(self::canView($request->user(), $item->user_id), 404);

        return $item;
    }

    /** 編集用に作品を取得（見えない=404、見えるが編集不可=403） */
    public static function editableItem(Request $request, int $id): PortfolioItem
    {
        $item = self::viewableItem($request, $id);
        abort_unless(self::canEdit($request->user(), $item->user_id), 403, '作品の編集・削除はご本人のみ可能です。');

        return $item;
    }

    public static function editableCollection(Request $request, int $id): PortfolioCollection
    {
        $collection = PortfolioCollection::findOrFail($id);
        abort_unless(self::canView($request->user(), $collection->user_id), 404);
        abort_unless(self::canEdit($request->user(), $collection->user_id), 403, 'コレクションの編集・削除はご本人のみ可能です。');

        return $collection;
    }

    /**
     * 管理画面の「閲覧するメンバー」選択肢（Admin / SuperAdmin のみ。作品か利用許可を持つ SB メンバー。自分は作品がある場合のみ）。
     * 並び順と項目は、申請者管理のメンバー設定と同じ（MemberList）。部署での絞り込み用に department_id も返す。
     */
    public static function ownerChoices(User $actor): array
    {
        if (! self::isViewer($actor)) {
            return [];
        }

        $query = User::with(['department:id,name', 'positionTitle:id,name,sort_order'])
            ->whereHas('company', fn ($q) => $q->where('company_type', 'sunbrain'))
            ->where('id', '!=', $actor->isAdmin() ? $actor->id : 0) // Admin ロールは自分のポートフォリオを持たない
            ->where(function ($q) {
                $q->whereHas('portfolioItems')
                    ->orWhereIn('id', PortfolioPermission::where('enabled', true)->pluck('user_id'));
            });
        if (! $actor->isSuperAdmin()) {
            $query->where('company_id', $actor->company_id);
        }

        return MemberList::sort($query->get())
            ->map(fn (User $u) => MemberList::row($u) + [
                'label' => $u->name . ($u->id === $actor->id ? '（自分）' : ''),
            ])
            ->all();
    }
}
