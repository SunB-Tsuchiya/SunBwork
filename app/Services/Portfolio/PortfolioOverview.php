<?php

namespace App\Services\Portfolio;

use App\Models\Department;
use App\Models\Portfolio\PortfolioCollection;
use App\Models\Portfolio\PortfolioItem;
use App\Models\Portfolio\PortfolioPermission;
use App\Models\User;

/**
 * 管理画面「メンバーのポートフォリオ」タブの、登録者カードのデータ。
 *
 * 対象: 閲覧できる SB メンバー（PortfolioAccess::viewableMembersQuery）のうち、ポートフォリオを使える人
 *   （スイッチ ON ＝ PortfolioAccess::switchedOn と同じ規則。Admin は未設定なら ON）か、すでに作品を持っている人。
 * 並び順は、申請者管理と同じ「雇用区分 → 役職（設定順）→ 氏名」（MemberList）。
 * 作品が 1 つでもある人は `members`（サムネイル付きのカード）、1 つも無い人は `withoutWorks`（名前だけ）に分ける。
 */
class PortfolioOverview
{
    /** カードに並べるサムネイルの最大数 */
    public const PREVIEW_LIMIT = 4;

    public static function build(User $actor): array
    {
        $query = PortfolioAccess::viewableMembersQuery($actor);
        if ($query === null) {
            return ['members' => [], 'withoutWorks' => [], 'departments' => []];
        }

        $users = MemberList::sort(
            $query->with(['department:id,name', 'positionTitle:id,name,sort_order'])->get()
        );
        $ids = $users->pluck('id');

        // 作品（本数が多くならない想定のため、まとめて取って PHP 側で振り分ける）
        $items = PortfolioItem::whereIn('user_id', $ids)
            ->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'user_id', 'collection_id', 'type', 'status', 'thumb_path', 'updated_at'])
            ->groupBy('user_id');
        $covers = PortfolioCollection::whereIn('user_id', $ids)
            ->whereNotNull('cover_item_id')->orderBy('sort_order')->orderBy('id')
            ->get(['user_id', 'cover_item_id'])->groupBy('user_id');
        $permissions = PortfolioPermission::whereIn('user_id', $ids)->pluck('enabled', 'user_id');

        $members = [];
        $withoutWorks = [];

        foreach ($users as $user) {
            $mine = $items->get($user->id, collect());

            // ポートフォリオを使えない人（スイッチ OFF で作品も無い人）は出さない
            $usable = $permissions->has($user->id)
                ? (bool) $permissions[$user->id]
                : $user->isAdmin();
            if ($mine->isEmpty() && ! $usable && ! $user->isSuperAdmin()) {
                continue;
            }
            // 会社・部署が未設定の SuperAdmin（管理用アカウント）は、作品が無ければ出さない
            if ($mine->isEmpty() && $user->isSuperAdmin() && ! $user->department_id) {
                continue;
            }

            $row = MemberList::row($user) + [
                'role_label' => match ($user->user_role) {
                    'superadmin' => 'SuperAdmin',
                    'admin' => 'Admin',
                    default => null,
                },
                'is_self' => $user->id === $actor->id,
                // 自分の編集画面（ユーザーのタブ）へ案内できるか
                'can_edit' => $user->id === $actor->id && PortfolioAccess::canEdit($actor, $user->id),
            ];

            if ($mine->isEmpty()) {
                $withoutWorks[] = $row;
                continue;
            }

            $ready = $mine->where('status', 'ready');
            $coverIds = ($covers->get($user->id) ?? collect())->pluck('cover_item_id')->all();
            // サムネイル: コレクションの表紙を先に、次に並び順（sort_order）の先頭から。サムネイルがある完成済みの作品だけ
            $withThumb = $ready->filter(fn ($i) => $i->thumb_path);
            $previews = $withThumb->filter(fn ($i) => in_array($i->id, $coverIds, true))
                ->merge($withThumb->reject(fn ($i) => in_array($i->id, $coverIds, true)))
                ->take(self::PREVIEW_LIMIT)
                ->map(fn ($i) => ['id' => $i->id, 'type' => $i->type])
                ->values();

            $members[] = $row + [
                'total' => $ready->count(),
                'counts' => [
                    'image' => $ready->where('type', 'image')->count(),
                    'pdf' => $ready->where('type', 'pdf')->count(),
                    'video' => $ready->where('type', 'video')->count(),
                ],
                'more' => max(0, $withThumb->count() - self::PREVIEW_LIMIT),
                'previews' => $previews,
                // アップ中・変換中・失敗のまま残った作品（Admin は直せないので、本人に確認してもらう目印にする）
                'problem' => [
                    'uploading' => $mine->whereIn('status', ['uploading', 'processing'])->count(),
                    'failed' => $mine->where('status', 'failed')->count(),
                ],
                'updated_at' => $mine->max('updated_at')?->toIso8601String(),
            ];
        }

        $deptIds = collect($members)->merge($withoutWorks)->pluck('department_id')->filter()->unique();

        return [
            'members' => $members,
            'withoutWorks' => $withoutWorks,
            // 部署での絞り込み用（カードに出る人がいる部署だけ。並びは部署の設定順）
            'departments' => Department::whereIn('id', $deptIds)->orderBy('sort_order')->orderBy('id')->get(['id', 'name']),
        ];
    }
}
