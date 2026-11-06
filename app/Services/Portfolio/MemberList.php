<?php

namespace App\Services\Portfolio;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * ポートフォリオのメンバー選択（利用メンバー設定・閲覧するメンバー）で使う、並び順と表示用の整形。
 *
 * 並び順は、申請者管理（Admin\ApplicantController）のメンバー設定と同じ。
 *   雇用区分（正社員 → 契約社員 → 派遣社員 → 業務委託）→ 役職（役員・次長・課長…の position_titles.sort_order）→ 氏名
 * 役職の順番は、ユーザー管理で設定している任意の順番（役職称号管理）に従う。役職が未設定の人は最後。
 *
 * 呼び出し側は、あらかじめ with(['department', 'positionTitle']) を読み込んでおくこと。
 */
class MemberList
{
    private const EMPLOYMENT_PRIORITY = ['regular' => 1, 'contract' => 2, 'dispatch' => 3, 'outsource' => 4];

    /** @param Collection<int, User> $users */
    public static function sort(Collection $users): Collection
    {
        return $users->sortBy([
            fn ($a, $b) => (self::EMPLOYMENT_PRIORITY[$a->employment_type ?? 'regular'] ?? 99) <=> (self::EMPLOYMENT_PRIORITY[$b->employment_type ?? 'regular'] ?? 99),
            fn ($a, $b) => ($a->positionTitle?->sort_order ?? 9999) <=> ($b->positionTitle?->sort_order ?? 9999),
            fn ($a, $b) => $a->name <=> $b->name,
        ])->values();
    }

    /** 画面に渡す、メンバー 1 人分の共通項目（申請者管理のメンバー設定と同じ項目名） */
    public static function row(User $u): array
    {
        return [
            'id'              => $u->id,
            'name'            => $u->name,
            'department'      => $u->department?->name ?? '未所属',
            'department_id'   => $u->department_id,
            'position_title'  => $u->positionTitle?->name,
            'employment_type' => $u->employment_type ?? 'regular',
        ];
    }
}
