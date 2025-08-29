<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

// デバッグ用ルート
require __DIR__ . '/debug.php';
// チャット用ルート
require __DIR__ . '/chat.php';

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

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

    // ユーザー割り当てジョブ一覧・詳細
    Route::prefix('user/assigned-projects')->name('user.assigned-projects.')->group(function () {
        Route::get('/', [App\Http\Controllers\User\AssignedProjectController::class, 'index'])->name('index');
        Route::get('/{id}', [App\Http\Controllers\User\AssignedProjectController::class, 'show'])->name('show');
    });

    // チャット画面
    Route::get('/chat', [App\Http\Controllers\Chat\ChatController::class, 'index'])->name('chat.users.index');
    // AIチャット（Bot）ページ
    Route::get('/bot/chat', function () {
        return Inertia::render('Bot/ChatBot');
    })->name('bot.chat');
    // Bot API proxy to OpenAI
    Route::post('/bot/chat', [App\Http\Controllers\Bot\BotController::class, 'chat'])->name('bot.chat.api');
    // Bot file upload & stream
    Route::post('/bot/files', [App\Http\Controllers\Bot\BotFileController::class, 'upload'])->name('bot.files.upload');
    Route::get('/bot/attachments', [App\Http\Controllers\Bot\BotFileController::class, 'stream'])->name('bot.files.stream');

    // Bot export (conversation -> file)
    Route::post('/bot/export', [App\Http\Controllers\BotExportController::class, 'export'])->name('bot.export');
    Route::get('/bot/export/download/{filename}', [App\Http\Controllers\BotExportController::class, 'download'])->name('bot.export.download');

    // AI conversation history
    Route::get('/bot/history', [App\Http\Controllers\Bot\AiHistoryController::class, 'index'])->name('bot.history.index');
    Route::get('/bot/history/{id}', [App\Http\Controllers\Bot\AiHistoryController::class, 'show'])->name('bot.history.show');
    Route::get('/bot/history/{id}/json', [App\Http\Controllers\Bot\AiHistoryController::class, 'showJson'])->name('bot.history.show.json');
    Route::post('/bot/history', [App\Http\Controllers\Bot\AiHistoryController::class, 'store'])->name('bot.history.store');
    Route::put('/bot/history/{id}', [App\Http\Controllers\Bot\AiHistoryController::class, 'update'])->name('bot.history.update');

    // Ziggy用: 明示的にuser.dashboardルートを追加
    Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/user/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('user.dashboard');

    // チーム切り替え
    Route::put('/current-team', [App\Http\Controllers\CurrentTeamController::class, 'update'])->name('current-team.update');

    // 日報機能（作成、保存、表示、編集、更新、削除）
    Route::resource('diaries', App\Http\Controllers\DiaryController::class)
        ->only(['create', 'store', 'show', 'edit', 'update', 'destroy', 'index']);

    // イベント機能（作成、保存、表示、編集、更新）
    Route::resource('events', App\Http\Controllers\EventController::class)->only([
        'create',
        'store',
        'show',
        'edit',
        'update'
    ]);

    // Allow authenticated users (owners) to delete their project memos via a non-coordinator route
    Route::delete('project_memos/{memo}', [App\Http\Controllers\Coordinator\ProjectMemosController::class, 'destroy'])->name('project_memos.destroy');

    // チャットルーム作成
    Route::get('/chat/rooms', [App\Http\Controllers\Chat\ChatController::class, 'indexRooms'])->name('chat.rooms.index');
    Route::get('/chat/rooms/create', [App\Http\Controllers\Chat\ChatController::class, 'createRoom'])->name('chat.rooms.create');
    Route::post('/chat/rooms', [App\Http\Controllers\Chat\ChatController::class, 'storeRoom'])->name('chat.rooms.store');
    Route::get('/chat/rooms/{id}', [App\Http\Controllers\Chat\ChatController::class, 'showRoom'])->name('chat.rooms.show');
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

        // Shortcut route used by the ProjectJobs edit/create pages to open the
        // ProjectSchedules calendar for a specific project (passes project_job_id
        // as a query parameter). This keeps front-end route calls like
        // route('coordinator.project_jobs.schedule', { projectJob: id }) working
        // without changing existing calendar controller signatures.
        Route::get('project_jobs/{projectJob}/schedule', function ($projectJob) {
            return redirect()->route('coordinator.project_schedules.calendar', ['project_job_id' => $projectJob]);
        })->name('project_jobs.schedule');

        // PoC: ProjectSchedules (Gantt)
        Route::get('project_schedules', [App\Http\Controllers\Coordinator\ProjectSchedulesController::class, 'index'])->name('project_schedules.index');
        Route::get('project_schedules/create', [App\Http\Controllers\Coordinator\ProjectSchedulesController::class, 'create'])->name('project_schedules.create');
        Route::post('project_schedules', [App\Http\Controllers\Coordinator\ProjectSchedulesController::class, 'store'])->name('project_schedules.store');
        Route::patch('project_schedules/{project_schedule}', [App\Http\Controllers\Coordinator\ProjectSchedulesController::class, 'update'])->name('project_schedules.update');
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
    });



// デバッグ・テスト用ページのルート例
// 今後も任意のVueページをテスト表示したい場合は、下記のようにInertia::renderでページ名を指定してください。
// 例: /debug/create → resources/js/Pages/Diaries/CreateDebug.vue
// 例: /debug/other  → resources/js/Pages/OtherDebug.vue


Route::get('/debug/create', function () {
    return Inertia::render('Diaries/CreateDebug');
})->name('debug.create');

// --- デバッグ用API/認証チェックページ ---
// /debug/api でAPI/認証の動作確認ができるVueページ（resources/js/Debug/ApiDebug.vue）
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/debug/api', function () {
        return Inertia::render('Debug/ApiDebug');
    })->name('debug.api');
});
