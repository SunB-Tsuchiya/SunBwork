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
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});
// Temporary public debug route to send test completion mail (remove after testing)
Route::get('/debug/events/send-test-completion', [App\Http\Controllers\EventController::class, 'sendTestCompletion'])->name('debug.events.send_test_completion');

// User Dashboard (default authenticated users)
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {
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

    // Ziggy用: 明示的にuser.dashboardルートを追加
    Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/user/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('user.dashboard');

    // Shortcut route: global JobBox - show a user's job messages across assignments
    // When no specific project is provided the controller will return messages
    // relevant to the authenticated user.
    Route::get('/jobbox', [\App\Http\Controllers\ProjectJobs\JobBoxController::class, 'global'])->name('project_jobs.index');

    // MyJobBox: user-scoped JobBox page (personal messages/assignments)
    Route::get('/myjobbox', [\App\Http\Controllers\User\MyProjectJobController::class, 'index'])->name('user.myjobbox.index');
    Route::get('/myjobbox/{assignment}', [\App\Http\Controllers\User\MyProjectJobController::class, 'showAssignment'])->name('user.myjobbox.show');

    // チーム切り替え
    Route::put('/current-team', [App\Http\Controllers\CurrentTeamController::class, 'update'])->name('current-team.update');

    // 日報機能（作成、保存、表示、編集、更新、削除）
    Route::resource('diaries', App\Http\Controllers\DiaryController::class)
        ->only(['create', 'store', 'show', 'edit', 'update', 'destroy', 'index']);

    // Diary comment delete (authenticated users can delete their own comment)
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

    // Allow assigned users to view jobbox (read-only). These routes mirror coordinator jobbox but are under authenticated user middleware.
    Route::get('project_jobs/{projectJob}/jobbox', [\App\Http\Controllers\ProjectJobs\JobBoxController::class, 'index'])->name('project_jobs.jobbox.index');
    Route::get('project_jobs/{projectJob}/jobbox/{message}', [\App\Http\Controllers\ProjectJobs\JobBoxController::class, 'show'])->name('project_jobs.jobbox.show');
    // Allow assigned users to send a completion reply back to coordinators
    Route::post('project_jobs/{projectJob}/jobbox/reply', [\App\Http\Controllers\ProjectJobs\JobBoxController::class, 'reply'])->name('project_jobs.jobbox.reply');
    // Allow authenticated users to create a single assignment (their own) without coordinator side-effects
    Route::post('project_jobs/{projectJob}/assignments/user', [App\Http\Controllers\User\ProjectJobAssignmentController::class, 'store'])->name('project_jobs.assignments.store_user');
    // Allow authenticated users to update their assignment (edit)
    Route::patch('project_jobs/{projectJob}/assignments/{assignment}/user', [App\Http\Controllers\User\ProjectJobAssignmentController::class, 'update'])->name('project_jobs.assignments.update_user');
    // Standalone page for user assignment form — redirect to controller-backed job create
    Route::get('project_jobs/assignments/create-user', function (
        \Illuminate\Http\Request $request
    ) {
        // Redirect to EventController::createJob which provides userClients/userProjects/members and lookup lists
        return redirect()->route('events.create_job');
    })->name('project_jobs.assignments.create_user');
    Route::get('/chat/rooms/{id}', [App\Http\Controllers\Chat\ChatController::class, 'showRoom'])->name('chat.rooms.show');



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

        // CSV一括登録（リソースルートより前に配置）
        Route::get('users/csv/upload', [App\Http\Controllers\Admin\UserController::class, 'csvUpload'])->name('users.csv.upload');
        Route::post('users/csv/preview', [App\Http\Controllers\Admin\UserController::class, 'csvPreview'])->name('users.csv.preview');

        Route::post('users/csv/store', [App\Http\Controllers\Admin\UserController::class, 'csvStore'])->name('users.csv.store');

        // ユーザー管理
        Route::resource('users', App\Http\Controllers\Admin\UserController::class);

        // クライアント管理（Admin用）: 管理者はクライアントの CRUD を扱える（作成/編集等）
        Route::resource('clients', App\Http\Controllers\ClientController::class)->only(['index', 'create', 'store', 'edit', 'update']);

        // 会社管理 (会社作成/管理は SuperAdmin 側に一本化しました)
        // 会社管理: 管理者は自社の閲覧・編集のみ許可 (作成/削除はできない)
        Route::resource('companies', App\Http\Controllers\Admin\CompanyController::class)
            ->only(['index', 'show', 'edit', 'update']);

        // チーム管理
        Route::resource('teams', App\Http\Controllers\Admin\TeamController::class);
        // ユニットチーム作成 (画面と保存)
        Route::get('teams/units/create', [App\Http\Controllers\Admin\UnitController::class, 'create'])->name('teams.units.create');
        Route::post('units', [App\Http\Controllers\Admin\UnitController::class, 'store'])->name('units.store');
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
        // Admin: Workload Analyzer (company-wide)
        Route::get('workload-analyzer', [App\Http\Controllers\Leader\WorkloadAnalyzerController::class, 'index'])->name('workload_analyzer.index');
        // Register settings routes before the parameterized {user} route so 'settings' is not captured as {user}
        Route::get('workload-analyzer/settings', [App\Http\Controllers\Leader\WorkloadAnalyzerController::class, 'settings'])->name('workload_analyzer.settings');
        Route::post('workload-analyzer/settings', [App\Http\Controllers\Leader\WorkloadAnalyzerController::class, 'saveSettings'])->name('workload_analyzer.settings.save');
        Route::get('workload-analyzer/{user}', [App\Http\Controllers\Leader\WorkloadAnalyzerController::class, 'show'])->name('workload_analyzer.show');
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

        // 会社管理
        Route::resource('companies', App\Http\Controllers\SuperAdmin\CompanyController::class);

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
    });



// Ziggy用: 明示的にleader.dashboardルートを追加
// Leader Routes (AdminとLeaderのみアクセス可能)
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'leader'])
    ->prefix('leader')
    ->name('leader.')
    ->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
        // クライアント管理（Leader用）
        Route::resource('clients', App\Http\Controllers\ClientController::class)->only(['index', 'create', 'store', 'edit', 'update']);
        // Leader diary interactions (leader can view diaries for departments/units they lead)
        Route::get('diaryinteractions', [App\Http\Controllers\Diaries\DiaryInteractionController::class, 'index'])->name('diaryinteractions.index');
        Route::get('diaryinteractions/{diary}', [App\Http\Controllers\Diaries\DiaryInteractionController::class, 'show'])->name('diaryinteractions.show');
        Route::post('diaryinteractions/{diary}/mark-read', [App\Http\Controllers\Diaries\DiaryInteractionController::class, 'markRead'])->name('diaryinteractions.mark_read');
        // 日付単位で「全部既読にする」(リーダー用)
        Route::post('diaryinteractions/mark-read-all', [App\Http\Controllers\Diaries\DiaryInteractionController::class, 'markReadAll'])->name('diaryinteractions.mark_read_all');
        // Leader: Workload Analyzer (show company/department/team members and analysis placeholders)
        Route::get('workload-analyzer', [App\Http\Controllers\Leader\WorkloadAnalyzerController::class, 'index'])->name('workload_analyzer.index');
        // ensure static 'settings' route is registered before the parameterized {user} route
        Route::get('workload-analyzer/settings', [App\Http\Controllers\Leader\WorkloadAnalyzerController::class, 'settings'])->name('workload_analyzer.settings');
        Route::post('workload-analyzer/settings', [App\Http\Controllers\Leader\WorkloadAnalyzerController::class, 'saveSettings'])->name('workload_analyzer.settings.save');
        Route::get('workload-analyzer/{user}', [App\Http\Controllers\Leader\WorkloadAnalyzerController::class, 'show'])->name('workload_analyzer.show');
    });

// クライアント管理（Admin用）は上の admin グループに統合済み（重複削除）
// Coordinator Routes (AdminとCoordinatorのみアクセス可能)
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified', 'coordinator'])
    ->prefix('coordinator')
    ->name('coordinator.')
    ->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

        // Project_job CRUD
        Route::get('project_jobs', [App\Http\Controllers\Coordinator\ProjectJobController::class, 'index'])->name('project_jobs.index');
        Route::get('project_jobs/create', [App\Http\Controllers\Coordinator\ProjectJobController::class, 'create'])->name('project_jobs.create');
        Route::post('project_jobs', [App\Http\Controllers\Coordinator\ProjectJobController::class, 'store'])->name('project_jobs.store');
        Route::get('project_jobs/{projectJob}', [App\Http\Controllers\Coordinator\ProjectJobController::class, 'show'])->name('project_jobs.show');
        Route::get('project_jobs/{projectJob}/edit', [App\Http\Controllers\Coordinator\ProjectJobController::class, 'edit'])->name('project_jobs.edit');
        Route::put('project_jobs/{projectJob}', [App\Http\Controllers\Coordinator\ProjectJobController::class, 'update'])->name('project_jobs.update');
        Route::delete('project_jobs/{projectJob}', [App\Http\Controllers\Coordinator\ProjectJobController::class, 'destroy'])->name('project_jobs.destroy');
        // Project job assignment (JobAssign)
        Route::get('project_jobs/{projectJob}/assignments', [App\Http\Controllers\Coordinator\ProjectJobAssignmentsController::class, 'index'])->name('project_jobs.assignments.index');
        Route::get('project_jobs/{projectJob}/assignments/create', [App\Http\Controllers\Coordinator\ProjectJobAssignmentsController::class, 'create'])->name('project_jobs.assignments.create');
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
        Route::post('project_schedules/bulk_update', [App\Http\Controllers\Coordinator\ProjectSchedulesController::class, 'bulkUpdate'])->name('project_schedules.bulk_update');

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
        Route::post('project_jobs/{projectJob}/jobbox', [\App\Http\Controllers\ProjectJobs\JobBoxController::class, 'store'])->name('project_jobs.jobbox.store');
        Route::delete('project_jobs/{projectJob}/jobbox/{message}', [\App\Http\Controllers\ProjectJobs\JobBoxController::class, 'destroy'])->name('project_jobs.jobbox.destroy');
        // Project job analysis (ジョブ分析)
        Route::get('project_jobs/{projectJob}/analysis', [App\Http\Controllers\Coordinator\ProjectJobController::class, 'analysis'])->name('project_jobs.analysis');
    });



// デバッグ・テスト用ページのルート例
// 今後も任意のVueページをテスト表示したい場合は、下記のようにInertia::renderでページ名を指定してください。
// 例: /debug/create → resources/js/Pages/Diaries/CreateDebug.vue
// 例: /debug/other  → resources/js/Pages/OtherDebug.vue


Route::get('/debug/create', function () {
    return Inertia::render('Diaries/CreateDebug');
})->name('debug.create');

// (temporary debug routes removed)

// --- デバッグ用API/認証チェックページ ---
// /debug/api でAPI/認証の動作確認ができるVueページ（resources/js/Debug/ApiDebug.vue）
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/debug/api', function () {
        return Inertia::render('Debug/ApiDebug');
    })->name('debug.api');
});
