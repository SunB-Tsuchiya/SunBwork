# ジョブ通知機能 実装仕様書

作成日: 2026-04-07
ステータス: 未実装（設計完了）

---

## 概要

Coordinator がユーザーにジョブを割り当てたとき、ユーザーがジョブを完了したとき、
またはユーザーが進行管理表からジョブを操作したときに、
専用の通知ページ（`/job-notifications`）に通知を表示する機能。

---

## 確定した仕様

### 通知トリガーと受信者

| トリガー | 受信者 | メッセージ |
|---------|--------|-----------|
| ① Coordinator がジョブ依頼 | 依頼相手（assigned user） | 「〇〇さんから「案件名」の新しいジョブが依頼されました」 |
| ① Coordinator がジョブ依頼 | 案件リーダー・副リーダー（自分以外） | 「〇〇さんが△△さんに「案件名」のジョブを依頼しました」 |
| ② ユーザーがジョブ完了（Coordinator依頼分） | 依頼主（sender） | 「〇〇さんが「ジョブ名」を完了しました」 |
| ② ユーザーがジョブ完了（Coordinator依頼分） | 案件リーダー・副リーダー（自分以外） | 「〇〇さんが「ジョブ名」を完了しました」 |
| ③ ユーザーが進行管理表からジョブ登録 | 案件リーダー・副リーダー（自分以外） | 「〇〇さんが「案件名」の進行管理表でジョブを登録しました」 |
| ④ ユーザーが進行管理表からジョブ完了 | 案件リーダー・副リーダー（自分以外） | 「〇〇さんが「案件名」の進行管理表のジョブを完了しました」 |

### 通知しないケース

- **マイジョブ（自己割当）の予定セット** → 通知なし
- **マイジョブ（自己割当）の完了** → 通知なし（進行管理表に紐づいている場合を除く）
- **自分自身への通知** → 常に除外

### 「案件リーダー・副リーダー」の定義

- `project_jobs.user_id`（案件オーナー/代表Coordinator）
- `project_job_coordinators` ピボットに登録されたユーザー（サブCo）

### その他

| 項目 | 内容 |
|------|------|
| ナビ表示 | AppLayout のメールアイコンのリンクを `/job-notifications` に変更 |
| バッジ | 未読件数を青丸で表示 |
| クリック動作 | 既読マーク → `project_jobs.show` へ直接リダイレクト（中間ページなし） |
| 既存システム | `job_assignment_messages`（JobBox）は並行稼働・廃止しない |

---

## 通知種別（type カラムの値）

| type | 説明 |
|------|------|
| `new_job` | 新規ジョブ依頼（依頼相手向け） |
| `new_job_info` | 新規ジョブ依頼（リーダー向け情報通知） |
| `completed` | ジョブ完了（依頼主向け） |
| `completed_info` | ジョブ完了（リーダー向け情報通知） |
| `progress_registered` | 進行管理表からのジョブ登録（リーダー向け） |
| `progress_completed` | 進行管理表からのジョブ完了（リーダー向け） |

---

## Step 1: マイグレーション作成

**ファイル名:** `database/migrations/2026_04_07_200001_create_job_notifications_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // new_job | new_job_info | completed | completed_info | progress_registered | progress_completed
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_job_id')->constrained('project_jobs')->cascadeOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('project_job_assignments')->nullOnDelete();
            $table->string('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_notifications');
    }
};
```

**CI（SQLite）対応:** MySQL固有構文なし。SQLite ガード不要。

---

## Step 2: モデル作成

**ファイル:** `app/Models/JobNotification.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobNotification extends Model
{
    protected $fillable = [
        'type',
        'sender_id',
        'recipient_id',
        'project_job_id',
        'assignment_id',
        'message',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function projectJob()
    {
        return $this->belongsTo(ProjectJob::class);
    }

    public function assignment()
    {
        return $this->belongsTo(ProjectJobAssignment::class);
    }
}
```

---

## Step 3: 通知ヘルパーサービス作成

**ファイル:** `app/Services/JobNotificationService.php`

複数コントローラーから呼ばれるため、共通ロジックをサービスクラスにまとめる。

```php
<?php

namespace App\Services;

use App\Models\JobNotification;
use App\Models\ProjectJob;
use App\Models\ProjectJobAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class JobNotificationService
{
    /**
     * 案件のリーダー・副リーダーのIDリストを返す（自分自身は除く）
     */
    public static function getLeaderIds(ProjectJob $projectJob, int $excludeUserId): array
    {
        $ids = [];
        if ($projectJob->user_id) {
            $ids[] = $projectJob->user_id;
        }
        $subCoIds = $projectJob->coordinators()->pluck('users.id')->toArray();
        $ids = array_unique(array_merge($ids, $subCoIds));
        return array_values(array_filter($ids, fn($id) => $id !== $excludeUserId));
    }

    /**
     * ① 新規ジョブ依頼通知
     * - 依頼相手: "〇〇さんから「案件名」の新しいジョブが依頼されました"
     * - リーダー・副リーダー: "〇〇さんが△△さんに「案件名」のジョブを依頼しました"
     */
    public static function notifyNewJob(
        User $sender,
        int $recipientId,
        ProjectJob $projectJob,
        ProjectJobAssignment $assignment
    ): void {
        try {
            $jobTitle = $projectJob->title ?? '案件';
            $recipientUser = User::find($recipientId);
            $recipientName = $recipientUser?->name ?? 'ユーザー';

            // 依頼相手への通知
            JobNotification::create([
                'type'           => 'new_job',
                'sender_id'      => $sender->id,
                'recipient_id'   => $recipientId,
                'project_job_id' => $projectJob->id,
                'assignment_id'  => $assignment->id,
                'message'        => "{$sender->name}さんから「{$jobTitle}」の新しいジョブが依頼されました",
            ]);

            // リーダー・副リーダーへの情報通知（自分と依頼相手を除く）
            $leaderIds = self::getLeaderIds($projectJob, $sender->id);
            $leaderIds = array_values(array_filter($leaderIds, fn($id) => $id !== $recipientId));
            foreach ($leaderIds as $leaderId) {
                JobNotification::create([
                    'type'           => 'new_job_info',
                    'sender_id'      => $sender->id,
                    'recipient_id'   => $leaderId,
                    'project_job_id' => $projectJob->id,
                    'assignment_id'  => $assignment->id,
                    'message'        => "{$sender->name}さんが{$recipientName}さんに「{$jobTitle}」のジョブを依頼しました",
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('JobNotification create failed (new_job)', ['error' => $e->getMessage()]);
        }
    }

    /**
     * ② ジョブ完了通知（Coordinator依頼分のみ）
     * - 依頼主: "〇〇さんが「ジョブ名」を完了しました"
     * - リーダー・副リーダー: "〇〇さんが「ジョブ名」を完了しました"
     *
     * 自己割当（sender_id === user_id）の場合は呼ばない。
     */
    public static function notifyCompleted(
        User $user,
        ProjectJobAssignment $assignment,
        ProjectJob $projectJob
    ): void {
        // sender_id が null または自分自身の場合は通知しない（自己割当）
        if (!$assignment->sender_id || $assignment->sender_id === $user->id) {
            return;
        }

        try {
            $assignmentTitle = $assignment->title ?? 'ジョブ';
            $message = "{$user->name}さんが「{$assignmentTitle}」を完了しました";

            // 依頼主への通知
            JobNotification::create([
                'type'           => 'completed',
                'sender_id'      => $user->id,
                'recipient_id'   => $assignment->sender_id,
                'project_job_id' => $projectJob->id,
                'assignment_id'  => $assignment->id,
                'message'        => $message,
            ]);

            // リーダー・副リーダーへの情報通知（自分と依頼主を除く）
            $leaderIds = self::getLeaderIds($projectJob, $user->id);
            $leaderIds = array_values(array_filter($leaderIds, fn($id) => $id !== $assignment->sender_id));
            foreach ($leaderIds as $leaderId) {
                JobNotification::create([
                    'type'           => 'completed_info',
                    'sender_id'      => $user->id,
                    'recipient_id'   => $leaderId,
                    'project_job_id' => $projectJob->id,
                    'assignment_id'  => $assignment->id,
                    'message'        => $message,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('JobNotification create failed (completed)', ['error' => $e->getMessage()]);
        }
    }

    /**
     * ③ 進行管理表からのジョブ登録通知
     * リーダー・副リーダー（自分以外）へ: "〇〇さんが「案件名」の進行管理表でジョブを登録しました"
     */
    public static function notifyProgressRegistered(
        User $user,
        ProjectJob $projectJob,
        ProjectJobAssignment $assignment
    ): void {
        try {
            $jobTitle = $projectJob->title ?? '案件';
            $message = "{$user->name}さんが「{$jobTitle}」の進行管理表でジョブを登録しました";

            $leaderIds = self::getLeaderIds($projectJob, $user->id);
            foreach ($leaderIds as $leaderId) {
                JobNotification::create([
                    'type'           => 'progress_registered',
                    'sender_id'      => $user->id,
                    'recipient_id'   => $leaderId,
                    'project_job_id' => $projectJob->id,
                    'assignment_id'  => $assignment->id,
                    'message'        => $message,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('JobNotification create failed (progress_registered)', ['error' => $e->getMessage()]);
        }
    }

    /**
     * ④ 進行管理表からのジョブ完了通知
     * リーダー・副リーダー（自分以外）へ: "〇〇さんが「案件名」の進行管理表のジョブを完了しました"
     */
    public static function notifyProgressCompleted(
        User $user,
        ProjectJob $projectJob,
        ProjectJobAssignment $assignment
    ): void {
        try {
            $jobTitle = $projectJob->title ?? '案件';
            $message = "{$user->name}さんが「{$jobTitle}」の進行管理表のジョブを完了しました";

            $leaderIds = self::getLeaderIds($projectJob, $user->id);
            foreach ($leaderIds as $leaderId) {
                JobNotification::create([
                    'type'           => 'progress_completed',
                    'sender_id'      => $user->id,
                    'recipient_id'   => $leaderId,
                    'project_job_id' => $projectJob->id,
                    'assignment_id'  => $assignment->id,
                    'message'        => $message,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('JobNotification create failed (progress_completed)', ['error' => $e->getMessage()]);
        }
    }
}
```

---

## Step 4: コントローラー作成

**ファイル:** `app/Http/Controllers/JobNotificationController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\JobNotification;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Carbon;

class JobNotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // フィルタパラメータ
        $group = $request->query('group', 'day');   // 'day' or 'month'
        $days  = (int) $request->query('days', 30); // 7 / 30 / 90
        if (!in_array($days, [7, 30, 90])) $days = 30;

        $since = Carbon::now()->subDays($days)->startOfDay();

        // 件数が多くなりにくいため、期間内は全件取得（ページネーションなし）
        $notifications = JobNotification::where('recipient_id', $user->id)
            ->where('created_at', '>=', $since)
            ->with(['sender', 'projectJob'])
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('JobNotifications/Index', [
            'notifications' => $notifications,
            'filters' => [
                'group' => $group,
                'days'  => $days,
            ],
        ]);
    }

    public function show(Request $request, JobNotification $jobNotification)
    {
        $user = $request->user();

        if ($jobNotification->recipient_id !== $user->id) {
            abort(403);
        }

        if (is_null($jobNotification->read_at)) {
            $jobNotification->update(['read_at' => now()]);
        }

        // ロールに応じて project_jobs.show のルートを決定
        $routeName = match(true) {
            $user->isAdmin() || $user->isSuperAdmin() => 'coordinator.project_jobs.show',
            $user->isCoordinator() || $user->isClerk() => 'coordinator.project_jobs.show',
            $user->isLeader() => 'leader.project_jobs.show',
            default => 'user.project_jobs.show',
        };

        try {
            return redirect()->route($routeName, ['projectJob' => $jobNotification->project_job_id]);
        } catch (\Throwable $e) {
            return redirect()->route('coordinator.project_jobs.show', ['projectJob' => $jobNotification->project_job_id]);
        }
    }
}
```

---

## Step 5: ルート追加

**ファイル:** `routes/web.php`

既存の announcements ルート（行 472–477 付近）の下に追加:

```php
// ジョブ通知（全認証ユーザー）
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])
    ->prefix('job-notifications')
    ->name('job-notifications.')
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\JobNotificationController::class, 'index'])->name('index');
        Route::get('/{jobNotification}', [\App\Http\Controllers\JobNotificationController::class, 'show'])->name('show');
    });
```

Ziggy 再生成:
```bash
docker compose exec laravel bash -lc "php artisan ziggy:generate resources/js/ziggy.js"
```

---

## Step 6: 通知作成ロジックをコントローラーに追加

### 6-A: 新規ジョブ依頼時

**ファイル:** `app/Http/Controllers/Coordinator/ProjectJobAssignmentsController.php`

`store()` メソッドの `$assignment = ProjectJobAssignment::create($createData);`（行 665 付近）の直後に追加:

```php
// ジョブ通知（受信者と案件リーダーへ）
if (!empty($createData['user_id'])) {
    $senderUser = auth()->user();
    if ($senderUser) {
        \App\Services\JobNotificationService::notifyNewJob(
            $senderUser,
            $createData['user_id'],
            $projectJob,
            $assignment
        );
    }
}
```

### 6-B: ジョブ完了時（MyProjectJobController）

**ファイル:** `app/Http/Controllers/User/MyProjectJobController.php`

`completeAssignment()` メソッドの `$assignment->save();`（行 137 付近）の直後に追加:

```php
// ジョブ通知（Coordinator依頼分のみ、進行管理表リンクあり/なし共通）
$projectJob = $assignment->projectJob;
if ($projectJob) {
    // 進行管理表に紐づいているか確認
    $hasProgressLink = \App\Models\ProgressCell::where('assignment_id', $assignment->id)->exists();

    if ($hasProgressLink) {
        // 進行管理表経由の完了 → リーダー・副リーダーに通知
        \App\Services\JobNotificationService::notifyProgressCompleted($user, $projectJob, $assignment);
    } else {
        // 通常の完了（Coordinator依頼分のみ）
        \App\Services\JobNotificationService::notifyCompleted($user, $assignment, $projectJob);
    }
}
```

### 6-C: ジョブ完了時（JobBoxController）

**ファイル:** `app/Http/Controllers/ProjectJobs/JobBoxController.php`

`completeAssignment()` メソッドの `$assignment->save()` 相当処理の後に追加（6-B と同じロジック）:

```php
$projectJob = $assignment->projectJob;
if ($projectJob) {
    $hasProgressLink = \App\Models\ProgressCell::where('assignment_id', $assignment->id)->exists();
    if ($hasProgressLink) {
        \App\Services\JobNotificationService::notifyProgressCompleted($user, $projectJob, $assignment);
    } else {
        \App\Services\JobNotificationService::notifyCompleted($user, $assignment, $projectJob);
    }
}
```

### 6-D: 進行管理表からのジョブ登録時

**ファイル:** `app/Http/Controllers/User/ProjectJobController.php`

`linkProgressCell()` メソッドの assignment 作成・ProgressCell 紐付け完了後に追加:

```php
// 進行管理表からのジョブ登録通知
\App\Services\JobNotificationService::notifyProgressRegistered($user, $projectJob, $assignment);
```

> `linkProgressCell()` の場所は CLAUDE.md 参照。ルート: `user.project_jobs.progress_sheets.link_job`

---

## Step 7: HandleInertiaRequests に未読数を追加

**ファイル:** `app/Http/Middleware/HandleInertiaRequests.php`

use 文に追加:
```php
use App\Models\JobNotification;
```

`$unreadAnnouncements` ブロックの下に追加:
```php
$unreadJobNotifications = 0;
if ($request->user()) {
    $unreadJobNotifications = JobNotification::where('recipient_id', $request->user()->id)
        ->whereNull('read_at')
        ->count();
}
```

return 配列に追加:
```php
'unreadJobNotifications' => $unreadJobNotifications,
```

---

## Step 8: AppLayout.vue の更新

**ファイル:** `resources/js/layouts/AppLayout.vue`

### 8-A: reactive ref 追加（行 74–78 の unreadAnnouncements watch の下）

```js
const unreadJobNotifications = vueRef(page.props.unreadJobNotifications || 0);
watch(
    () => page.props.unreadJobNotifications,
    (v) => { unreadJobNotifications.value = v || 0; },
);
```

### 8-B: メールアイコン部分を置換（行 312–324 付近）

```html
<!-- ジョブ通知 -->
<div class="group relative">
    <Link :href="route('job-notifications.index')" class="relative flex h-8 w-8 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-gray-700">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
        </svg>
        <span v-if="unreadJobNotifications > 0" class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-blue-500 text-[10px] text-white">{{ unreadJobNotifications }}</span>
    </Link>
    <div class="pointer-events-none absolute right-0 top-9 z-50 w-36 rounded-md bg-gray-800 px-3 py-2 text-xs text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100">
        <p class="font-medium">ジョブ通知</p>
        <p class="text-gray-300">依頼・完了通知</p>
    </div>
</div>
```

---

## Step 9: Vue ページ作成

**ファイル:** `resources/js/Pages/JobNotifications/Index.vue`

DiaryInteractions/Index.vue と同じパターンで「日別/月別」切り替えと「期間」選択を実装する。
未読・既読はアイコン（封筒：未読=青塗り / 既読=グレー開封）と背景色で区別する。

```vue
<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { ref, computed } from 'vue';

const props = defineProps({
    notifications: Array,
    filters: Object,
});

// フィルター state（サーバーから渡された値で初期化）
const viewMode     = ref(props.filters?.group === 'month' ? 'month' : 'day');
const selectedDays = ref(props.filters?.days ?? 30);

function applyFilters() {
    router.get(route('job-notifications.index'), {
        group: viewMode.value,
        days:  selectedDays.value,
    }, { preserveState: false });
}

// 日別 or 月別にグルーピング
const groupedNotifications = computed(() => {
    const map = {};
    (props.notifications || []).forEach((n) => {
        const raw = n.created_at ? String(n.created_at).substring(0, 10) : '不明';
        const key = viewMode.value === 'month' ? raw.substring(0, 7) : raw;
        if (!map[key]) map[key] = [];
        map[key].push(n);
    });
    // 降順ソート
    return Object.fromEntries(
        Object.entries(map).sort(([a], [b]) => (a < b ? 1 : a > b ? -1 : 0))
    );
});

function formatGroupKey(key) {
    if (!key || key === '不明') return '不明';
    if (viewMode.value === 'month') {
        const [y, m] = key.split('-');
        return `${y}年${m}月`;
    }
    const [y, m, d] = key.split('-');
    return `${y}/${m}/${d}`;
}

function formatTime(dateStr) {
    if (!dateStr) return '';
    return String(dateStr).replace('T', ' ').substring(11, 16);
}

const TYPE_LABELS = {
    new_job:             { label: '新規依頼',   cls: 'bg-blue-100 text-blue-800' },
    new_job_info:        { label: '依頼情報',   cls: 'bg-indigo-100 text-indigo-800' },
    completed:           { label: '完了報告',   cls: 'bg-yellow-100 text-yellow-800' },
    completed_info:      { label: '完了情報',   cls: 'bg-amber-100 text-amber-800' },
    progress_registered: { label: '進行表登録', cls: 'bg-green-100 text-green-800' },
    progress_completed:  { label: '進行表完了', cls: 'bg-teal-100 text-teal-800' },
};

function typeMeta(type) {
    return TYPE_LABELS[type] ?? { label: type, cls: 'bg-gray-100 text-gray-700' };
}
</script>

<template>
    <AppLayout title="ジョブ通知">
        <div class="rounded bg-white shadow">

            <!-- ヘッダー + フィルター -->
            <div class="border-b px-6 py-4">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-lg font-semibold text-gray-800">ジョブ通知</h2>
                    <div class="ml-auto flex flex-wrap items-center gap-2">
                        <label class="text-sm text-gray-600">表示:</label>
                        <select v-model="viewMode" class="rounded border px-2 py-1 text-sm">
                            <option value="day">日別表示</option>
                            <option value="month">月別表示</option>
                        </select>

                        <label class="text-sm text-gray-600">期間:</label>
                        <select v-model.number="selectedDays" class="rounded border px-2 py-1 text-sm">
                            <option :value="7">7日分</option>
                            <option :value="30">30日分</option>
                            <option :value="90">90日分</option>
                        </select>

                        <button @click="applyFilters" class="rounded bg-blue-600 px-3 py-1 text-xs text-white hover:bg-blue-700">
                            適用
                        </button>
                    </div>
                </div>
            </div>

            <!-- 通知リスト（グループ別） -->
            <div v-if="notifications && notifications.length > 0">
                <div v-for="(list, groupKey) in groupedNotifications" :key="groupKey" class="border-b last:border-b-0">
                    <!-- グループヘッダー -->
                    <div class="bg-gray-50 px-6 py-2">
                        <h3 class="text-sm font-semibold text-gray-600">{{ formatGroupKey(groupKey) }}</h3>
                    </div>

                    <!-- 通知行 -->
                    <ul class="divide-y divide-gray-100">
                        <li v-for="n in list" :key="n.id">
                            <Link
                                :href="route('job-notifications.show', { jobNotification: n.id })"
                                class="flex items-start gap-3 px-6 py-3 hover:bg-gray-50"
                                :class="n.read_at ? 'bg-white' : 'bg-blue-50'"
                            >
                                <!-- 未読/既読アイコン -->
                                <div class="mt-0.5 flex-shrink-0">
                                    <!-- 未読: 青い封筒（塗りつぶし） -->
                                    <svg v-if="!n.read_at" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5 text-blue-500">
                                        <path d="M1.5 8.67v8.58a3 3 0 003 3h15a3 3 0 003-3V8.67l-8.928 5.493a3 3 0 01-3.144 0L1.5 8.67z" />
                                        <path d="M22.5 6.908V6.75a3 3 0 00-3-3h-15a3 3 0 00-3 3v.158l9.714 5.978a1.5 1.5 0 001.572 0L22.5 6.908z" />
                                    </svg>
                                    <!-- 既読: グレーの開封封筒 -->
                                    <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 text-gray-300">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 9v.906a2.25 2.25 0 01-1.183 1.981l-6.478 3.488M2.25 9v.906a2.25 2.25 0 001.183 1.981l6.478 3.488m8.839 2.51l-4.66-2.51m0 0l-1.023-.55a2.25 2.25 0 00-2.134 0l-1.022.55m0 0l-4.661 2.51m16.5 1.615a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V8.844a2.25 2.25 0 011.183-1.981l7.5-4.039a2.25 2.25 0 012.134 0l7.5 4.039a2.25 2.25 0 011.183 1.98V19.5z" />
                                    </svg>
                                </div>

                                <!-- 本文 -->
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" :class="typeMeta(n.type).cls">
                                            {{ typeMeta(n.type).label }}
                                        </span>
                                        <span class="text-xs text-gray-400">{{ formatTime(n.created_at) }}</span>
                                        <span v-if="!n.read_at" class="text-xs font-semibold text-blue-600">● 未読</span>
                                    </div>
                                    <p class="mt-1 text-sm" :class="n.read_at ? 'text-gray-600' : 'font-medium text-gray-900'">
                                        {{ n.message }}
                                    </p>
                                    <p v-if="n.project_job?.title" class="mt-0.5 text-xs text-gray-400">
                                        案件: {{ n.project_job.title }}
                                    </p>
                                </div>
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>

            <div v-else class="px-6 py-12 text-center text-sm text-gray-500">
                この期間に通知はありません
            </div>
        </div>
    </AppLayout>
</template>
```

### 未読/既読の視覚的区別まとめ

| 要素 | 未読 | 既読 |
|------|------|------|
| 行背景 | `bg-blue-50`（薄い青） | `bg-white` |
| アイコン | 青い封筒（filled） | グレーの開封封筒（outline） |
| テキスト | `font-medium text-gray-900`（太字） | `text-gray-600`（通常） |
| ラベル | `● 未読` バッジ（青） | なし |

---

## Step 10: ビルドとデプロイ

### ローカル確認
```bash
docker compose exec laravel bash -lc "php artisan migrate"
docker compose exec laravel bash -lc "php artisan ziggy:generate resources/js/ziggy.js"
npm run build
```

### さくらデプロイ（CLAUDE.md の手順に従う）
```bash
# さくら用ビルド
sed -i 's/^VITE_APP_BASE_PATH=$/VITE_APP_BASE_PATH=\/members/' /home/w229/SunBwork/.env
npm run build

# コミット対象ファイル
git add \
    app/Http/Controllers/JobNotificationController.php \
    app/Models/JobNotification.php \
    app/Services/JobNotificationService.php \
    app/Http/Middleware/HandleInertiaRequests.php \
    app/Http/Controllers/Coordinator/ProjectJobAssignmentsController.php \
    app/Http/Controllers/User/MyProjectJobController.php \
    app/Http/Controllers/ProjectJobs/JobBoxController.php \
    app/Http/Controllers/User/ProjectJobController.php \
    database/migrations/2026_04_07_200001_create_job_notifications_table.php \
    resources/js/layouts/AppLayout.vue \
    resources/js/Pages/JobNotifications/Index.vue \
    resources/js/ziggy.js \
    public/build/
git commit -m "feat: ジョブ通知機能を追加（新規依頼・完了・進行管理表）"

# ローカルビルドに戻す（必須）
sed -i 's/^VITE_APP_BASE_PATH=\/members$/VITE_APP_BASE_PATH=/' /home/w229/SunBwork/.env
npm run build
```

---

## 実装順序（推奨）

1. Step 1: マイグレーション作成・実行
2. Step 2: モデル作成
3. Step 3: JobNotificationService 作成
4. Step 4: コントローラー作成
5. Step 5: ルート追加 → Ziggy 再生成
6. Step 7: HandleInertiaRequests 更新
7. Step 6: 通知ロジック追加（6-A〜6-D）
8. Step 8: AppLayout.vue 更新
9. Step 9: Vue ページ作成
10. Step 10: ビルド・確認・デプロイ

---

## 重要な注意事項

- **`getLeaderIds()` の依存:** `ProjectJob` モデルに `coordinators()` リレーション（`belongsToMany(User, 'project_job_coordinators')`）が必要。既に実装済み。
- **`ProgressCell` モデル:** `app/Models/ProgressCell.php` が存在し `assignment_id` カラムを持つ。既に実装済み。
- **Ziggy ルート名:** `job-notifications.index` / `job-notifications.show`（ハイフン区切り）
- **さくらでは `route()` を必ず使う**（ハードコードパスは `/members` ベースパスで壊れる）
- **通知作成は try/catch で囲む**（JobNotificationService 内で処理済み）
- **自己割当の完了は通知しない:** `notifyCompleted()` 内で `sender_id === user_id` の場合は早期 return 済み
- **進行管理表経由の判定:** `ProgressCell::where('assignment_id', $assignment->id)->exists()` で判定
