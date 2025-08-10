<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\Diary;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $diaries = Diary::where('user_id', $user->id)
            ->where('date', '>=', now()->startOfMonth())
            ->where('date', '<=', now()->endOfMonth())
            ->get();

        // 予定一覧（当月分、日付・タイトルのみ）
        $events = \App\Models\Event::where('user_id', $user->id)
            ->where('start', '>=', now()->startOfMonth())
            ->where('start', '<=', now()->endOfMonth())
            ->get(['id', 'title', 'start']);

        return Inertia::render('Dashboard', [
            'user' => $user,
            'diaries' => $diaries,
            'events' => $events,
        ]);
    }
}
