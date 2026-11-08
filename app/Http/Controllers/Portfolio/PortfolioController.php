<?php

namespace App\Http\Controllers\Portfolio;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Portfolio\PortfolioAccess;
use App\Services\Portfolio\PortfolioData;
use App\Services\Portfolio\PortfolioOverview;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * ポートフォリオの管理画面（ヘッダー付き）。
 *  - admin エリア（Admin / SuperAdmin）: タブ付き。「メンバーのポートフォリオ」（登録者のカード一覧）→ 1 人のページ（閲覧専用）、
 *    と、「利用メンバー設定」（SuperAdmin のみ。PortfolioPermissionController）。
 *  - user エリア: 自分のポートフォリオの編集画面（スイッチ ON の人）。
 */
class PortfolioController extends Controller
{
    /** user エリア: 自分のポートフォリオの編集画面 */
    public function manage(Request $request)
    {
        $user = $request->user();
        $prefix = Str::before($request->route()->getName(), '.portfolio.');

        // 自分の分を作れない Admin（スイッチ OFF）は、メンバーを閲覧する画面（admin エリア）へ案内する
        if (! PortfolioAccess::canEdit($user, $user->id)) {
            return redirect()->route('admin.portfolio.manage');
        }

        return Inertia::render('Portfolio/Manage', [
            'initial' => PortfolioData::for($user),
            'routePrefix' => $prefix,
            'readOnly' => false,
            'ownerId' => $user->id,
            'ownerName' => $user->name,
        ]);
    }

    /** admin エリア: タブ1「メンバーのポートフォリオ」（登録者のカード一覧） */
    public function overview(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Portfolio/Overview', PortfolioOverview::build($user) + [
            'routePrefix' => 'admin',
            // 利用メンバー設定のタブは SuperAdmin のみ
            'canManagePermissions' => (bool) $user->isSuperAdmin(),
            // 自分の作品を作れる人（SuperAdmin・スイッチ ON の Admin）には、自分のカードから編集画面へ案内する
            'canEditOwn' => PortfolioAccess::canEdit($user, $user->id),
        ]);
    }

    /** admin エリア: 1 人のポートフォリオ（閲覧専用）。コレクションと作品の一覧。作成・編集・削除は本人のみ */
    public function member(Request $request, User $member)
    {
        $user = $request->user();
        abort_unless(PortfolioAccess::canView($user, $member->id), 403, 'このポートフォリオは閲覧できません。');

        return Inertia::render('Portfolio/Manage', [
            'initial' => PortfolioData::for($member),
            'routePrefix' => 'admin',
            'readOnly' => true,
            'ownerId' => $member->id,
            'ownerName' => $member->name,
            // 自分自身のページなら、編集画面（ユーザーのタブ）への入口を出す
            'canEditOwn' => $member->id === $user->id && PortfolioAccess::canEdit($user, $user->id),
            'canManagePermissions' => (bool) $user->isSuperAdmin(),
        ]);
    }
}
