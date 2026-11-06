<?php

namespace App\Http\Controllers\Portfolio;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Services\Portfolio\PortfolioAccess;
use App\Services\Portfolio\PortfolioData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;

/** ポートフォリオ管理画面（ヘッダー付き） */
class PortfolioController extends Controller
{
    public function manage(Request $request)
    {
        $user = $request->user();
        // ルート名の接頭辞（admin / user）。画面内リンクをロール別ルートで作るために使う
        $prefix = Str::before($request->route()->getName(), '.portfolio.');

        // 閲覧専用モード: Admin（ロール）と、admin エリアで開いた SuperAdmin。メンバーを選んで見るだけ
        // 作成・編集・削除は本人のみ（サーバー側でも拒否する）。問題のある作品は本人に修正・削除を依頼する
        $readOnly = $prefix === 'admin' || $user->isAdmin();
        $owners = $readOnly ? PortfolioAccess::ownerChoices($user) : [];
        $viewId = $readOnly ? ($owners[0]['id'] ?? null) : $user->id;

        return Inertia::render('Portfolio/Manage', [
            'initial' => PortfolioData::for($viewId ? User::findOrFail($viewId) : $user),
            'routePrefix' => $prefix,
            'readOnly' => $readOnly,
            'ownerId' => $viewId,
            'owners' => $owners,
            // 部署での絞り込み用（選択肢に含まれるメンバーがいる部署だけ。並びは部署の設定順）
            'ownerDepartments' => $owners
                ? Department::whereIn('id', collect($owners)->pluck('department_id')->filter()->unique())->orderBy('sort_order')->orderBy('id')->get(['id', 'name'])
                : [],
            // 利用メンバー設定は SuperAdmin のみ。そのロール接頭辞にルートがある場合だけ出す（無いルートを呼ぶと Ziggy エラーになる）
            'canManagePermissions' => $user->isSuperAdmin() && Route::has("{$prefix}.portfolio.permissions.index"),
        ]);
    }
}
