<?php

namespace App\Http\Controllers\Portfolio;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Portfolio\PortfolioAccess;
use App\Services\Portfolio\PortfolioData;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * お客様に見せる閲覧ページ（別タブ・ヘッダーなしの独立レイアウト）。
 * 本人のポートフォリオを開く。Admin / SuperAdmin は owner_id で SB メンバー分を閲覧できる（閲覧のみ）。
 * 管理用の操作（追加・編集・削除）は一切持たない。完了（ready）した作品だけを渡す。
 */
class ViewController extends Controller
{
    public function show(Request $request)
    {
        $owner = User::findOrFail(PortfolioAccess::viewOwnerId($request));
        $data = PortfolioData::for($owner);

        $items = collect($data['items'])->where('status', 'ready')->values();

        return Inertia::render('Portfolio/View', [
            'profile' => [
                'accent_color' => $data['profile']['accent_color'],
                'name' => $data['profile']['display_name'] ?: $owner->name,
                'headline' => $data['profile']['headline'],
            ],
            'collections' => $data['collections'],
            'items' => $items,
        ]);
    }
}
