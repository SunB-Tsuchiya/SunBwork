<?php

namespace App\Http\Controllers\SunA\GeneralAffairs\Nencho;

use App\Http\Controllers\Controller;
use App\Models\SunA\GeneralAffairs\Nencho\Campaign;
use App\Models\SunA\GeneralAffairs\Nencho\Notice;
use App\Services\SunA\GeneralAffairs\Nencho\NoticeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 総務: 年末調整の案内（お知らせの送信・カレンダーのリマインダー）。権限 suna_staff:manage_nencho。
 */
class NoticeController extends Controller
{
    public function store(Request $request, Campaign $campaign, NoticeService $notices)
    {
        $data = $request->validate([
            'kind' => ['required', 'in:' . implode(',', array_keys(Notice::KINDS))],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
        ], [], ['title' => 'タイトル', 'content' => '本文']);
        $count = $notices->send($campaign, $data['kind'], $data['title'], $data['content'], Auth::user());

        return back()->with('success', "お知らせを{$count}名に送信しました。");
    }

    public function reminder(Request $request, Campaign $campaign, NoticeService $notices)
    {
        $data = $request->validate(['content' => ['required', 'string', 'max:500']], [], ['content' => 'リマインダーの文面']);
        $notices->upsertReminder($campaign, Auth::user(), $data['content']);

        return back()->with('success', 'カレンダーのリマインダーを設定しました（受付期間中、その会社のユーザーのカレンダー上部に表示されます）。');
    }

    public function removeReminder(Campaign $campaign, NoticeService $notices)
    {
        $notices->removeReminder($campaign);

        return back()->with('success', 'カレンダーのリマインダーを削除しました。');
    }
}
