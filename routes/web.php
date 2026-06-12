<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;

// デバッグ用ルート
require __DIR__ . '/debug.php';
// チャット用ルート
require __DIR__ . '/chat.php';



// Signed attachment route (temporary signed URLs may access this without authentication)
// Re-apply the 'signed' middleware so Laravel's built-in signature validation runs
// early. The controller still performs an explicit signature check for diagnostic
// logging, but middleware will prevent invalid signatures from reaching it.
Route::get('/attachments/signed', [App\Http\Controllers\AttachmentController::class, 'stream'])
    ->name('attachments.signed');

Route::get('/', function () {
    return redirect()->route('login');
});
// Temporary public debug route to send test completion mail (remove after testing)
Route::get('/debug/events/send-test-completion', [App\Http\Controllers\EventController::class, 'sendTestCompletion'])->name('debug.events.send_test_completion');

// User Dashboard (default authenticated users)
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'ghost'])->group(function () {
    // ...既存コード...
    // チャットルームメッセージ送信
    Route::post('/chat/rooms/{id}/messages', [App\Http\Controllers\Chat\ChatController::class, 'sendRoomMessage'])->name('chat.rooms.messages.send');
    // カレンダー画面
    Route::get('/calendar', [App\Http\Controllers\CalendarController::class, 'index'])->name('calendar.index');
    // カレンダーからのイベント時間変更用
    Route::put('/events/{id}/calendar', [App\Http\Controllers\EventController::class, 'update_from_calendar'])->name('events.update_from_calendar');

    // 予定（イベント）API
    Route::get('/events', [App\Http\Controllers\EventController::class, 'index'])->name('events.index');
    // store/update are handled by the resource route declared below to avoid duplicate route names
    Route::delete('/events/{event}', [App\Http\Controllers\EventController::class, 'destroy'])->name('events.destroy');

    // 案件打合せ・外出フォーム（ClientEvent）
    Route::get('/events/client-event/create', [App\Http\Controllers\Events\ClientEventController::class, 'create'])->name('events.client-event.create');
    Route::post('/events/client-event', [App\Http\Controllers\Events\ClientEventController::class, 'store'])->name('events.client-event.store');
    Route::get('/events/client-event/{event}/edit', [App\Http\Controllers\Events\ClientEventController::class, 'edit'])->name('events.client-event.edit');
    Route::put('/events/client-event/{event}', [App\Http\Controllers\Events\ClientEventController::class, 'update'])->name('events.client-event.update');

    // 社内予定フォーム（InternalEvent）
    Route::get('/events/internal-event/create', [App\Http\Controllers\Events\InternalEventController::class, 'create'])->name('events.internal-event.create');
    Route::post('/events/internal-event', [App\Http\Controllers\Events\InternalEventController::class, 'store'])->name('events.internal-event.store');
    Route::get('/events/internal-event/{event}/edit', [App\Http\Controllers\Events\InternalEventController::class, 'edit'])->name('events.internal-event.edit');
    Route::put('/events/internal-event/{event}', [App\Http\Controllers\Events\InternalEventController::class, 'update'])->name('events.internal-event.update');

    // ユーザー割り当てジョブ一覧・詳細 (旧: assigned-projects, 新: assigned-jobs)
    Route::prefix('user/assigned-projects')->name('user.assigned-projects.')->group(function () {
        Route::get('/', [App\Http\Controllers\User\AssignedProjectController::class, 'index'])->name('index');
        Route::get('/{id}', [App\Http\Controllers\User\AssignedProjectController::class, 'show'])->name('show');
    });
    // 新しいルート名 assigned-jobs を追加 (既存コントローラを再利用)
    Route::prefix('user/assigned-jobs')->name('user.assigned-jobs.')->group(function () {
        Route::get('/', [App\Http\Controllers\User\AssignedProjectController::class, 'index'])->name('index');
        Route::get('/{id}', [App\Http\Controllers\User\AssignedProjectController::class, 'show'])->name('show');
    });

    // チャット画面: トップ-level /chat はチャットルーム一覧にリダイレクト
    Route::get('/chat', function () {
        return redirect()->route('chat.rooms.index');
    })->name('chat.index');
    // AIチャット（Bot）ページ
    Route::get('/bot/chat', function () {
        return Inertia::render('Bot/ChatBot');
    })->name('bot.chat');
    // Bot API proxy to OpenAI
    Route::post('/bot/chat', [App\Http\Controllers\Bot\BotController::class, 'chat'])->name('bot.chat.api');
    // Bot file upload & stream
    Route::post('/bot/files', [App\Http\Controllers\Bot\BotFileController::class, 'upload'])->name('bot.files.upload');
    Route::post('/bot/files/delete', [App\Http\Controllers\Bot\BotFileController::class, 'delete'])->name('bot.files.delete');
    Route::get('/bot/attachments', [App\Http\Controllers\Bot\BotFileController::class, 'stream'])->name('bot.files.stream');

    // Bot export (conversation -> file)
    Route::post('/bot/export', [App\Http\Controllers\BotExportController::class, 'export'])->name('bot.export');
    Route::get('/bot/export/download/{filename}', [App\Http\Controllers\BotExportController::class, 'download'])->name('bot.export.download');

    // AI conversation history
    Route::get('/bot/history', [App\Http\Controllers\Bot\AiHistoryController::class, 'index'])->name('bot.history.index');
    Route::get('/bot/history/{id}', [App\Http\Controllers\Bot\AiHistoryController::class, 'show'])->name('bot.history.show');
    Route::get('/bot/history/{id}/json', [App\Http\Controllers\Bot\AiHistoryController::class, 'showJson'])->name('bot.history.show.json');
    Route::post('/bot/history', [App\Http\Controllers\Bot\AiHistoryController::class, 'store'])->name('bot.history.store');
    Route::delete('/bot/history/{id}', [App\Http\Controllers\Bot\AiHistoryController::class, 'destroy'])->name('bot.history.destroy');
    Route::put('/bot/history/{id}', [App\Http\Controllers\Bot\AiHistoryController::class, 'update'])->name('bot.history.update');

    // Fetch latest summary for a conversation (used by frontend SummaryPanel)
    Route::get('/bot/conversations/{id}/summary', [App\Http\Controllers\Bot\BotController::class, 'summary'])->name('bot.conversations.summary');

    // 使い方ガイド
    Route::prefix('guide')->name('guide.')->group(function () {
        Route::get('/', [App\Http\Controllers\GuideController::class, 'index'])->name('index');
        Route::get('/user', [App\Http\Controllers\GuideController::class, 'user'])->name('user');
        Route::get('/coordinator', [App\Http\Controllers\GuideController::class, 'coordinator'])->name('coordinator');
        Route::get('/leader', [App\Http\Controllers\GuideController::class, 'leader'])->name('leader');
        Route::get('/admin', [App\Http\Controllers\GuideController::class, 'admin'])->name('admin');
        Route::get('/proof-coordinator', [App\Http\Controllers\GuideController::class, 'proofCoordinator'])->name('proof_coordinator');
    });

    // スクリプトツール
    Route::prefix('scripts')->name('scripts.')->group(function () {
        Route::get('/', [App\Http\Controllers\ScriptController::class, 'index'])->name('index');
        Route::get('/{script:slug}', [App\Http\Controllers\ScriptController::class, 'show'])->name('show');
    });

    // 更新ログ
    Route::get('/changelogs', [App\Http\Controllers\ChangelogController::class, 'index'])->name('changelogs.index');
    Route::get('/changelogs/{changelog}', [App\Http\Controllers\ChangelogController::class, 'show'])->name('changelogs.show');

    // Ziggy用: 明示的にuser.dashboardルートを追加
    Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/user/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('user.dashboard');

    // 在席管理（イルカ）
    Route::get('/presence', [App\Http\Controllers\UserPresenceController::class, 'index'])->name('presence.index');
    Route::get('/presence/statuses', [App\Http\Controllers\UserPresenceController::class, 'statuses'])->name('presence.statuses');
    Route::post('/presence/self/clear', [App\Http\Controllers\UserPresenceController::class, 'clearSelf'])->name('presence.clear_self');
    Route::get('/presence/status-update', [App\Http\Controllers\UserPresenceController::class, 'statusUpdatePage'])->name('presence.status_update');

    // 在席ボード管理（Admin・Leader）— {user} ワイルドカードより前に定義が必須
    Route::get('/presence/board-settings', [App\Http\Controllers\PresenceBoardSettingsController::class, 'index'])->name('presence.board_settings');
    Route::post('/presence/board-settings', [App\Http\Controllers\PresenceBoardSettingsController::class, 'update'])->name('presence.board_settings.update');
    Route::post('/presence/board-settings/statuses', [App\Http\Controllers\PresenceBoardSettingsController::class, 'updateStatuses'])->name('presence.board_settings.statuses');
    Route::post('/presence/board-settings/statuses/create', [App\Http\Controllers\PresenceBoardSettingsController::class, 'createStatus'])->name('presence.board_settings.statuses.create');
    Route::delete('/presence/board-settings/statuses/{statusOrder}', [App\Http\Controllers\PresenceBoardSettingsController::class, 'deleteStatus'])->name('presence.board_settings.statuses.delete');

    Route::post('/presence/{user}', [App\Http\Controllers\UserPresenceController::class, 'update'])->name('presence.update');

    // 案件確認（ユーザー向け案件一覧・詳細）
    Route::get('/user/project-jobs/json', [App\Http\Controllers\User\ProjectJobController::class, 'projectsJson'])->name('user.project_jobs.json');
    Route::get('/user/project-jobs/{projectJob}/progress-sheets-json', [App\Http\Controllers\User\ProjectJobController::class, 'progressSheetsJson'])->name('user.project_jobs.progress_sheets_json');
    Route::get('/user/project-jobs/{projectJob}/sheets-json', [App\Http\Controllers\User\ProjectJobController::class, 'sheetsJson'])->name('user.project_jobs.sheets_json');
    Route::get('/user/project-jobs', [App\Http\Controllers\User\ProjectJobController::class, 'index'])->name('user.project_jobs.index');
    Route::get('/user/project-jobs/{projectJob}', [App\Http\Controllers\User\ProjectJobController::class, 'show'])->name('user.project_jobs.show');

    // 校正機能（サン・ブレーン専用）
    Route::middleware('company_type:sunbrain')->group(function () {
        // 校正状況（ユーザー自身の依頼のみ）
        Route::get('/user/proof/status', [\App\Http\Controllers\ProofCoordinator\ProofRequestController::class, 'userProofStatus'])->name('user.proof.status');

        // 校正ジョブ（ユーザー）
        Route::get('/user/proof-jobs', [\App\Http\Controllers\User\ProofJobController::class, 'index'])->name('user.proof_jobs.index');
        Route::get('/user/proof-jobs/{proofRequest}', [\App\Http\Controllers\User\ProofJobController::class, 'show'])->name('user.proof_jobs.show');
        Route::get('/user/proof-jobs/{proofRequest}/set', [\App\Http\Controllers\User\ProofJobController::class, 'setPage'])->name('user.proof_jobs.set_page');
        Route::match(['post', 'put'], '/user/proof-jobs/{proofRequest}/set', [\App\Http\Controllers\User\ProofJobController::class, 'set'])->name('user.proof_jobs.set');
        Route::post('/user/proof-jobs/{proofRequest}/complete', [\App\Http\Controllers\User\ProofJobController::class, 'complete'])->name('user.proof_jobs.complete');
    });

    // ユーザー設定
    Route::get('/user/settings',      [App\Http\Controllers\User\UserSettingController::class, 'index'])->name('user.settings.index');
    Route::get('/user/settings/edit', [App\Http\Controllers\User\UserSettingController::class, 'edit'])->name('user.settings.edit');
    Route::put('/user/settings',      [App\Http\Controllers\User\UserSettingController::class, 'update'])->name('user.settings.update');

    // 日ごと勤務形態設定
    Route::post('/user/daily-worktypes', [App\Http\Controllers\User\UserDailyWorktypeController::class, 'store'])->name('user.daily_worktypes.store');

    // 日ごと休憩設定
    Route::post('/user/daily-breaks', [App\Http\Controllers\User\UserDailyBreakController::class, 'store'])->name('user.daily_breaks.store');

    // Coordinator JobBox - accessible at /coordinator/jobbox
    Route::get('/coordinator/jobbox', [\App\Http\Controllers\ProjectJobs\JobBoxController::class, 'global'])
        ->middleware('coordinator')
        ->name('coordinator.jobbox');

    // User-scoped jobbox: show messages where the assignment's user_id == auth id
    Route::get('/user/jobbox', [\App\Http\Controllers\ProjectJobs\JobBoxController::class, 'user'])
        ->name('user.jobbox.index');

    // JobBox: mark an assignment as completed (accessible by any authenticated user for their own assignments)
    Route::post('/coordinator/jobbox/assignments/{assignment}/complete', [\App\Http\Controllers\ProjectJobs\JobBoxController::class, 'completeAssignment'])
        ->name('user.jobbox.assignments.complete');

    // MyJobBox: user-scoped JobBox page (personal messages/assignments)
    Route::get('/myjobbox', [\App\Http\Controllers\User\MyProjectJobController::class, 'index'])->name('user.myjobbox.index');
    Route::get('/myjobbox/past-data', [\App\Http\Controllers\User\MyProjectJobController::class, 'pastData'])->name('user.myjobbox.past_data');
    Route::get('/myjobbox/pending-requests', [\App\Http\Controllers\User\MyProjectJobController::class, 'pendingRequests'])->name('user.myjobbox.pending_requests');
    Route::post('/myjobbox/assignments/{assignment}/complete', [\App\Http\Controllers\User\MyProjectJobController::class, 'completeAssignment'])->name('myjobbox.assignments.complete');
    Route::get('/myjobbox/assignments/{assignment}/chain', [\App\Http\Controllers\User\MyProjectJobController::class, 'chainAssignments'])->name('user.myjobbox.assignments.chain');
    Route::get('/myjobbox/{assignment}', [\App\Http\Controllers\User\MyProjectJobController::class, 'showAssignment'])->name('user.myjobbox.show');
    Route::delete('/myjobbox/{assignment}', [\App\Http\Controllers\User\MyProjectJobController::class, 'destroyAssignment'])->name('user.myjobbox.destroy');

    // 週間プランナー掲示板（User向け）
    Route::get('/user/project-jobs/{projectJob}/week-posts', [App\Http\Controllers\User\ProjectScheduleWeekPostController::class, 'index'])->name('user.project_jobs.week_posts.index');
    Route::post('/user/project-jobs/{projectJob}/week-posts', [App\Http\Controllers\User\ProjectScheduleWeekPostController::class, 'store'])->name('user.project_jobs.week_posts.store');

    // 進行管理表（User 閲覧・担当者登録）
    Route::get('/user/progress-sheets/{sheet}', [\App\Http\Controllers\User\ProgressSheetController::class, 'show'])->name('user.progress_sheets.show');
    Route::get('/user/progress-sheets/{sheet}/print', [\App\Http\Controllers\User\ProgressSheetController::class, 'printView'])->name('user.progress_sheets.print');
    Route::post('/user/progress-sheets/{sheet}/cells/{cell}/assign', [\App\Http\Controllers\User\ProgressSheetController::class, 'assign'])->name('progress_sheets.cells.assign');
    Route::delete('/user/progress-sheets/{sheet}/cells/{cell}/assign', [\App\Http\Controllers\User\ProgressSheetController::class, 'unassign'])->name('progress_sheets.cells.unassign');
    Route::get('/user/progress-cells/my-assignments', [\App\Http\Controllers\User\ProgressCellController::class, 'myAssignments'])->name('user.progress_cells.my_assignments');
    Route::post('/user/progress-cells/{cell}/complete', [\App\Http\Controllers\User\ProgressCellController::class, 'complete'])->name('user.progress_cells.complete');

    // 項目リスト候補（User向け：マイジョブ作成用）
    Route::get('/user/project-jobs/{projectJob}/item-entries/suggestions', [\App\Http\Controllers\User\ItemEntrySuggestController::class, 'suggestions'])->name('user.item_entries.suggestions');

    // 工程シート（User 閲覧・セル更新）
    Route::get('/user/workflow-sheets/{sheet}', [\App\Http\Controllers\User\WorkflowSheetController::class, 'show'])->name('user.workflow_sheets.show');
    Route::put('/user/workflow-sheets/{sheet}/cells', [\App\Http\Controllers\User\WorkflowCellController::class, 'update'])->name('user.workflow_sheets.cells.update');
    Route::post('/user/workflow-sheets/{sheet}/cells/register', [\App\Http\Controllers\User\WorkflowCellController::class, 'register'])->name('user.workflow_sheets.cells.register');
    Route::post('/user/workflow-cells/{cell}/complete', [\App\Http\Controllers\User\WorkflowCellController::class, 'complete'])->name('user.workflow_cells.complete');

    // チーム切り替え
    Route::put('/current-team', [App\Http\Controllers\CurrentTeamController::class, 'update'])->name('current-team.update');

    // 日報機能（作成、保存、表示、編集、更新、削除）
    // past-data は静的ルートなので resource より前に定義する
    Route::get('diaries/past-data', [App\Http\Controllers\DiaryController::class, 'pastData'])->name('diaries.past_data');
    Route::resource('diaries', App\Http\Controllers\DiaryController::class)
        ->only(['create', 'store', 'show', 'edit', 'update', 'destroy', 'index']);

    // Diary comment update/delete (authenticated users can edit/delete their own comment)
    Route::patch('diary-comments/{comment}', [App\Http\Controllers\DiaryCommentController::class, 'update'])->name('diary_comments.update');
    Route::delete('diary-comments/{comment}', [App\Http\Controllers\DiaryCommentController::class, 'destroy'])->name('diary_comments.destroy');

    // Attachment deletion (authenticated users) - allow frontend to call DELETE /attachments/{id}
    Route::delete('attachments/{attachment}', [App\Http\Controllers\AttachmentController::class, 'destroy'])->name('attachments.destroy');
    // Also allow deletion by POST/DELETE with path/attachment_id in body for clients that only have path
    Route::delete('attachments', [App\Http\Controllers\AttachmentController::class, 'destroyByPath'])->name('attachments.destroy_by_path');
    // SPA-friendly attachment stream (use web middleware so StartSession runs)
    Route::get('/attachments/stream', [App\Http\Controllers\AttachmentController::class, 'stream'])->name('attachments.stream');

    // Unified diary interactions (管理者/リーダーの既読・コメント操作を統合するためのエンドポイント)
    // Keep /interactions as the canonical user-facing index. Provide /entries as a
    // backward-compatible redirect to avoid breaking older links.
    Route::prefix('diaryinteractions')->name('diaryinteractions.')->group(function () {
        Route::get('/interactions', [App\Http\Controllers\Diaries\DiaryInteractionController::class, 'index'])->name('interactions.index');
        // backward-compatible redirect from /entries -> /interactions
        Route::get('/entries', function () {
            return redirect()->route('diaryinteractions.interactions.index');
        })->name('entries.index');
        Route::post('/mark-read-all', [App\Http\Controllers\Diaries\DiaryInteractionController::class, 'markReadAll'])->name('mark_read_all');
        // Show a single event in the diary interactions context (read-only view)
        Route::get('/events/{event}', [App\Http\Controllers\EventController::class, 'showForInteraction'])->name('diaryinteractions.events.show');
    });

    // Coordinator 専用イベント詳細（タブメニューをCoordinatorのまま維持、編集・削除無効）
    Route::get('/coordinator/events/{event}', [App\Http\Controllers\EventController::class, 'showForCoordinator'])->name('coordinator.events.show');

    // イベント機能（作成、保存、表示、編集、更新）
    // New route for job-specific create page (frontend navigates here for job creation)
    Route::get('/events/create-job', [App\Http\Controllers\EventController::class, 'createJob'])->name('events.create_job');

    Route::resource('events', App\Http\Controllers\EventController::class)->only([
        'create',
        'store',
        'show',
        'edit',
        'update'
    ]);

    // Mark an event (that is linked to a project_job_assignment) as completed
    Route::post('/events/{event}/complete', [App\Http\Controllers\EventController::class, 'complete'])->name('events.complete');

    // Test: send a fake job-completion mail to user_id=1
    Route::get('/events/send-test-completion', [App\Http\Controllers\EventController::class, 'sendTestCompletion'])->name('events.send_test_completion');

    // Allow authenticated users (owners) to delete their project memos via a non-coordinator route
    Route::delete('project_memos/{memo}', [App\Http\Controllers\Coordinator\ProjectMemosController::class, 'destroy'])->name('project_memos.destroy');

    // チャットルーム作成
    Route::get('/chat/rooms', [App\Http\Controllers\Chat\ChatController::class, 'indexRooms'])->name('chat.rooms.index');
    Route::get('/chat/rooms/create', [App\Http\Controllers\Chat\ChatController::class, 'createRoom'])->name('chat.rooms.create');
    Route::post('/chat/rooms', [App\Http\Controllers\Chat\ChatController::class, 'storeRoom'])->name('chat.rooms.store');

    // User-facing jobbox routes (read-only, for assigned users)
    Route::get('project_jobs/{projectJob}/jobbox', [\App\Http\Controllers\ProjectJobs\JobBoxController::class, 'index'])->name('user.project_jobs.jobbox.index');
    Route::get('project_jobs/{projectJob}/jobbox/{message}', [\App\Http\Controllers\ProjectJobs\JobBoxController::class, 'show'])->name('user.project_jobs.jobbox.show');
    Route::post('project_jobs/{projectJob}/jobbox/reply', [\App\Http\Controllers\ProjectJobs\JobBoxController::class, 'reply'])->name('user.project_jobs.jobbox.reply');
    // JobBox JSON API (SPA session auth — must be in web.php, not api.php)
    Route::get('/api/jobbox/{id}', [\App\Http\Controllers\ProjectJobs\JobBoxController::class, 'apiShow'])->name('api.jobbox.show');
    Route::post('/api/jobbox/{id}/read', [\App\Http\Controllers\ProjectJobs\JobBoxController::class, 'apiMarkRead'])->name('api.jobbox.read');
    // User self-assignment routes
    Route::post('project_jobs/{projectJob}/assignments/user', [App\Http\Controllers\User\ProjectJobAssignmentController::class, 'store'])->name('user.project_jobs.assignments.store');
    Route::patch('project_jobs/{projectJob}/assignments/{assignment}/user', [App\Http\Controllers\User\ProjectJobAssignmentController::class, 'update'])->name('user.project_jobs.assignments.update');
    Route::get('project_jobs/assignments/create-user', [\App\Http\Controllers\ProjectJobs\ProjectJobAssignmentUserController::class, 'create'])
        ->name('user.project_jobs.assignments.create');
    Route::get('/project_jobs/assignments/edit-user', [\App\Http\Controllers\ProjectJobs\ProjectJobAssignmentUserController::class, 'edit'])
        ->name('user.project_jobs.assignments.edit');
    Route::get('/project_jobs/assignments/{assignment}/schedule', [\App\Http\Controllers\ProjectJobs\JobBoxController::class, 'schedule'])
        ->name('user.project_jobs.assignments.schedule');
    Route::put('/project_jobs/assignments/{assignment}/schedule', [\App\Http\Controllers\ProjectJobs\JobBoxController::class, 'storeSchedule'])
        ->name('user.project_jobs.assignments.schedule.store');
    Route::get('/chat/rooms/{id}', [App\Http\Controllers\Chat\ChatController::class, 'showRoom'])->name('chat.rooms.show');

    // Workload setting: show lists (stages, sizes, statuses, difficulties) scoped by company
    Route::get('workload-setting', [App\Http\Controllers\WorkloadSettingController::class, 'index'])
        ->name('workload_setting.index');

    // Workload setting edit and save (type = stages|work_item_types|sizes|statuses|difficulties)
    Route::get('workload-setting/edit/{type}', [App\Http\Controllers\WorkloadSettingController::class, 'edit'])
        ->name('workload_setting.edit');
    Route::post('workload-setting/{type}', [App\Http\Controllers\WorkloadSettingController::class, 'store'])
        ->name('workload_setting.store');



    // Job Requests (Inbox) - minimal CRUD + accept
    Route::get('/job_requests', [App\Http\Controllers\JobRequestsController::class, 'index'])->name('job_requests.index');
    Route::get('/job_requests/{jobRequest}', [App\Http\Controllers\JobRequestsController::class, 'show'])->name('job_requests.show');
    Route::post('/job_requests', [App\Http\Controllers\JobRequestsController::class, 'store'])->name('job_requests.store');
    Route::post('/job_requests/{jobRequest}/accept', [App\Http\Controllers\JobRequestsController::class, 'accept'])->name('job_requests.accept');
    // Messages (mailbox)
    Route::get('/messages', [App\Http\Controllers\MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/create', [App\Http\Controllers\MessageController::class, 'create'])->name('messages.create');
    Route::get('/messages/{message}', [App\Http\Controllers\MessageController::class, 'show'])->name('messages.show');
    Route::post('/messages', [App\Http\Controllers\MessageController::class, 'store'])->name('messages.store');
    Route::post('/messages/{message}/read', [App\Http\Controllers\MessageController::class, 'markRead'])->name('messages.read');
    // Accept a job request using messages flow (transitional endpoint)
    Route::post('/messages/job_requests/{jobRequest}/accept', [App\Http\Controllers\MessageController::class, 'acceptJobRequest'])->name('messages.job_requests.accept');
    // Move message to trash for the current user
    Route::post('/messages/{message}/trash', [App\Http\Controllers\MessageController::class, 'trash'])->name('messages.trash');
    // Permanently remove a message for the current user (only if already in trash)
    Route::delete('/messages/{message}', [App\Http\Controllers\MessageController::class, 'destroy'])->name('messages.destroy');
    // lightweight user search for message compose autocomplete
    Route::get('/users/search', [App\Http\Controllers\UserController::class, 'search'])->name('users.search');
});

// Admin Routes (Adminのみアクセス可能)
// More specific admin routes that must enforce company ownership (clients, etc.) are protected separately.
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Ziggy用: 明示的にadmin.dashboardルートを追加
        Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

        // 案件総覧
        Route::get('project-jobs', [App\Http\Controllers\Admin\ProjectJobController::class, 'index'])->name('project_jobs.index');
        Route::get('project-jobs/{projectJob}', [App\Http\Controllers\Admin\ProjectJobController::class, 'show'])->name('project_jobs.show');

        // CSV一括登録（リソースルートより前に配置）
        Route::get('users/csv/upload', [App\Http\Controllers\Admin\UserController::class, 'csvUpload'])->name('users.csv.upload');
        Route::post('users/csv/preview', [App\Http\Controllers\Admin\UserController::class, 'csvPreview'])->name('users.csv.preview');
        Route::post('users/csv/store', [App\Http\Controllers\Admin\UserController::class, 'csvStore'])->name('users.csv.store');
        Route::get('users/csv/sample', [App\Http\Controllers\Admin\UserController::class, 'csvSampleDownload'])->name('users.csv.sample');

        // ユーザー管理
        Route::resource('users', App\Http\Controllers\Admin\UserController::class);

        // クライアント管理（Admin用）
        Route::get('clients/csv/upload', [App\Http\Controllers\ClientController::class, 'csvUpload'])->name('clients.csv.upload');
        Route::post('clients/csv/preview', [App\Http\Controllers\ClientController::class, 'csvPreview'])->name('clients.csv.preview');
        Route::post('clients/csv/store', [App\Http\Controllers\ClientController::class, 'csvStore'])->name('clients.csv.store');
        Route::get('clients/csv/sample', [App\Http\Controllers\ClientController::class, 'csvSampleDownload'])->name('clients.csv.sample');
        Route::get('clients/json', [App\Http\Controllers\ClientController::class, 'clientsJson'])->name('clients.json');
        Route::post('clients/check-duplicate', [App\Http\Controllers\ClientController::class, 'checkDuplicate'])->name('clients.check_duplicate');
        Route::get('clients/duplicate-check', [App\Http\Controllers\ClientController::class, 'duplicateCheckPage'])->name('clients.duplicate_check');
        Route::post('clients/batch-merge', [App\Http\Controllers\ClientController::class, 'batchMerge'])->name('clients.batch_merge');
        Route::post('clients/{client}/merge', [App\Http\Controllers\ClientController::class, 'merge'])->name('clients.merge');
        Route::post('clients/{client}/dormant', [App\Http\Controllers\ClientController::class, 'dormant'])->name('clients.dormant');
        Route::post('clients/{client}/share-to-my-company', [App\Http\Controllers\ClientController::class, 'shareToMyCompany'])->name('clients.share_to_my_company');
        Route::post('clients/{client}/toggle-dept', [App\Http\Controllers\ClientController::class, 'toggleDeptAdmin'])->name('clients.toggle_dept');
        Route::post('clients/{client}/toggle-company', [App\Http\Controllers\ClientController::class, 'toggleCompany'])->name('clients.toggle_company');
        Route::resource('clients', App\Http\Controllers\ClientController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

        // 会社管理 (会社作成/管理は SuperAdmin 側に一本化しました)
        // 会社管理: 管理者は自社の閲覧・編集のみ許可 (作成/削除はできない)
        Route::resource('companies', App\Http\Controllers\Admin\CompanyController::class)
            ->only(['index', 'show', 'edit', 'update']);

        // チーム管理
        Route::resource('teams', App\Http\Controllers\Admin\TeamController::class);
        // ユニットチーム作成 (画面と保存)
        Route::get('teams/units/create', [App\Http\Controllers\Admin\UnitController::class, 'create'])->name('teams.units.create');
        Route::post('units', [App\Http\Controllers\Admin\UnitController::class, 'store'])->name('units.store');
        // 特別チーム管理（会社横断）
        Route::resource('special-teams', App\Http\Controllers\Admin\SpecialTeamController::class)
            ->names('special_teams');
        // 管理者向け 日報一覧・閲覧 (centralized diary interactions)
        // Provide an admin-scoped diaries index route so admin links using
        // route('admin.diaries.index') resolve correctly in Ziggy.
        Route::get('diaries', [App\Http\Controllers\DiaryController::class, 'index'])->name('diaries.index');
        Route::get('diaryinteractions', [App\Http\Controllers\Diaries\DiaryInteractionController::class, 'index'])->name('diaryinteractions.index');
        Route::get('diaryinteractions/{diary}', [App\Http\Controllers\Diaries\DiaryInteractionController::class, 'show'])->name('diaryinteractions.show');
        Route::post('diaryinteractions/{diary}/mark-read', [App\Http\Controllers\Diaries\DiaryInteractionController::class, 'markRead'])->name('diaryinteractions.mark_read');
        // 日付単位で「全部既読にする」
        Route::post('diaryinteractions/mark-read-all', [App\Http\Controllers\Diaries\DiaryInteractionController::class, 'markReadAll'])->name('diaryinteractions.mark_read_all');
        // AI settings admin
        Route::get('/ai', [\App\Http\Controllers\Admin\AiSettingController::class, 'index'])->name('ai.index');
        Route::get('/ai/create', [\App\Http\Controllers\Admin\AiSettingController::class, 'create'])->name('ai.create');
        Route::post('/ai', [\App\Http\Controllers\Admin\AiSettingController::class, 'store'])->name('ai.store');
        Route::get('/ai/{id}/edit', [\App\Http\Controllers\Admin\AiSettingController::class, 'edit'])->name('ai.edit');
        Route::put('/ai/{id}', [\App\Http\Controllers\Admin\AiSettingController::class, 'update'])->name('ai.update');
        // AI presets management
        Route::get('/ai-presets', [\App\Http\Controllers\Admin\AiPresetsController::class, 'index'])->name('ai.presets.index');
        Route::post('/ai-presets', [\App\Http\Controllers\Admin\AiPresetsController::class, 'store'])->name('ai.presets.store');
        Route::put('/ai-presets/{ai_preset}', [\App\Http\Controllers\Admin\AiPresetsController::class, 'update'])->name('ai.presets.update');
        Route::delete('/ai-presets/{ai_preset}', [\App\Http\Controllers\Admin\AiPresetsController::class, 'destroy'])->name('ai.presets.destroy');
        // 勤務形態設定
        Route::get('worktypes', [App\Http\Controllers\Admin\WorktypeController::class, 'index'])->name('worktypes.index');
        Route::get('worktypes/edit', [App\Http\Controllers\Admin\WorktypeController::class, 'edit'])->name('worktypes.edit');
        Route::post('worktypes/update', [App\Http\Controllers\Admin\WorktypeController::class, 'update'])->name('worktypes.update');
        // 勤務時間管理
        Route::get('work-records', [App\Http\Controllers\WorkRecordController::class, 'index'])->name('work_records.index');

        // Admin: Workload Analyzer (company-wide)
        Route::get('workload-analyzer', [App\Http\Controllers\Leader\WorkloadAnalyzerController::class, 'index'])->name('workload_analyzer.index');
        // Register static routes before the parameterized {user} route
        Route::get('workload-analyzer/category-rank', [App\Http\Controllers\Leader\WorkloadAnalyzerController::class, 'index'])->name('workload_analyzer.category_rank');
        Route::get('workload-analyzer/guide', [App\Http\Controllers\Leader\WorkloadAnalyzerController::class, 'guide'])->name('workload_analyzer.guide');
        Route::get('workload-analyzer/settings', [App\Http\Controllers\Leader\WorkloadAnalyzerController::class, 'settings'])->name('workload_analyzer.settings');
        Route::post('workload-analyzer/settings', [App\Http\Controllers\Leader\WorkloadAnalyzerController::class, 'saveSettings'])->name('workload_analyzer.settings.save');
        Route::get('workload-analyzer/{user}', [App\Http\Controllers\Leader\WorkloadAnalyzerController::class, 'show'])->name('workload_analyzer.show');

        // 代表者 Admin のみ: Admin 権限管理
        Route::middleware('representative')->group(function () {
            Route::get('admin-permissions', [App\Http\Controllers\SuperAdmin\AdminPermissionController::class, 'index'])->name('admin_permissions.index');
            Route::get('admin-permissions/{adminuser}/edit', [App\Http\Controllers\SuperAdmin\AdminPermissionController::class, 'edit'])->name('admin_permissions.edit');
            Route::put('admin-permissions/{adminuser}', [App\Http\Controllers\SuperAdmin\AdminPermissionController::class, 'update'])->name('admin_permissions.update');
        });

        // Admin: Leader 権限管理
        Route::get('leader-permissions', [App\Http\Controllers\Admin\LeaderPermissionController::class, 'index'])->name('leader_permissions.index');
        Route::get('leader-permissions/{leaderuser}/edit', [App\Http\Controllers\Admin\LeaderPermissionController::class, 'edit'])->name('leader_permissions.edit');
        Route::put('leader-permissions/{leaderuser}', [App\Http\Controllers\Admin\LeaderPermissionController::class, 'update'])->name('leader_permissions.update');

        // 会議設定（Admin用）
        Route::resource('meeting-definitions', App\Http\Controllers\Admin\MeetingDefinitionController::class)
            ->names('meeting_definitions');

        // 在席ボード管理（Admin用）
        Route::get('presence/board-settings', [App\Http\Controllers\PresenceBoardSettingsController::class, 'index'])->name('presence.board_settings');

        // 部署管理（Admin用 — SuperAdmin作成の部署も含む）
        Route::get('departments', [App\Http\Controllers\Admin\DepartmentController::class, 'index'])->name('departments.index');
        Route::post('departments', [App\Http\Controllers\Admin\DepartmentController::class, 'store'])->name('departments.store');
        Route::post('departments/{department}/create-team', [App\Http\Controllers\Admin\DepartmentController::class, 'createTeam'])->name('departments.create_team');
        Route::delete('departments/{department}', [App\Http\Controllers\Admin\DepartmentController::class, 'destroy'])->name('departments.destroy');
        // 部署フィールド設定
        Route::get('departments/{department}/field-config', [App\Http\Controllers\Admin\DepartmentController::class, 'fieldConfig'])->name('departments.field_config');
        Route::post('departments/{department}/field-config', [App\Http\Controllers\Admin\DepartmentController::class, 'updateFieldConfig'])->name('departments.field_config.update');

        // 日報権限チーム管理
        Route::resource('diary-teams', App\Http\Controllers\Admin\DiaryTeamController::class)
            ->names([
                'index'   => 'diary_teams.index',
                'create'  => 'diary_teams.create',
                'store'   => 'diary_teams.store',
                'edit'    => 'diary_teams.edit',
                'update'  => 'diary_teams.update',
                'destroy' => 'diary_teams.destroy',
            ])
            ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
    });


// DiaryManager Routes (diary_team_leaders に登録されたユーザーのみ)
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'diary_manager'])
    ->prefix('diary-manager')
    ->name('diary_manager.')
    ->group(function () {
        Route::get('diaryinteractions', [App\Http\Controllers\DiaryManager\DiaryInteractionController::class, 'index'])
            ->name('diaryinteractions.index');
        Route::get('diaryinteractions/{diary}', [App\Http\Controllers\DiaryManager\DiaryInteractionController::class, 'show'])
            ->name('diaryinteractions.show');
        Route::post('diaryinteractions/{diary}/mark-read', [App\Http\Controllers\DiaryManager\DiaryInteractionController::class, 'markRead'])
            ->name('diaryinteractions.mark_read');
        Route::post('diaryinteractions/mark-read-all', [App\Http\Controllers\DiaryManager\DiaryInteractionController::class, 'markReadAll'])
            ->name('diaryinteractions.mark_read_all');
    });

// SuperAdmin Routes (SuperAdminのみアクセス可能)
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'superadmin'])
    ->prefix('superadmin')
    ->name('superadmin.')
    ->group(function () {
        // Ziggy用: 明示的にsuperadmin.dashboardルートを追加
        Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

        // ユーザー管理
        // existing users resource (general)
        Route::resource('users', App\Http\Controllers\SuperAdmin\UserController::class);
        // adminusers: superadmin が管理する "admin" ユーザー用 CRUD (単一定義)
        Route::resource('adminusers', App\Http\Controllers\SuperAdmin\AdminUserController::class);

        // CSV routes for adminusers
        Route::get('adminusers/csv/upload', [App\Http\Controllers\SuperAdmin\AdminUserController::class, 'csvUpload'])->name('adminusers.csv.upload');
        Route::post('adminusers/csv/preview', [App\Http\Controllers\SuperAdmin\AdminUserController::class, 'csvPreview'])->name('adminusers.csv.preview');
        Route::post('adminusers/csv/store', [App\Http\Controllers\SuperAdmin\AdminUserController::class, 'csvStore'])->name('adminusers.csv.store');

        // 役職称号管理
        Route::get('position-titles', [App\Http\Controllers\SuperAdmin\PositionTitleController::class, 'index'])->name('position_titles.index');
        Route::get('position-titles/edit', [App\Http\Controllers\SuperAdmin\PositionTitleController::class, 'edit'])->name('position_titles.edit');
        Route::put('position-titles', [App\Http\Controllers\SuperAdmin\PositionTitleController::class, 'update'])->name('position_titles.update');

        // Admin 権限管理
        Route::get('admin-permissions', [App\Http\Controllers\SuperAdmin\AdminPermissionController::class, 'index'])->name('admin_permissions.index');
        Route::get('admin-permissions/{adminuser}/edit', [App\Http\Controllers\SuperAdmin\AdminPermissionController::class, 'edit'])->name('admin_permissions.edit');
        Route::put('admin-permissions/{adminuser}', [App\Http\Controllers\SuperAdmin\AdminPermissionController::class, 'update'])->name('admin_permissions.update');

        // 会社管理
        Route::resource('companies', App\Http\Controllers\SuperAdmin\CompanyController::class);
        Route::post('companies/reorder', [App\Http\Controllers\SuperAdmin\CompanyController::class, 'reorder'])->name('superadmin.companies.reorder');

        // チーム管理
        Route::resource('teams', App\Http\Controllers\SuperAdmin\TeamController::class);

        // AI settings for SuperAdmin
        Route::get('/ai', [\App\Http\Controllers\SuperAdmin\AiSettingController::class, 'index'])->name('ai.index');
        Route::get('/ai/create', [\App\Http\Controllers\SuperAdmin\AiSettingController::class, 'create'])->name('ai.create');
        Route::post('/ai', [\App\Http\Controllers\SuperAdmin\AiSettingController::class, 'store'])->name('ai.store');
        Route::get('/ai/{id}/edit', [\App\Http\Controllers\SuperAdmin\AiSettingController::class, 'edit'])->name('ai.edit');
        Route::put('/ai/{id}', [\App\Http\Controllers\SuperAdmin\AiSettingController::class, 'update'])->name('ai.update');
        // AI presets management (SuperAdmin)
        Route::get('/ai-presets', [\App\Http\Controllers\SuperAdmin\AiPresetsController::class, 'index'])->name('ai.presets.index');
        Route::post('/ai-presets', [\App\Http\Controllers\SuperAdmin\AiPresetsController::class, 'store'])->name('ai.presets.store');
        Route::put('/ai-presets/{ai_preset}', [\App\Http\Controllers\SuperAdmin\AiPresetsController::class, 'update'])->name('ai.presets.update');
        Route::delete('/ai-presets/{ai_preset}', [\App\Http\Controllers\SuperAdmin\AiPresetsController::class, 'destroy'])->name('ai.presets.destroy');
        // SuperAdmin: Workload Analyzer (global)
        Route::get('workload-analyzer', [App\Http\Controllers\Leader\WorkloadAnalyzerController::class, 'index'])->name('workload_analyzer.index');
        Route::get('workload-analyzer/category-rank', [App\Http\Controllers\Leader\WorkloadAnalyzerController::class, 'index'])->name('workload_analyzer.category_rank');
        Route::get('workload-analyzer/guide', [App\Http\Controllers\Leader\WorkloadAnalyzerController::class, 'guide'])->name('workload_analyzer.guide');

        // SuperAdmin: 交通費管理
        Route::prefix('billing/transport')->name('billing.transport.')->group(function () {
            Route::get('billed', [App\Http\Controllers\Billing\Transport\BillingRequestController::class, 'index'])->name('billed');
            Route::get('list', [App\Http\Controllers\Billing\Transport\ExpenseListController::class, 'index'])->name('list');
            Route::get('/', [App\Http\Controllers\Billing\Transport\ExpenseController::class, 'index'])->name('index');
            Route::post('/', [App\Http\Controllers\Billing\Transport\ExpenseController::class, 'store'])->name('store');
            Route::put('{expense}', [App\Http\Controllers\Billing\Transport\ExpenseController::class, 'update'])->name('update');
            Route::delete('{expense}', [App\Http\Controllers\Billing\Transport\ExpenseController::class, 'destroy'])->name('destroy');
            Route::get('{expense}/excel', [App\Http\Controllers\Billing\Transport\ExpenseController::class, 'exportExcel'])->name('excel');
            Route::get('{expense}/pdf', [App\Http\Controllers\Billing\Transport\ExpenseController::class, 'exportPdf'])->name('pdf');
            Route::post('billing', [App\Http\Controllers\Billing\Transport\BillingRequestController::class, 'store'])->name('billing.store');
            Route::get('billing/{billing}/pdf', [App\Http\Controllers\Billing\Transport\BillingRequestController::class, 'exportPdf'])->name('billing.pdf');
            Route::get('billing/{billing}/excel', [App\Http\Controllers\Billing\Transport\BillingRequestController::class, 'exportExcel'])->name('billing.excel');
        });

        // SuperAdmin: スクリプト管理
        Route::get('scripts', [App\Http\Controllers\SuperAdmin\ScriptManagementController::class, 'index'])->name('scripts.index');
        Route::post('scripts/assign', [App\Http\Controllers\SuperAdmin\ScriptManagementController::class, 'assign'])->name('scripts.assign');
    });



// Ziggy用: 明示的にleader.dashboardルートを追加
// Leader Routes (AdminとLeaderのみアクセス可能)
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'leader'])
    ->prefix('leader')
    ->name('leader.')
    ->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
        // クライアント管理（Leader用）
        Route::get('clients/csv/upload', [App\Http\Controllers\ClientController::class, 'csvUpload'])->name('clients.csv.upload');
        Route::post('clients/csv/preview', [App\Http\Controllers\ClientController::class, 'csvPreview'])->name('clients.csv.preview');
        Route::post('clients/csv/store', [App\Http\Controllers\ClientController::class, 'csvStore'])->name('clients.csv.store');
        Route::get('clients/csv/sample', [App\Http\Controllers\ClientController::class, 'csvSampleDownload'])->name('clients.csv.sample');
        Route::get('clients/json', [App\Http\Controllers\ClientController::class, 'clientsJson'])->name('clients.json');
        Route::post('clients/check-duplicate', [App\Http\Controllers\ClientController::class, 'checkDuplicate'])->name('clients.check_duplicate');
        Route::get('clients/duplicate-check', [App\Http\Controllers\ClientController::class, 'duplicateCheckPage'])->name('clients.duplicate_check');
        Route::post('clients/batch-merge', [App\Http\Controllers\ClientController::class, 'batchMerge'])->name('clients.batch_merge');
        Route::post('clients/{client}/merge', [App\Http\Controllers\ClientController::class, 'merge'])->name('clients.merge');
        Route::post('clients/{client}/dormant', [App\Http\Controllers\ClientController::class, 'dormant'])->name('clients.dormant');
        Route::post('clients/{client}/toggle-department', [App\Http\Controllers\ClientController::class, 'toggleDepartment'])->name('clients.toggle_department');
        Route::post('clients/{client}/share-to-my-company', [App\Http\Controllers\ClientController::class, 'shareToMyCompany'])->name('clients.share_to_my_company');
        Route::post('clients/{client}/toggle-dept', [App\Http\Controllers\ClientController::class, 'toggleDeptAdmin'])->name('clients.toggle_dept');
        Route::resource('clients', App\Http\Controllers\ClientController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
        // Leader diary interactions (leader can view diaries for departments/units they lead)
        Route::get('diaryinteractions', [App\Http\Controllers\Diaries\DiaryInteractionController::class, 'index'])->name('diaryinteractions.index');
        Route::get('diaryinteractions/{diary}', [App\Http\Controllers\Diaries\DiaryInteractionController::class, 'show'])->name('diaryinteractions.show');
        Route::post('diaryinteractions/{diary}/mark-read', [App\Http\Controllers\Diaries\DiaryInteractionController::class, 'markRead'])->name('diaryinteractions.mark_read');
        // 日付単位で「全部既読にする」(リーダー用)
        Route::post('diaryinteractions/mark-read-all', [App\Http\Controllers\Diaries\DiaryInteractionController::class, 'markReadAll'])->name('diaryinteractions.mark_read_all');
        // Leader: Workload Analyzer (show company/department/team members and analysis placeholders)
        Route::get('workload-analyzer', [App\Http\Controllers\Leader\WorkloadAnalyzerController::class, 'index'])->name('workload_analyzer.index');
        // ensure static routes are registered before the parameterized {user} route
        Route::get('workload-analyzer/category-rank', [App\Http\Controllers\Leader\WorkloadAnalyzerController::class, 'index'])->name('workload_analyzer.category_rank');
        Route::get('workload-analyzer/guide', [App\Http\Controllers\Leader\WorkloadAnalyzerController::class, 'guide'])->name('workload_analyzer.guide');
        Route::get('workload-analyzer/settings', [App\Http\Controllers\Leader\WorkloadAnalyzerController::class, 'settings'])->name('workload_analyzer.settings');
        Route::post('workload-analyzer/settings', [App\Http\Controllers\Leader\WorkloadAnalyzerController::class, 'saveSettings'])->name('workload_analyzer.settings.save');
        Route::get('workload-analyzer/{user}', [App\Http\Controllers\Leader\WorkloadAnalyzerController::class, 'show'])->name('workload_analyzer.show');
        // 勤務時間管理
        Route::get('work-records', [App\Http\Controllers\WorkRecordController::class, 'index'])->name('work_records.index');

        // ユニットチーム管理（Leader用）
        Route::get('teams/create', [App\Http\Controllers\Leader\UnitController::class, 'create'])->name('teams.create');
        Route::post('teams', [App\Http\Controllers\Leader\UnitController::class, 'store'])->name('units.store');
        Route::resource('teams', App\Http\Controllers\Leader\TeamController::class)->only(['index', 'show', 'edit', 'update', 'destroy']);

        // 勤務項目設定（Leader用 prefix）
        Route::get('workload-setting', [App\Http\Controllers\WorkloadSettingController::class, 'index'])
            ->name('workload_setting.index');
        Route::get('workload-setting/edit/{type}', [App\Http\Controllers\WorkloadSettingController::class, 'edit'])
            ->name('workload_setting.edit');
        Route::post('workload-setting/{type}', [App\Http\Controllers\WorkloadSettingController::class, 'store'])
            ->name('workload_setting.store');

        // 部署フィールド設定（Leader用 — 自部署のみ）
        Route::get('department-field-config', [App\Http\Controllers\Leader\DepartmentFieldConfigController::class, 'edit'])->name('department_field_config.edit');
        Route::post('department-field-config', [App\Http\Controllers\Leader\DepartmentFieldConfigController::class, 'update'])->name('department_field_config.update');

        // 派遣・業務委託管理
        Route::get('dispatch-management', [App\Http\Controllers\Leader\DispatchManagementController::class, 'index'])->name('dispatch_management.index');
        Route::get('dispatch-management/{dispatchUser}/edit', [App\Http\Controllers\Leader\DispatchManagementController::class, 'edit'])->name('dispatch_management.edit');
        Route::put('dispatch-management/{dispatchUser}', [App\Http\Controllers\Leader\DispatchManagementController::class, 'update'])->name('dispatch_management.update');

        // 部署リーダー用ユーザー管理
        Route::get('user-management', [App\Http\Controllers\Leader\UserManagementController::class, 'index'])->name('user_management.index');
        Route::get('user-management/create', [App\Http\Controllers\Leader\UserManagementController::class, 'create'])->name('user_management.create');
        Route::post('user-management', [App\Http\Controllers\Leader\UserManagementController::class, 'store'])->name('user_management.store');
        Route::post('user-management/bulk-update', [App\Http\Controllers\Leader\UserManagementController::class, 'bulkUpdate'])->name('user_management.bulk_update');
        Route::get('user-management/{user}/edit', [App\Http\Controllers\Leader\UserManagementController::class, 'edit'])->name('user_management.edit');
        Route::put('user-management/{user}', [App\Http\Controllers\Leader\UserManagementController::class, 'update'])->name('user_management.update');

        // 全 Leader: Leader 権限管理（スコープはコントローラで制御）
        Route::get('leader-permissions', [App\Http\Controllers\Admin\LeaderPermissionController::class, 'index'])->name('leader_permissions.index');
        Route::get('leader-permissions/{leaderuser}/edit', [App\Http\Controllers\Admin\LeaderPermissionController::class, 'edit'])->name('leader_permissions.edit');
        Route::put('leader-permissions/{leaderuser}', [App\Http\Controllers\Admin\LeaderPermissionController::class, 'update'])->name('leader_permissions.update');

        // 案件総覧（部署リーダー: 部署内全案件を読み取り専用で閲覧）
        Route::get('project-jobs', [App\Http\Controllers\Leader\ProjectJobController::class, 'index'])->name('project_jobs.index');
        Route::get('project-jobs/{projectJob}', [App\Http\Controllers\Leader\ProjectJobController::class, 'show'])->name('project_jobs.show');

        // 会議設定（Leader用）
        Route::resource('meeting-definitions', App\Http\Controllers\Leader\MeetingDefinitionController::class)
            ->names('meeting_definitions');

        // 在席ボード管理（Leader用）
        Route::get('presence/board-settings', [App\Http\Controllers\PresenceBoardSettingsController::class, 'index'])->name('presence.board_settings');

        // 営業担当管理（Leader用）
        Route::get('sales-reps', [\App\Http\Controllers\Leader\SalesRepController::class, 'index'])->name('sales_reps.index');
        Route::post('sales-reps', [\App\Http\Controllers\Leader\SalesRepController::class, 'store'])->name('sales_reps.store');
        Route::post('sales-reps/bulk',    [\App\Http\Controllers\Leader\SalesRepController::class, 'bulkStore'])->name('sales_reps.bulkStore');
        Route::post('sales-reps/reorder', [\App\Http\Controllers\Leader\SalesRepController::class, 'reorder'])->name('sales_reps.reorder');
        Route::patch('sales-reps/{salesRep}', [\App\Http\Controllers\Leader\SalesRepController::class, 'update'])->name('sales_reps.update');
        Route::delete('sales-reps/{salesRep}', [\App\Http\Controllers\Leader\SalesRepController::class, 'destroy'])->name('sales_reps.destroy');

        // お知らせ管理（Leader用）
        Route::get('announcements', [App\Http\Controllers\Leader\AnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('announcements/create', [App\Http\Controllers\Leader\AnnouncementController::class, 'create'])->name('announcements.create');
        Route::post('announcements', [App\Http\Controllers\Leader\AnnouncementController::class, 'store'])->name('announcements.store');
        Route::get('announcements/{announcement}', [App\Http\Controllers\Leader\AnnouncementController::class, 'show'])->name('announcements.show');
        Route::get('announcements/{announcement}/edit', [App\Http\Controllers\Leader\AnnouncementController::class, 'edit'])->name('announcements.edit');
        Route::put('announcements/{announcement}', [App\Http\Controllers\Leader\AnnouncementController::class, 'update'])->name('announcements.update');
        Route::delete('announcements/{announcement}', [App\Http\Controllers\Leader\AnnouncementController::class, 'destroy'])->name('announcements.destroy');
        Route::post('announcements/{announcement}/send', [App\Http\Controllers\Leader\AnnouncementController::class, 'send'])->name('announcements.send');
    });

// クライアント管理（Admin用）は上の admin グループに統合済み（重複削除）
// Coordinator Routes (AdminとCoordinatorのみアクセス可能)
// Clerk Routes (Clerk / Admin / SuperAdmin / 部署リーダーのみアクセス可能)
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'clerk'])
    ->prefix('clerk')
    ->name('clerk.')
    ->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

        // お知らせ管理（送信側）
        Route::get('announcements', [App\Http\Controllers\Clerk\AnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('announcements/create', [App\Http\Controllers\Clerk\AnnouncementController::class, 'create'])->name('announcements.create');
        Route::post('announcements', [App\Http\Controllers\Clerk\AnnouncementController::class, 'store'])->name('announcements.store');
        Route::get('announcements/{announcement}', [App\Http\Controllers\Clerk\AnnouncementController::class, 'show'])->name('announcements.show');
        Route::get('announcements/{announcement}/edit', [App\Http\Controllers\Clerk\AnnouncementController::class, 'edit'])->name('announcements.edit');
        Route::put('announcements/{announcement}', [App\Http\Controllers\Clerk\AnnouncementController::class, 'update'])->name('announcements.update');
        Route::delete('announcements/{announcement}', [App\Http\Controllers\Clerk\AnnouncementController::class, 'destroy'])->name('announcements.destroy');
        Route::post('announcements/{announcement}/send', [App\Http\Controllers\Clerk\AnnouncementController::class, 'send'])->name('announcements.send');
    });

// お知らせ受信（全認証ユーザー）
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])
    ->prefix('announcements')
    ->name('announcements.')
    ->group(function () {
        Route::get('/', [App\Http\Controllers\AnnouncementController::class, 'index'])->name('index');
        Route::get('/{recipient}', [App\Http\Controllers\AnnouncementController::class, 'show'])->name('show');
    });

// ジョブ通知（全認証ユーザー）
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])
    ->prefix('job-notifications')
    ->name('job-notifications.')
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\JobNotificationController::class, 'index'])->name('index');
        Route::post('/{jobNotification}/read', [\App\Http\Controllers\JobNotificationController::class, 'markRead'])->name('markRead');
        Route::get('/{jobNotification}', [\App\Http\Controllers\JobNotificationController::class, 'show'])->name('show');
    });

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'coordinator'])
    ->prefix('coordinator')
    ->name('coordinator.')
    ->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

        // 外注先管理
        Route::post('subcontractors/check-duplicate', [App\Http\Controllers\Coordinator\SubcontractorController::class, 'checkDuplicate'])->name('subcontractors.check_duplicate');
        Route::resource('subcontractors', App\Http\Controllers\Coordinator\SubcontractorController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);

        // クライアント管理（Coordinator用）
        Route::get('clients/csv/upload', [App\Http\Controllers\ClientController::class, 'csvUpload'])->name('clients.csv.upload');
        Route::post('clients/csv/preview', [App\Http\Controllers\ClientController::class, 'csvPreview'])->name('clients.csv.preview');
        Route::post('clients/csv/store', [App\Http\Controllers\ClientController::class, 'csvStore'])->name('clients.csv.store');
        Route::get('clients/csv/sample', [App\Http\Controllers\ClientController::class, 'csvSampleDownload'])->name('clients.csv.sample');
        // クライアント検索JSON（案件作成モーダル用・統合先選択用）
        Route::get('clients/json', [App\Http\Controllers\ClientController::class, 'clientsJson'])->name('clients.json');
        Route::post('clients/check-duplicate', [App\Http\Controllers\ClientController::class, 'checkDuplicate'])->name('clients.check_duplicate');
        Route::get('clients/duplicate-check', [App\Http\Controllers\ClientController::class, 'duplicateCheckPage'])->name('clients.duplicate_check');
        Route::post('clients/batch-merge', [App\Http\Controllers\ClientController::class, 'batchMerge'])->name('clients.batch_merge');
        Route::get('clients/{client}/last-job-config', [App\Http\Controllers\ClientController::class, 'lastJobConfig'])->name('clients.last_job_config');
        Route::post('clients/{client}/merge', [App\Http\Controllers\ClientController::class, 'merge'])->name('clients.merge');
        Route::post('clients/{client}/dormant', [App\Http\Controllers\ClientController::class, 'dormant'])->name('clients.dormant');
        Route::post('clients/{client}/toggle-department', [App\Http\Controllers\ClientController::class, 'toggleDepartment'])->name('clients.toggle_department');
        Route::post('clients/{client}/share-to-my-company', [App\Http\Controllers\ClientController::class, 'shareToMyCompany'])->name('clients.share_to_my_company');
        Route::post('clients/{client}/toggle-dept', [App\Http\Controllers\ClientController::class, 'toggleDeptAdmin'])->name('clients.toggle_dept');
        Route::resource('clients', App\Http\Controllers\ClientController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

        // Project_job CRUD
        Route::get('project_jobs', [App\Http\Controllers\Coordinator\ProjectJobController::class, 'index'])->name('project_jobs.index');
        Route::get('project_jobs/create', [App\Http\Controllers\Coordinator\ProjectJobController::class, 'create'])->name('project_jobs.create');
        // 案件 CSV 一括登録（静的パスなので {projectJob} より前に定義）
        Route::get('project-jobs/csv/sample', [\App\Http\Controllers\Coordinator\ProjectJobCsvController::class, 'downloadSample'])->name('project_jobs.csv.sample');
        Route::post('project-jobs/csv/analyze', [\App\Http\Controllers\Coordinator\ProjectJobCsvController::class, 'analyzeCsv'])->name('project_jobs.csv.analyze');
        Route::post('project-jobs/csv/import', [\App\Http\Controllers\Coordinator\ProjectJobCsvController::class, 'importCsv'])->name('project_jobs.csv.import');
        Route::post('project-jobs/csv/client-create', [\App\Http\Controllers\Coordinator\ProjectJobCsvController::class, 'apiClientCreate'])->name('project_jobs.csv.client_create');
        // 案件一括作成（静的パスなので {projectJob} より前に定義）
        Route::get('project-jobs/bulk-create', [App\Http\Controllers\Coordinator\BulkProjectJobController::class, 'index'])->name('project_jobs.bulk_create.index');
        Route::get('project-jobs/bulk-create/sample', [App\Http\Controllers\Coordinator\BulkProjectJobController::class, 'downloadSample'])->name('project_jobs.bulk_create.sample');
        Route::post('project-jobs/bulk-create/preview', [App\Http\Controllers\Coordinator\BulkProjectJobController::class, 'preview'])->name('project_jobs.bulk_create.preview');
        Route::post('project-jobs/bulk-create/store', [App\Http\Controllers\Coordinator\BulkProjectJobController::class, 'store'])->name('project_jobs.bulk_create.store');
        // テンプレートから案件1件作成
        Route::post('project-jobs/from-template', [App\Http\Controllers\Coordinator\ProjectJobController::class, 'storeFromTemplate'])->name('project_jobs.store_from_template');
        // 案件テンプレートCRUD（静的パスなので {projectJob} より前に定義）
        Route::get('project-job-templates', [App\Http\Controllers\Coordinator\ProjectJobTemplateController::class, 'index'])->name('project_job_templates.index');
        Route::post('project-job-templates', [App\Http\Controllers\Coordinator\ProjectJobTemplateController::class, 'store'])->name('project_job_templates.store');
        Route::put('project-job-templates/{template}', [App\Http\Controllers\Coordinator\ProjectJobTemplateController::class, 'update'])->name('project_job_templates.update');
        Route::delete('project-job-templates/{template}', [App\Http\Controllers\Coordinator\ProjectJobTemplateController::class, 'destroy'])->name('project_job_templates.destroy');
        // Static assignment routes must come before {projectJob} parameterized routes
        Route::get('project_jobs/past-assignments', [App\Http\Controllers\Coordinator\ProjectJobAssignmentsController::class, 'pastData'])->name('project_jobs.past_assignments');
        Route::get('project_jobs/assignment-select', [App\Http\Controllers\Coordinator\ProjectJobAssignmentsController::class, 'selectProject'])->name('project_jobs.assignment_select');
        Route::get('project_jobs/calendar', [App\Http\Controllers\Coordinator\ProjectJobsCalendarController::class, 'index'])->name('project_jobs.calendar');
        Route::post('project_jobs/check-duplicate', [App\Http\Controllers\Coordinator\ProjectJobController::class, 'checkDuplicate'])->name('project_jobs.check_duplicate');
        Route::post('project_jobs/ocr/analyze', [App\Http\Controllers\Coordinator\ProjectJobController::class, 'analyzeOcr'])->name('project_jobs.ocr.analyze');
        Route::post('project_jobs', [App\Http\Controllers\Coordinator\ProjectJobController::class, 'store'])->name('project_jobs.store');
        Route::post('project_jobs/{projectJob}/complete', [App\Http\Controllers\Coordinator\ProjectJobController::class, 'complete'])->name('project_jobs.complete');
        Route::post('project_jobs/{projectJob}/uncomplete', [App\Http\Controllers\Coordinator\ProjectJobController::class, 'uncomplete'])->name('project_jobs.uncomplete');
        Route::post('project_jobs/{projectJob}/favorite', [App\Http\Controllers\Coordinator\ProjectJobController::class, 'toggleFavorite'])->name('project_jobs.favorite');
        Route::post('project_jobs/{projectJob}/clone', [App\Http\Controllers\Coordinator\ProjectJobController::class, 'clone'])->name('project_jobs.clone');
        Route::post('project_jobs/{projectJob}/share', [App\Http\Controllers\Coordinator\ProjectJobController::class, 'share'])->name('project_jobs.share');
        Route::post('project_jobs/{projectJob}/image', [App\Http\Controllers\Coordinator\ProjectJobController::class, 'storeImage'])->name('project_jobs.image.store');
        Route::delete('project_jobs/{projectJob}/image', [App\Http\Controllers\Coordinator\ProjectJobController::class, 'deleteImage'])->name('project_jobs.image.destroy');
        Route::patch('project_jobs/{projectJob}/ocr-apply', [App\Http\Controllers\Coordinator\ProjectJobController::class, 'applyOcrResult'])->name('project_jobs.ocr.apply');
        // メンバー予定表（静的サブパスなので {projectJob} の前に定義）
        Route::get('project_jobs/{projectJob}/member-schedule', [App\Http\Controllers\Coordinator\ProjectJobMemberScheduleController::class, 'index'])->name('project_jobs.member_schedule');
        Route::get('project_jobs/{projectJob}/member-schedule/data', [App\Http\Controllers\Coordinator\ProjectJobMemberScheduleController::class, 'data'])->name('project_jobs.member_schedule.data');
        Route::get('project_jobs/{projectJob}', [App\Http\Controllers\Coordinator\ProjectJobController::class, 'show'])->name('project_jobs.show');
        Route::get('project_jobs/{projectJob}/edit', [App\Http\Controllers\Coordinator\ProjectJobController::class, 'edit'])->name('project_jobs.edit');
        Route::put('project_jobs/{projectJob}', [App\Http\Controllers\Coordinator\ProjectJobController::class, 'update'])->name('project_jobs.update');
        Route::delete('project_jobs/{projectJob}', [App\Http\Controllers\Coordinator\ProjectJobController::class, 'destroy'])->name('project_jobs.destroy');
        // Project job assignment (JobAssign)
        Route::get('project_jobs/{projectJob}/assignments',[App\Http\Controllers\Coordinator\ProjectJobAssignmentsController::class, 'index'])->name('project_jobs.assignments.index');
        Route::get('project_jobs/{projectJob}/assignments/create', [App\Http\Controllers\Coordinator\ProjectJobAssignmentsController::class, 'create'])->name('project_jobs.assignments.create');
        // 複合ジョブ作成（static routeは {assignment} より前に定義する）
        Route::get('project_jobs/{projectJob}/assignments/composite/create', [App\Http\Controllers\Coordinator\CompositeJobAssignmentController::class, 'create'])->name('project_jobs.assignments.composite.create');
        Route::post('project_jobs/{projectJob}/assignments/composite', [App\Http\Controllers\Coordinator\CompositeJobAssignmentController::class, 'store'])->name('project_jobs.assignments.composite.store');
        Route::get('project_jobs/{projectJob}/assignments/{assignment}/edit', [App\Http\Controllers\Coordinator\ProjectJobAssignmentsController::class, 'edit'])->name('project_jobs.assignments.edit');
        // Show (read-only) view for a single assignment
        Route::get('project_jobs/{projectJob}/assignments/{assignment}', [App\Http\Controllers\Coordinator\ProjectJobAssignmentsController::class, 'show'])->name('project_jobs.assignments.show');
        Route::post('project_jobs/{projectJob}/assignments', [App\Http\Controllers\Coordinator\ProjectJobAssignmentsController::class, 'store'])->name('project_jobs.assignments.store');
        Route::put('project_jobs/{projectJob}/assignments/{assignment}', [App\Http\Controllers\Coordinator\ProjectJobAssignmentsController::class, 'update'])->name('project_jobs.assignments.update');
        Route::delete('project_jobs/{projectJob}/assignments/{assignment}', [App\Http\Controllers\Coordinator\ProjectJobAssignmentsController::class, 'destroy'])->name('project_jobs.assignments.destroy');

        Route::get(
            'project_jobs/{projectJob}/schedule',
            [App\Http\Controllers\Coordinator\ProjectJobController::class, 'schedule']
        )
            ->name('project_jobs.schedule');

        // PoC: ProjectSchedules (Gantt)
        Route::get('project_schedules', [App\Http\Controllers\Coordinator\ProjectSchedulesController::class, 'index'])->name('project_schedules.index');
        Route::get('project_schedules/create', [App\Http\Controllers\Coordinator\ProjectSchedulesController::class, 'create'])->name('project_schedules.create');
        Route::post('project_schedules', [App\Http\Controllers\Coordinator\ProjectSchedulesController::class, 'store'])->name('project_schedules.store');
        Route::patch('project_schedules/{project_schedule}', [App\Http\Controllers\Coordinator\ProjectSchedulesController::class, 'update'])->name('project_schedules.update');
        Route::delete('project_schedules/{project_schedule}', [App\Http\Controllers\Coordinator\ProjectSchedulesController::class, 'destroy'])->name('project_schedules.destroy');
        Route::patch('project_schedules/{project_schedule}/uncomplete', [App\Http\Controllers\Coordinator\ProjectSchedulesController::class, 'uncomplete'])->name('project_schedules.uncomplete');
        Route::post('project_schedules/bulk_update', [App\Http\Controllers\Coordinator\ProjectSchedulesController::class, 'bulkUpdate'])->name('project_schedules.bulk_update');
        Route::get('project_schedules/csv_export', [App\Http\Controllers\Coordinator\ProjectSchedulesController::class, 'csvExport'])->name('project_schedules.csv_export');
        Route::post('project_schedules/csv_import', [App\Http\Controllers\Coordinator\ProjectSchedulesController::class, 'csvImport'])->name('project_schedules.csv_import');

        // Calendar PoC for ProjectSchedules
        Route::get('project_schedules/calendar', [App\Http\Controllers\Coordinator\ProjectSchedulesCalendarController::class, 'index'])->name('project_schedules.calendar');
        Route::patch('project_schedules/{project_schedule}/calendar', [App\Http\Controllers\Coordinator\ProjectSchedulesCalendarController::class, 'update'])->name('project_schedules.calendar.update');

        // ProjectSchedule comments (memos) - minimal PoC routes
        Route::get('project_schedules/{project_schedule}/comments/create', [App\Http\Controllers\Coordinator\ProjectScheduleCommentsController::class, 'create'])->name('project_schedule_comments.create');
        Route::post('project_schedules/{project_schedule}/comments', [App\Http\Controllers\Coordinator\ProjectScheduleCommentsController::class, 'store'])->name('project_schedule_comments.store');
        Route::match(['put', 'patch'], 'project_schedules/comments/{comment}', [App\Http\Controllers\Coordinator\ProjectScheduleCommentsController::class, 'update'])->name('project_schedule_comments.update');
        Route::get('project_schedules/comments/{comment}', [App\Http\Controllers\Coordinator\ProjectScheduleCommentsController::class, 'show'])->name('project_schedule_comments.show');

        // Project-level memos (date-based notes) - new resource
        Route::get('project_memos', [App\Http\Controllers\Coordinator\ProjectMemosController::class, 'index'])->name('project_memos.index');
        Route::post('project_memos', [App\Http\Controllers\Coordinator\ProjectMemosController::class, 'store'])->name('project_memos.store');
        Route::match(['put', 'patch'], 'project_memos/{memo}', [App\Http\Controllers\Coordinator\ProjectMemosController::class, 'update'])->name('project_memos.update');
        Route::get('project_memos/{memo}', [App\Http\Controllers\Coordinator\ProjectMemosController::class, 'show'])->name('project_memos.show');
        Route::delete('project_memos/{memo}', [App\Http\Controllers\Coordinator\ProjectMemosController::class, 'destroy'])->name('project_memos.destroy');

        // 週間プランナー掲示板（週別投稿）
        Route::get('project_jobs/{projectJob}/week-posts', [App\Http\Controllers\Coordinator\ProjectScheduleWeekPostController::class, 'index'])->name('project_jobs.week_posts.index');
        Route::post('project_jobs/{projectJob}/week-posts', [App\Http\Controllers\Coordinator\ProjectScheduleWeekPostController::class, 'store'])->name('project_jobs.week_posts.store');

        // 連携設定（G-01）: 進行表ごとの連携設定 CRUD
        Route::get('progress-sheets/{sheet}/link-settings', [App\Http\Controllers\Coordinator\ProgressSheetItemController::class, 'index'])->name('progress_sheets.link_settings.index');
        Route::post('progress-sheets/{sheet}/link-settings', [App\Http\Controllers\Coordinator\ProgressSheetItemController::class, 'store'])->name('progress_sheets.link_settings.store');
        Route::put('progress-sheets/{sheet}/link-settings/{item}', [App\Http\Controllers\Coordinator\ProgressSheetItemController::class, 'update'])->name('progress_sheets.link_settings.update');
        Route::delete('progress-sheets/{sheet}/link-settings/{item}', [App\Http\Controllers\Coordinator\ProgressSheetItemController::class, 'destroy'])->name('progress_sheets.link_settings.destroy');
        Route::get('progress-sheets/{sheet}/link-settings/propose', [App\Http\Controllers\Coordinator\ProgressSheetItemController::class, 'propose'])->name('progress_sheets.link_settings.propose');
        Route::post('progress-sheets/{sheet}/link-settings/import', [App\Http\Controllers\Coordinator\ProgressSheetItemController::class, 'importFromSheet'])->name('progress_sheets.link_settings.import');
        Route::post('progress-sheets/{sheet}/link-settings/recalculate', [App\Http\Controllers\Coordinator\ProgressSheetItemController::class, 'recalculate'])->name('progress_sheets.link_settings.recalculate');
        // 連携設定（G-01）: カレンダー用・案件単位で calendar_linked 項目を返す
        Route::get('project_jobs/{projectJob}/link-settings', [App\Http\Controllers\Coordinator\ProgressSheetItemController::class, 'indexForCalendar'])->name('project_jobs.link_settings.index');

        // Project_team_members リソースルート
        Route::resource('project_team_members', App\Http\Controllers\Coordinator\ProjectTeamMembersController::class)->names([
            'create' => 'project_team_members.create',
            'store' => 'project_team_members.store',
            'show' => 'project_team_members.show',
            'edit' => 'project_team_members.edit',
            'update' => 'project_team_members.update',
            'destroy' => 'project_team_members.destroy',
        ]);

        // Coordinator Work Items (Workflows / tasks)
        // (work-items routes removed - lookups are provided by assignment controllers)
        // JobBox (job-assignment related messages)
        Route::get('project_jobs/{projectJob}/jobbox', [\App\Http\Controllers\ProjectJobs\JobBoxController::class, 'index'])->name('project_jobs.jobbox.index');
        Route::get('project_jobs/{projectJob}/jobbox/{message}', [\App\Http\Controllers\ProjectJobs\JobBoxController::class, 'show'])->name('project_jobs.jobbox.show');
        Route::get('project_jobs/{projectJob}/jobbox/{message}/edit', [\App\Http\Controllers\ProjectJobs\JobBoxController::class, 'edit'])->name('project_jobs.jobbox.edit');
        Route::put('project_jobs/{projectJob}/jobbox/{message}', [\App\Http\Controllers\ProjectJobs\JobBoxController::class, 'update'])->name('project_jobs.jobbox.update');
        Route::post('project_jobs/{projectJob}/jobbox', [\App\Http\Controllers\ProjectJobs\JobBoxController::class, 'store'])->name('project_jobs.jobbox.store');
        Route::delete('project_jobs/{projectJob}/jobbox/{message}', [\App\Http\Controllers\ProjectJobs\JobBoxController::class, 'destroy'])->name('project_jobs.jobbox.destroy');
        // Project job analysis (ジョブ分析)
        Route::get('project_jobs/{projectJob}/analysis', [App\Http\Controllers\Coordinator\ProjectJobController::class, 'analysis'])->name('project_jobs.analysis');

        // ── 進行管理テンプレート ────────────────────────────────────
        Route::get('progress-templates', [App\Http\Controllers\Coordinator\ProgressTemplateController::class, 'index'])->name('progress_templates.index');
        Route::get('progress-templates/create', [App\Http\Controllers\Coordinator\ProgressTemplateController::class, 'create'])->name('progress_templates.create');
        Route::post('progress-templates', [App\Http\Controllers\Coordinator\ProgressTemplateController::class, 'store'])->name('progress_templates.store');
        Route::get('progress-templates/{template}', [App\Http\Controllers\Coordinator\ProgressTemplateController::class, 'show'])->name('progress_templates.show');
        Route::get('progress-templates/{template}/edit', [App\Http\Controllers\Coordinator\ProgressTemplateController::class, 'edit'])->name('progress_templates.edit');
        Route::put('progress-templates/{template}', [App\Http\Controllers\Coordinator\ProgressTemplateController::class, 'update'])->name('progress_templates.update');
        Route::delete('progress-templates/{template}', [App\Http\Controllers\Coordinator\ProgressTemplateController::class, 'destroy'])->name('progress_templates.destroy');

        // ── 進行管理シート ────────────────────────────────────────
        Route::post('project_jobs/{projectJob}/progress-sheets', [App\Http\Controllers\Coordinator\ProgressSheetController::class, 'store'])->name('project_jobs.progress_sheets.store');
        Route::put('project_jobs/{projectJob}/progress-sheets/reorder', [App\Http\Controllers\Coordinator\ProgressSheetController::class, 'reorderSheets'])->name('project_jobs.progress_sheets.reorder');
        Route::get('progress-sheets/{sheet}', [App\Http\Controllers\Coordinator\ProgressSheetController::class, 'show'])->name('progress_sheets.show');
        Route::put('progress-sheets/{sheet}', [App\Http\Controllers\Coordinator\ProgressSheetController::class, 'update'])->name('progress_sheets.update');
        Route::delete('progress-sheets/{sheet}', [App\Http\Controllers\Coordinator\ProgressSheetController::class, 'destroy'])->name('progress_sheets.destroy');
        Route::post('progress-sheets/{sheet}/register-template', [App\Http\Controllers\Coordinator\ProgressSheetController::class, 'registerAsTemplate'])->name('progress_sheets.register_template');

        // ── 行管理 ────────────────────────────────────────────────
        Route::post('progress-sheets/{sheet}/rows', [App\Http\Controllers\Coordinator\ProgressRowController::class, 'store'])->name('progress_sheets.rows.store');
        Route::put('progress-sheets/{sheet}/rows/{row}', [App\Http\Controllers\Coordinator\ProgressRowController::class, 'update'])->name('progress_sheets.rows.update');
        Route::delete('progress-sheets/{sheet}/rows/{row}', [App\Http\Controllers\Coordinator\ProgressRowController::class, 'destroy'])->name('progress_sheets.rows.destroy');
        Route::post('progress-sheets/{sheet}/rows/import', [App\Http\Controllers\Coordinator\ProgressRowController::class, 'import'])->name('progress_sheets.rows.import');
        Route::post('progress-sheets/{sheet}/rows/{row}/make-group', [App\Http\Controllers\Coordinator\ProgressRowController::class, 'makeGroup'])->name('progress_sheets.rows.make_group');
        Route::post('progress-sheets/{sheet}/rows/{row}/duplicate', [App\Http\Controllers\Coordinator\ProgressRowController::class, 'duplicate'])->name('progress_sheets.rows.duplicate');
        Route::put('progress-sheets/{sheet}/rows-reorder', [App\Http\Controllers\Coordinator\ProgressRowController::class, 'reorder'])->name('progress_sheets.rows.reorder');

        // ── セル一括更新 ──────────────────────────────────────────
        Route::put('progress-sheets/{sheet}/cells', [App\Http\Controllers\Coordinator\ProgressCellController::class, 'bulkUpdate'])->name('progress_sheets.cells.update');
        Route::post('progress-sheets/{sheet}/cells/link-job', [App\Http\Controllers\Coordinator\ProgressSheetController::class, 'linkJob'])->name('progress_sheets.cells.link_job');
        Route::delete('progress-sheets/{sheet}/cells/unlink-job', [App\Http\Controllers\Coordinator\ProgressSheetController::class, 'unlinkJob'])->name('progress_sheets.cells.unlink_job');

        // ── アサインメント完了管理（管理者用）─────────────────────
        Route::post('progress-sheets/assignments/{assignment}/complete', [App\Http\Controllers\Coordinator\ProgressSheetController::class, 'completeAssignment'])->name('progress_sheets.assignments.complete');
        Route::post('progress-sheets/assignments/{assignment}/uncomplete', [App\Http\Controllers\Coordinator\ProgressSheetController::class, 'uncompleteAssignment'])->name('progress_sheets.assignments.uncomplete');
        Route::post('progress-sheets/assignments/{assignment}/proof-complete', [App\Http\Controllers\Coordinator\ProgressSheetController::class, 'proofDirectComplete'])->name('progress_sheets.assignments.proof_complete');
        // ── V2 セル操作 ───────────────────────────────────────────
        Route::post('progress-cells/{cell}/complete', [App\Http\Controllers\Coordinator\ProgressCellController::class, 'complete'])->name('progress_cells.complete');
        Route::patch('progress-cells/{cell}/deadline', [App\Http\Controllers\Coordinator\ProgressCellController::class, 'deadline'])->name('progress_cells.deadline');
        Route::patch('progress-cells/{cell}/note', [App\Http\Controllers\Coordinator\ProgressCellController::class, 'note'])->name('progress_cells.note');
        Route::post('progress-sheets/{sheet}/cell-note', [App\Http\Controllers\Coordinator\ProgressCellController::class, 'noteByPosition'])->name('progress_sheets.cell_note');

        // ── V2 シート変換 ─────────────────────────────────────────
        Route::get('progress-sheets/{sheet}/convert-preview', [App\Http\Controllers\Coordinator\ProgressSheetController::class, 'convertPreview'])->name('progress_sheets.convert_preview');
        Route::put('progress-sheets/{sheet}/convert-to-v2', [App\Http\Controllers\Coordinator\ProgressSheetController::class, 'convertToV2'])->name('progress_sheets.convert_to_v2');

        // ── V2 共有リンク ─────────────────────────────────────────
        Route::post('progress-sheets/{sheet}/share', [App\Http\Controllers\Coordinator\ProgressSheetController::class, 'share'])->name('progress_sheets.share');
        Route::delete('progress-sheets/{sheet}/share', [App\Http\Controllers\Coordinator\ProgressSheetController::class, 'unshare'])->name('progress_sheets.unshare');

        // ── V-16 印刷 ─────────────────────────────────────────────
        Route::get('progress-sheets/{sheet}/print', [App\Http\Controllers\Coordinator\ProgressSheetController::class, 'printView'])->name('progress_sheets.print');

        // ── 進行表一覧 ─────────────────────────────────────────────
        Route::get('progress-sheet-list', [App\Http\Controllers\Coordinator\ProgressSheetListController::class, 'index'])->name('progress_sheet_list.index');
        Route::get('progress-sheet-list/create-projects-json', [App\Http\Controllers\Coordinator\ProgressSheetListController::class, 'createProjectsJson'])->name('progress_sheet_list.create_projects_json');
        Route::post('progress-sheet-list/favorite/{sheet}', [App\Http\Controllers\Coordinator\ProgressSheetListController::class, 'toggleFavorite'])->name('progress_sheet_list.favorite');
        Route::get('workflow-sheet-list', [App\Http\Controllers\Coordinator\WorkflowSheetListController::class, 'index'])->name('workflow_sheet_list.index');
        Route::get('workflow-sheet-list/create-projects-json', [App\Http\Controllers\Coordinator\WorkflowSheetListController::class, 'createProjectsJson'])->name('workflow_sheet_list.create_projects_json');
        Route::post('workflow-sheet-list/favorite/{sheet}', [App\Http\Controllers\Coordinator\WorkflowSheetListController::class, 'toggleFavorite'])->name('workflow_sheet_list.favorite');

        // ── V-12 進行レポート ───────────────────────────────────────
        Route::get('progress-report', [App\Http\Controllers\Coordinator\ProgressReportController::class, 'index'])->name('progress_report.index');

        // ── Coordinator 設定 ───────────────────────────────────────
        Route::get('settings', [App\Http\Controllers\Coordinator\CoordinatorSettingController::class, 'index'])->name('settings.index');
        Route::get('settings/data', [App\Http\Controllers\Coordinator\CoordinatorSettingController::class, 'show'])->name('settings.data');
        Route::put('settings', [App\Http\Controllers\Coordinator\CoordinatorSettingController::class, 'update'])->name('settings.update');

        // ── 項目リスト ─────────────────────────────────────────────
        Route::get('project_jobs/{projectJob}/item-entries', [App\Http\Controllers\Coordinator\ItemEntryController::class, 'index'])->name('item_entries.index');
        Route::put('project_jobs/{projectJob}/item-entries', [App\Http\Controllers\Coordinator\ItemEntryController::class, 'update'])->name('item_entries.update');
        Route::get('project_jobs/{projectJob}/item-entries/suggestions', [App\Http\Controllers\Coordinator\ItemEntryController::class, 'suggestions'])->name('item_entries.suggestions');

        // ── 工程シート ─────────────────────────────────────────────
        Route::post('project_jobs/{projectJob}/workflow-sheets', [App\Http\Controllers\Coordinator\WorkflowSheetController::class, 'store'])->name('project_jobs.workflow_sheets.store');
        Route::put('project_jobs/{projectJob}/workflow-sheets/reorder', [App\Http\Controllers\Coordinator\WorkflowSheetController::class, 'reorder'])->name('project_jobs.workflow_sheets.reorder');
        Route::get('workflow-sheets/{sheet}', [App\Http\Controllers\Coordinator\WorkflowSheetController::class, 'show'])->name('workflow_sheets.show');
        Route::put('workflow-sheets/{sheet}', [App\Http\Controllers\Coordinator\WorkflowSheetController::class, 'update'])->name('workflow_sheets.update');
        Route::delete('workflow-sheets/{sheet}', [App\Http\Controllers\Coordinator\WorkflowSheetController::class, 'destroy'])->name('workflow_sheets.destroy');
        Route::post('workflow-sheets/{sheet}/rows', [App\Http\Controllers\Coordinator\WorkflowRowController::class, 'store'])->name('workflow_sheets.rows.store');
        Route::post('workflow-sheets/{sheet}/rows/import', [App\Http\Controllers\Coordinator\WorkflowRowController::class, 'import'])->name('workflow_sheets.rows.import');
        Route::put('workflow-sheets/{sheet}/rows/{row}', [App\Http\Controllers\Coordinator\WorkflowRowController::class, 'update'])->name('workflow_sheets.rows.update');
        Route::delete('workflow-sheets/{sheet}/rows/{row}', [App\Http\Controllers\Coordinator\WorkflowRowController::class, 'destroy'])->name('workflow_sheets.rows.destroy');
        Route::put('workflow-sheets/{sheet}/rows/reorder', [App\Http\Controllers\Coordinator\WorkflowRowController::class, 'reorder'])->name('workflow_sheets.rows.reorder');
        Route::put('workflow-sheets/{sheet}/cells', [App\Http\Controllers\Coordinator\WorkflowCellController::class, 'bulkUpdate'])->name('workflow_sheets.cells.update');
        Route::post('workflow-sheets/{sheet}/cells/register', [App\Http\Controllers\Coordinator\WorkflowCellController::class, 'register'])->name('workflow_sheets.cells.register');
        Route::post('workflow-cells/{cell}/complete', [App\Http\Controllers\Coordinator\WorkflowCellController::class, 'complete'])->name('workflow_cells.complete');
        Route::post('workflow-cells/{cell}/unregister', [App\Http\Controllers\Coordinator\WorkflowCellController::class, 'unregister'])->name('workflow_cells.unregister');
        Route::get('workflow-sheets/{sheet}/print', [App\Http\Controllers\Coordinator\WorkflowSheetController::class, 'printView'])->name('workflow_sheets.print');
        Route::post('workflow-sheets/{sheet}/register-template', [App\Http\Controllers\Coordinator\WorkflowSheetController::class, 'registerAsTemplate'])->name('workflow_sheets.register_template');
        Route::post('workflow-sheets/{sheet}/share', [App\Http\Controllers\Coordinator\WorkflowSheetController::class, 'share'])->name('workflow_sheets.share');
        Route::delete('workflow-sheets/{sheet}/share', [App\Http\Controllers\Coordinator\WorkflowSheetController::class, 'unshare'])->name('workflow_sheets.unshare');

        // ── 工程シートテンプレート ──────────────────────────────────
        Route::get('workflow-templates', [App\Http\Controllers\Coordinator\WorkflowTemplateController::class, 'index'])->name('workflow_templates.index');
        Route::post('workflow-templates', [App\Http\Controllers\Coordinator\WorkflowTemplateController::class, 'store'])->name('workflow_templates.store');
        Route::put('workflow-templates/{template}', [App\Http\Controllers\Coordinator\WorkflowTemplateController::class, 'update'])->name('workflow_templates.update');
        Route::delete('workflow-templates/{template}', [App\Http\Controllers\Coordinator\WorkflowTemplateController::class, 'destroy'])->name('workflow_templates.destroy');

        // ── ゴーストユーザー管理 ────────────────────────────────────
        Route::get('ghost-users', [App\Http\Controllers\Coordinator\GhostUserController::class, 'index'])->name('ghost_users.index');
        Route::get('ghost-users/guide', [App\Http\Controllers\Coordinator\GhostUserController::class, 'guide'])->name('ghost_users.guide');
        Route::post('ghost-users', [App\Http\Controllers\Coordinator\GhostUserController::class, 'store'])->name('ghost_users.store');
        Route::delete('ghost-users/{ghostUserId}', [App\Http\Controllers\Coordinator\GhostUserController::class, 'destroy'])->name('ghost_users.destroy');
        Route::post('ghost-users/{ghostUserId}/switch', [App\Http\Controllers\Coordinator\GhostUserController::class, 'switch'])->name('ghost_users.switch');

        // 営業担当管理（Coordinator用）
        Route::get('sales-reps/api/list', [\App\Http\Controllers\Coordinator\SalesRepController::class, 'apiList'])->name('sales_reps.api.list');
        Route::post('sales-reps/api/create', [\App\Http\Controllers\Coordinator\SalesRepController::class, 'apiCreate'])->name('sales_reps.api.create');
        Route::get('sales-reps', [\App\Http\Controllers\Coordinator\SalesRepController::class, 'index'])->name('sales_reps.index');
        Route::post('sales-reps', [\App\Http\Controllers\Coordinator\SalesRepController::class, 'store'])->name('sales_reps.store');
        Route::post('sales-reps/bulk',    [\App\Http\Controllers\Coordinator\SalesRepController::class, 'bulkStore'])->name('sales_reps.bulkStore');
        Route::post('sales-reps/reorder', [\App\Http\Controllers\Coordinator\SalesRepController::class, 'reorder'])->name('sales_reps.reorder');
        Route::patch('sales-reps/{salesRep}', [\App\Http\Controllers\Coordinator\SalesRepController::class, 'update'])->name('sales_reps.update');
        Route::delete('sales-reps/{salesRep}', [\App\Http\Controllers\Coordinator\SalesRepController::class, 'destroy'])->name('sales_reps.destroy');
    });

// ゴーストセッション復帰（ghost ユーザーが呼ぶため coordinator middleware 外）
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])
    ->post('/coordinator/ghost/exit', [App\Http\Controllers\Coordinator\GhostUserController::class, 'exit'])
    ->name('coordinator.ghost.exit');



// デバッグ・テスト用ページのルート例
// 今後も任意のVueページをテスト表示したい場合は、下記のようにInertia::renderでページ名を指定してください。
// 例: /debug/create → resources/js/Pages/Diaries/CreateDebug.vue
// 例: /debug/other  → resources/js/Pages/OtherDebug.vue


// 進行管理表 共有URL（認証不要・公開）
Route::get('/shared/progress-sheets/{token}', [App\Http\Controllers\Shared\ProgressSheetController::class, 'show'])->name('shared.progress_sheets.show');
Route::get('/shared/progress-sheets/{token}/print', [App\Http\Controllers\Shared\ProgressSheetController::class, 'printView'])->name('shared.progress_sheets.print');

// 管理シート 共有URL（認証不要・公開）
Route::get('/shared/workflow-sheets/{token}', [App\Http\Controllers\Shared\WorkflowSheetController::class, 'show'])->name('shared.workflow_sheets.show');

Route::get('/debug/create', function () {
    return Inertia::render('Diaries/CreateDebug');
})->name('debug.create');

// (temporary debug routes removed)

// =====================================================
// ProofCoordinator Routes（校正窓口 / Admin / SuperAdmin / 部署Leader）
// サン・ブレーン専用（company_type: sunbrain）
// =====================================================
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'company_type:sunbrain', 'proof_coordinator'])
    ->prefix('proof-coordinator')
    ->name('proof_coordinator.')
    ->group(function () {
        Route::get('dashboard', [\App\Http\Controllers\ProofCoordinator\ProofRequestController::class, 'dashboard'])->name('dashboard');
        Route::get('inbox', [\App\Http\Controllers\ProofCoordinator\ProofRequestController::class, 'inbox'])->name('inbox');
        Route::get('inbox/{proofRequest}/assign', [\App\Http\Controllers\ProofCoordinator\ProofRequestController::class, 'assignPage'])->name('inbox.assign_page');
        Route::post('inbox/{proofRequest}/assign', [\App\Http\Controllers\ProofCoordinator\ProofRequestController::class, 'assignStore'])->name('inbox.assign_store');
        Route::post('inbox/{proofRequest}/accept', [\App\Http\Controllers\ProofCoordinator\ProofRequestController::class, 'accept'])->name('inbox.accept');
        Route::get('assignments', [\App\Http\Controllers\ProofCoordinator\ProofRequestController::class, 'assignments'])->name('assignments');
        Route::get('assignments/{proofRequest}', [\App\Http\Controllers\ProofCoordinator\ProofRequestController::class, 'show'])->name('assignments.show');
        Route::get('assignments/{proofRequest}/edit', [\App\Http\Controllers\ProofCoordinator\ProofRequestController::class, 'edit'])->name('assignments.edit');
        Route::put('assignments/{proofRequest}/assignment', [\App\Http\Controllers\ProofCoordinator\ProofRequestController::class, 'assignmentUpdate'])->name('assignments.assignment_update');
        Route::delete('assignments/{proofRequest}/events/{event}', [\App\Http\Controllers\ProofCoordinator\ProofRequestController::class, 'destroyEvent'])->name('assignments.event_destroy');
        Route::put('assignments/{proofRequest}/assign', [\App\Http\Controllers\ProofCoordinator\ProofRequestController::class, 'assign'])->name('assignments.assign');
        Route::put('assignments/{proofRequest}/start', [\App\Http\Controllers\ProofCoordinator\ProofRequestController::class, 'start'])->name('assignments.start');
        Route::put('assignments/{proofRequest}/complete', [\App\Http\Controllers\ProofCoordinator\ProofRequestController::class, 'complete'])->name('assignments.complete');
        Route::put('assignments/{proofRequest}/uncomplete', [\App\Http\Controllers\ProofCoordinator\ProofRequestController::class, 'uncomplete'])->name('assignments.uncomplete');
        Route::get('calendar', [\App\Http\Controllers\ProofCoordinator\CalendarController::class, 'index'])->name('calendar');
        Route::get('calendar/data', [\App\Http\Controllers\ProofCoordinator\CalendarController::class, 'data'])->name('calendar.data');
        Route::get('calendar/picker-data', [\App\Http\Controllers\ProofCoordinator\CalendarController::class, 'pickerData'])->name('calendar.picker_data');
        Route::post('schedules', [\App\Http\Controllers\ProofCoordinator\CalendarController::class, 'store'])->name('schedules.store');
        Route::put('schedules/{proofSchedule}', [\App\Http\Controllers\ProofCoordinator\CalendarController::class, 'update'])->name('schedules.update');
        Route::delete('schedules/{proofSchedule}', [\App\Http\Controllers\ProofCoordinator\CalendarController::class, 'destroy'])->name('schedules.destroy');
        Route::get('jobs', [\App\Http\Controllers\ProofCoordinator\ProofRequestController::class, 'jobManagement'])->name('jobs');
        Route::get('workload', [\App\Http\Controllers\ProofCoordinator\ProofRequestController::class, 'workload'])->name('workload');
        Route::get('history', [\App\Http\Controllers\ProofCoordinator\ProofRequestController::class, 'history'])->name('history');
        Route::get('team', [\App\Http\Controllers\ProofCoordinator\ProofTeamController::class, 'index'])->name('team.index');
        Route::post('team', [\App\Http\Controllers\ProofCoordinator\ProofTeamController::class, 'store'])->name('team.store');
        Route::post('team/reorder', [\App\Http\Controllers\ProofCoordinator\ProofTeamController::class, 'reorder'])->name('team.reorder');
        Route::delete('team/{proofTeamMember}', [\App\Http\Controllers\ProofCoordinator\ProofTeamController::class, 'destroy'])->name('team.destroy');
        // 単発派遣管理
        Route::post('dispatchers/check-duplicate', [\App\Http\Controllers\ProofCoordinator\ProofDispatcherController::class, 'checkDuplicate'])->name('dispatchers.check_duplicate');
        Route::put('dispatchers/{dispatcher}/toggle', [\App\Http\Controllers\ProofCoordinator\ProofDispatcherController::class, 'toggle'])->name('dispatchers.toggle');
        Route::resource('dispatchers', \App\Http\Controllers\ProofCoordinator\ProofDispatcherController::class)
            ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
        // 管理シート（校正）
        Route::get('workflow-sheets', [\App\Http\Controllers\ProofCoordinator\WorkflowSheetProofController::class, 'index'])->name('workflow_sheets.index');
        Route::get('workflow-sheets/{sheet}', [\App\Http\Controllers\ProofCoordinator\WorkflowSheetProofController::class, 'show'])->name('workflow_sheets.show');
        Route::get('workflow-sheets/{sheet}/assign', [\App\Http\Controllers\ProofCoordinator\WorkflowSheetProofController::class, 'assignPage'])->name('workflow_sheets.assign_page');
        Route::post('workflow-sheets/{sheet}/assign', [\App\Http\Controllers\ProofCoordinator\WorkflowSheetProofController::class, 'assignStore'])->name('workflow_sheets.assign_store');
        // 進行表（校正）
        Route::get('progress-sheets/{sheet}', [\App\Http\Controllers\ProofCoordinator\ProgressSheetProofController::class, 'show'])->name('progress_sheets.show');
        Route::get('progress-sheets/{sheet}/assign', [\App\Http\Controllers\ProofCoordinator\ProgressSheetProofController::class, 'assignPage'])->name('progress_sheets.assign_page');
        Route::post('progress-sheets/{sheet}/assign', [\App\Http\Controllers\ProofCoordinator\ProgressSheetProofController::class, 'assignStore'])->name('progress_sheets.assign_store');
    });

// =====================================================
// Prepress Routes（製版部署専用）
// サン・ブレーン専用（company_type: sunbrain）
// =====================================================
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'company_type:sunbrain'])
    ->prefix('prepress')
    ->name('prepress.')
    ->group(function () {
        Route::get('dashboard', [\App\Http\Controllers\Prepress\PrepressDashboardController::class, 'index'])->name('dashboard');

        // 伝票ボード
        Route::get('board', [\App\Http\Controllers\Prepress\BoardController::class, 'index'])->name('board');
        Route::patch('board/{ticket}/status', [\App\Http\Controllers\Prepress\BoardController::class, 'updateStatus'])->name('board.updateStatus');
        Route::patch('board/{ticket}/color', [\App\Http\Controllers\Prepress\BoardController::class, 'updateColor'])->name('board.updateColor');
        Route::patch('board/{ticket}/archive', [\App\Http\Controllers\Prepress\BoardController::class, 'archiveFromCompleted'])->name('board.archiveFromCompleted');
        // 伝票登録モーダル用 API
        Route::get('api/clients', [\App\Http\Controllers\Prepress\BoardController::class, 'apiClients'])->name('api.clients');
        Route::post('api/clients', [\App\Http\Controllers\Prepress\BoardController::class, 'apiClientCreate'])->name('api.clientCreate');
        Route::get('api/project-jobs', [\App\Http\Controllers\Prepress\BoardController::class, 'apiProjectJobs'])->name('api.projectJobs');

        // 伝票管理
        Route::get('tickets', [\App\Http\Controllers\Prepress\TicketController::class, 'index'])->name('tickets.index');
        Route::get('tickets/create', [\App\Http\Controllers\Prepress\TicketController::class, 'create'])->name('tickets.create');
        Route::get('tickets/csv/sample', [\App\Http\Controllers\Prepress\TicketController::class, 'downloadSample'])->name('tickets.csv.sample');
        Route::post('tickets/analyze-csv', [\App\Http\Controllers\Prepress\TicketController::class, 'analyzeCsv'])->name('tickets.analyzeCsv');
        Route::post('tickets/import-csv',  [\App\Http\Controllers\Prepress\TicketController::class, 'importCsv'])->name('tickets.importCsv');
        Route::post('tickets', [\App\Http\Controllers\Prepress\TicketController::class, 'store'])->name('tickets.store');
        Route::get('tickets/{ticket}', [\App\Http\Controllers\Prepress\TicketController::class, 'show'])->name('tickets.show');
        Route::get('tickets/{ticket}/edit', [\App\Http\Controllers\Prepress\TicketController::class, 'edit'])->name('tickets.edit');
        Route::patch('tickets/{ticket}', [\App\Http\Controllers\Prepress\TicketController::class, 'update'])->name('tickets.update');
        Route::patch('tickets/{ticket}/status', [\App\Http\Controllers\Prepress\TicketController::class, 'updateStatus'])->name('tickets.updateStatus');
        Route::post('tickets/{ticket}/image', [\App\Http\Controllers\Prepress\TicketController::class, 'updateImage'])->name('tickets.updateImage');
        Route::delete('tickets/{ticket}', [\App\Http\Controllers\Prepress\TicketController::class, 'destroy'])->name('tickets.destroy');

        // 伝票OCR解析 API
        Route::post('ocr/analyze', [\App\Http\Controllers\Prepress\TicketOcrController::class, 'analyze'])->name('ocr.analyze');
        Route::post('ocr/clients/{client}/attach-department', [\App\Http\Controllers\Prepress\TicketOcrController::class, 'attachClientToDepartment'])->name('ocr.attach_department');

        // 営業担当管理
        Route::get('sales-reps', [\App\Http\Controllers\Prepress\SalesRepController::class, 'index'])->name('sales_reps.index');
        Route::post('sales-reps', [\App\Http\Controllers\Prepress\SalesRepController::class, 'store'])->name('sales_reps.store');
        Route::post('sales-reps/bulk',    [\App\Http\Controllers\Prepress\SalesRepController::class, 'bulkStore'])->name('sales_reps.bulkStore');
        Route::post('sales-reps/reorder', [\App\Http\Controllers\Prepress\SalesRepController::class, 'reorder'])->name('sales_reps.reorder');
        Route::patch('sales-reps/{salesRep}', [\App\Http\Controllers\Prepress\SalesRepController::class, 'update'])->name('sales_reps.update');
        Route::delete('sales-reps/{salesRep}', [\App\Http\Controllers\Prepress\SalesRepController::class, 'destroy'])->name('sales_reps.destroy');
        Route::get('api/sales-reps',  [\App\Http\Controllers\Prepress\SalesRepController::class, 'apiList'])->name('api.salesReps');
        Route::post('api/sales-reps', [\App\Http\Controllers\Prepress\SalesRepController::class, 'apiCreate'])->name('api.salesRepCreate');
    });

// 全ロール共通（読み取り専用）
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])
    ->prefix('proof')
    ->name('proof.')
    ->group(function () {
        Route::get('calendar', [\App\Http\Controllers\ProofCoordinator\ProofRequestController::class, 'calendarPublic'])->name('calendar');
        Route::get('calendar/data', [\App\Http\Controllers\ProofCoordinator\ProofRequestController::class, 'calendarUserData'])->name('calendar.data');
        Route::get('status', [\App\Http\Controllers\ProofCoordinator\ProofRequestController::class, 'statusPublic'])->name('status');
    });

// 校正依頼作成・削除（全ロール）
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])
    ->group(function () {
        Route::post('proof-requests', [\App\Http\Controllers\ProofCoordinator\ProofRequestController::class, 'store'])->name('proof_requests.store');
        Route::patch('proof-requests/{proofRequest}/deadline', [\App\Http\Controllers\ProofCoordinator\ProofRequestController::class, 'updateDeadline'])->name('proof_requests.update_deadline');
        Route::delete('proof-requests/{proofRequest}', [\App\Http\Controllers\ProofCoordinator\ProofRequestController::class, 'destroy'])->name('proof_requests.destroy');
    });

// --- デバッグ用API/認証チェックページ ---
// /debug/api でAPI/認証の動作確認ができるVueページ（resources/js/Debug/ApiDebug.vue）
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/debug/api', function () {
        return Inertia::render('Debug/ApiDebug');
    })->name('debug.api');
});

// ── チームルーム ──────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])
    ->prefix('team-rooms')
    ->name('team-rooms.')
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\TeamRoom\TeamRoomController::class, 'index'])->name('index');
        Route::get('/{team}', [\App\Http\Controllers\TeamRoom\TeamRoomController::class, 'show'])->name('show');

        // スケジュール（イベント）— JSON API
        Route::get('/{team}/events', [\App\Http\Controllers\TeamRoom\TeamEventController::class, 'index'])->name('events.index');
        Route::post('/{team}/events', [\App\Http\Controllers\TeamRoom\TeamEventController::class, 'store'])->name('events.store');
        Route::get('/{team}/events/csv-export', [\App\Http\Controllers\TeamRoom\TeamEventController::class, 'csvExport'])->name('events.csv_export');
        Route::post('/{team}/events/csv-import', [\App\Http\Controllers\TeamRoom\TeamEventController::class, 'csvImport'])->name('events.csv_import');
        Route::put('/{team}/events/{event}', [\App\Http\Controllers\TeamRoom\TeamEventController::class, 'update'])->name('events.update');
        Route::delete('/{team}/events/{event}', [\App\Http\Controllers\TeamRoom\TeamEventController::class, 'destroy'])->name('events.destroy');

        // ボード
        Route::post('/{team}/board', [\App\Http\Controllers\TeamRoom\TeamBoardController::class, 'store'])->name('board.store');
        Route::put('/{team}/board/columns', [\App\Http\Controllers\TeamRoom\TeamBoardController::class, 'updateColumns'])->name('board.columns.update');
        Route::post('/{team}/board/cards', [\App\Http\Controllers\TeamRoom\TeamBoardCardController::class, 'store'])->name('board.cards.store');
        Route::get('/{team}/board/cards/{card}', [\App\Http\Controllers\TeamRoom\TeamBoardCardController::class, 'show'])->name('board.cards.show');
        Route::get('/{team}/board/cards/{card}/edit', [\App\Http\Controllers\TeamRoom\TeamBoardCardController::class, 'edit'])->name('board.cards.edit');
        Route::put('/{team}/board/cards/{card}', [\App\Http\Controllers\TeamRoom\TeamBoardCardController::class, 'update'])->name('board.cards.update');
        Route::delete('/{team}/board/cards/{card}', [\App\Http\Controllers\TeamRoom\TeamBoardCardController::class, 'destroy'])->name('board.cards.destroy');

        // 会議記録
        Route::get('/{team}/minutes', [\App\Http\Controllers\TeamRoom\TeamMeetingMinuteController::class, 'index'])->name('minutes.index');
        Route::get('/{team}/minutes/create', [\App\Http\Controllers\TeamRoom\TeamMeetingMinuteController::class, 'create'])->name('minutes.create');
        Route::post('/{team}/minutes', [\App\Http\Controllers\TeamRoom\TeamMeetingMinuteController::class, 'store'])->name('minutes.store');
        Route::get('/{team}/minutes/{minute}', [\App\Http\Controllers\TeamRoom\TeamMeetingMinuteController::class, 'show'])->name('minutes.show');
        Route::get('/{team}/minutes/{minute}/edit', [\App\Http\Controllers\TeamRoom\TeamMeetingMinuteController::class, 'edit'])->name('minutes.edit');
        Route::put('/{team}/minutes/{minute}', [\App\Http\Controllers\TeamRoom\TeamMeetingMinuteController::class, 'update'])->name('minutes.update');
        Route::delete('/{team}/minutes/{minute}', [\App\Http\Controllers\TeamRoom\TeamMeetingMinuteController::class, 'destroy'])->name('minutes.destroy');
        Route::post('/{team}/minutes/{minute}/comments', [\App\Http\Controllers\TeamRoom\TeamMeetingCommentController::class, 'store'])->name('minutes.comments.store');
        Route::delete('/{team}/minutes/{minute}/comments/{comment}', [\App\Http\Controllers\TeamRoom\TeamMeetingCommentController::class, 'destroy'])->name('minutes.comments.destroy');

        // 週間掲示板
        Route::get('/{team}/week-posts', [\App\Http\Controllers\TeamRoom\TeamWeekPostController::class, 'index'])->name('week_posts.index');
        Route::post('/{team}/week-posts', [\App\Http\Controllers\TeamRoom\TeamWeekPostController::class, 'store'])->name('week_posts.store');
        Route::delete('/{team}/week-posts/{post}', [\App\Http\Controllers\TeamRoom\TeamWeekPostController::class, 'destroy'])->name('week_posts.destroy');

        // 係・当番表
        Route::get('/{team}/duty-tables/create', [\App\Http\Controllers\TeamRoom\TeamDutyTableController::class, 'create'])->name('duty-tables.create');
        Route::post('/{team}/duty-tables/preview', [\App\Http\Controllers\TeamRoom\TeamDutyTableController::class, 'preview'])->name('duty-tables.preview');
        Route::post('/{team}/duty-tables', [\App\Http\Controllers\TeamRoom\TeamDutyTableController::class, 'store'])->name('duty-tables.store');
        Route::delete('/{team}/duty-tables/{dutyTable}', [\App\Http\Controllers\TeamRoom\TeamDutyTableController::class, 'destroy'])->name('duty-tables.destroy');

        // メモ・連絡
        Route::get('/{team}/memo-posts', [\App\Http\Controllers\TeamRoom\TeamMemoPostController::class, 'index'])->name('memo-posts.index');
        Route::post('/{team}/memo-posts', [\App\Http\Controllers\TeamRoom\TeamMemoPostController::class, 'store'])->name('memo-posts.store');
        Route::put('/{team}/memo-posts/{memoPost}', [\App\Http\Controllers\TeamRoom\TeamMemoPostController::class, 'update'])->name('memo-posts.update');
        Route::delete('/{team}/memo-posts/{memoPost}', [\App\Http\Controllers\TeamRoom\TeamMemoPostController::class, 'destroy'])->name('memo-posts.destroy');
    });

// セッション Keep-Alive 用 ping エンドポイント
Route::middleware(['auth:sanctum', config('jetstream.auth_session')])->get('/ping', function () {
    return response()->noContent();
})->name('ping');

// ラベルマスタ CRUD API（全ログインユーザー）
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->prefix('label-masters')->group(function () {
    Route::get('/schools',              [\App\Http\Controllers\LabelMasterController::class, 'schoolsIndex']);
    Route::post('/schools',             [\App\Http\Controllers\LabelMasterController::class, 'schoolsStore']);
    Route::put('/schools/{school}',     [\App\Http\Controllers\LabelMasterController::class, 'schoolsUpdate']);
    Route::delete('/schools/{school}',  [\App\Http\Controllers\LabelMasterController::class, 'schoolsDestroy']);

    Route::get('/test-names',                [\App\Http\Controllers\LabelMasterController::class, 'testNamesIndex']);
    Route::post('/test-names',               [\App\Http\Controllers\LabelMasterController::class, 'testNamesStore']);
    Route::put('/test-names/{testName}',     [\App\Http\Controllers\LabelMasterController::class, 'testNamesUpdate']);
    Route::delete('/test-names/{testName}',  [\App\Http\Controllers\LabelMasterController::class, 'testNamesDestroy']);

    Route::get('/subjects',              [\App\Http\Controllers\LabelMasterController::class, 'subjectsIndex']);
    Route::post('/subjects',             [\App\Http\Controllers\LabelMasterController::class, 'subjectsStore']);
    Route::put('/subjects/{subject}',    [\App\Http\Controllers\LabelMasterController::class, 'subjectsUpdate']);
    Route::delete('/subjects/{subject}', [\App\Http\Controllers\LabelMasterController::class, 'subjectsDestroy']);

    Route::get('/item-types',               [\App\Http\Controllers\LabelMasterController::class, 'itemTypesIndex']);
    Route::post('/item-types',              [\App\Http\Controllers\LabelMasterController::class, 'itemTypesStore']);
    Route::put('/item-types/{itemType}',    [\App\Http\Controllers\LabelMasterController::class, 'itemTypesUpdate']);
    Route::delete('/item-types/{itemType}', [\App\Http\Controllers\LabelMasterController::class, 'itemTypesDestroy']);

    // 並べ替え（試験名 / アイテム / 一式宛先 共通）
    Route::post('/reorder', [\App\Http\Controllers\LabelMasterController::class, 'reorder']);

    // エリアマスタ
    Route::get('/area-masters',                [\App\Http\Controllers\LabelMasterController::class, 'areaMastersIndex']);
    Route::post('/area-masters',               [\App\Http\Controllers\LabelMasterController::class, 'areaMastersStore']);
    Route::put('/area-masters/{area}',         [\App\Http\Controllers\LabelMasterController::class, 'areaMastersUpdate']);
    Route::delete('/area-masters/{area}',      [\App\Http\Controllers\LabelMasterController::class, 'areaMastersDestroy']);

    // 一式宛先マスタ
    Route::get('/isshiki-destinations',                  [\App\Http\Controllers\LabelMasterController::class, 'isshikiIndex']);
    Route::post('/isshiki-destinations',                 [\App\Http\Controllers\LabelMasterController::class, 'isshikiStore']);
    Route::put('/isshiki-destinations/{isshiki}',        [\App\Http\Controllers\LabelMasterController::class, 'isshikiUpdate']);
    Route::delete('/isshiki-destinations/{isshiki}',     [\App\Http\Controllers\LabelMasterController::class, 'isshikiDestroy']);

    // 社内便ルートマスタ
    Route::get('/routes',                        [\App\Http\Controllers\LabelMasterController::class, 'routesIndex']);
    Route::post('/routes',                       [\App\Http\Controllers\LabelMasterController::class, 'routesStore']);
    Route::put('/routes/{route}',                [\App\Http\Controllers\LabelMasterController::class, 'routesUpdate']);
    Route::delete('/routes/{route}',             [\App\Http\Controllers\LabelMasterController::class, 'routesDestroy']);
    Route::post('/routes/{route}/stops',              [\App\Http\Controllers\LabelMasterController::class, 'stopsStore']);
    Route::post('/routes/{route}/stops/insert-at',   [\App\Http\Controllers\LabelMasterController::class, 'stopsInsertAt']);
    Route::put('/route-stops/{routeStop}',            [\App\Http\Controllers\LabelMasterController::class, 'stopsUpdate']);
    Route::delete('/route-stops/{routeStop}',         [\App\Http\Controllers\LabelMasterController::class, 'stopsDestroy']);
    Route::delete('/route-stops/{routeStop}/shift',   [\App\Http\Controllers\LabelMasterController::class, 'stopsDestroyShift']);
});

// ラベルアイテムPDF OCR 解析（全ログインユーザー）
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])
    ->post('/label-ocr/analyze', [\App\Http\Controllers\LabelOcrController::class, 'analyze'])
    ->name('label-ocr.analyze');

// SuperAdmin コンテキスト切り替え
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'superadmin'])
    ->post('/superadmin/switch-context', [App\Http\Controllers\SuperAdmin\ContextController::class, 'switch'])
    ->name('superadmin.switch_context');
