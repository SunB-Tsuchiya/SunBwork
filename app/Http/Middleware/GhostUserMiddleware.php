<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class GhostUserMiddleware
{
    private const ALLOWED_ROUTES = [
        // MyJobBox
        'user.myjobbox.index',
        'user.myjobbox.past_data',
        'user.myjobbox.pending_requests',
        'user.myjobbox.show',
        'user.myjobbox.destroy',
        'user.myjobbox.assignments.chain',
        'myjobbox.assignments.complete',
        // JobBox
        'user.jobbox.index',
        'user.project_jobs.jobbox.index',
        'user.project_jobs.jobbox.show',
        'user.project_jobs.jobbox.reply',
        'api.jobbox.show',
        // 案件一覧・詳細
        'user.project_jobs.index',
        'user.project_jobs.show',
        'user.project_jobs.json',
        'user.project_jobs.progress_sheets_json',
        // 進行表
        'user.progress_sheets.show',
        // 自己割当（マイジョブ作成・進行表からのジョブ登録）
        'user.project_jobs.assignments.create',
        'user.project_jobs.assignments.store',
        'user.project_jobs.assignments.edit',
        'user.project_jobs.assignments.update',
        'user.project_jobs.progress_sheets.link_job',
        // スケジュール
        'user.project_jobs.assignments.schedule',
        'user.project_jobs.assignments.schedule.store',
        // マイジョブ作成フォーム（パラメータなし時のリダイレクト先）
        'events.create_job',
        // ゴーストセッション終了
        'coordinator.ghost.exit',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        if (!$user->is_ghost) {
            return $next($request);
        }

        $routeName = $request->route()?->getName() ?? '';

        foreach (self::ALLOWED_ROUTES as $allowed) {
            if ($routeName === $allowed || str_starts_with($routeName, rtrim($allowed, '*'))) {
                return $next($request);
            }
        }

        abort(403, 'テストユーザーはこの機能を利用できません。');
    }
}
