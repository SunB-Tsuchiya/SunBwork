# 校正ジョブ ユーザー側システム — 実装指示書

## 概要

校正管理（ProofCoordinator）側はほぼ完成済み。
この指示書は **ユーザー側の校正ジョブ機能** を実装するためのものです。

---

## 前提知識（必ず読むこと）

### 用語定義

| 用語 | 意味 |
|------|------|
| pja100 | Coordinator が作成した「coordinator 割当」。`sender_id ≠ user_id` |
| pja101 | ユーザーが自己登録した「自己割当」。`sender_id = user_id`, `source_assignment_id = pja100.id` |
| ProofSchedule | Coordinator のカレンダー用スケジュール（`proof_schedules` テーブル） |
| Event | ユーザーのカレンダー予定（`events` テーブル, `starts_at`/`ends_at` が UTC で保存） |
| ProofRequest | 校正依頼（`proof_requests` テーブル） |
| ProofTeamMember | 校正チームメンバー（`proof_team_members` テーブル, `user_id` カラム） |

### 重要な関係性

```
ProofRequest
  ├─ proofreader_id → User（校正員）
  ├─ proof_coordinator_id → User（校正管理者）
  └─ project_job_id → ProjectJob

pja100 (ProjectJobAssignment, coordinator割当)
  ├─ user_id = proofreader_id
  ├─ sender_id = proof_coordinator_id
  └─ project_job_id = ProofRequest.project_job_id

pja101 (ProjectJobAssignment, 自己割当)
  ├─ user_id = sender_id = proofreader_id（自己割当マーカー）
  └─ source_assignment_id = pja100.id

Event
  └─ project_job_assignment_id = pja101.id（pja101 に紐付く）
```

### 既存の関連ファイル（参照のこと）

```
app/Http/Controllers/ProofCoordinator/ProofRequestController.php
  - inbox(), assignPage(), assignStore(), show(), edit(), assignmentUpdate()

app/Http/Controllers/ProofCoordinator/CalendarController.php
  - getSchedulesForDate(), formatSchedule(), toUtcIso()

app/Models/ProofRequest.php
app/Models/ProofSchedule.php
app/Models/ProofTeamMember.php

resources/js/Pages/ProofCoordinator/Inbox/Assign.vue  ← ユーザー側のSet.vueのベース
resources/js/Pages/ProofCoordinator/Assignments/Edit.vue
resources/js/Components/Tabs/UserNavigationTabs.vue
resources/js/Pages/User/MyJobBox/Index.vue
resources/js/Pages/User/Calendar.vue  ← ユーザーカレンダー（存在確認が必要）

app/Http/Middleware/HandleInertiaRequests.php  ← 共有データ
routes/web.php
```

### CLAUDE.md の重要ルール（必ず守ること）

- Vue/JS ファイル変更後は必ず `npm run build` を実行
- Artisan は `docker compose exec laravel bash -lc "php artisan ..."` で実行
- ルートは `routes/web.php` に置く（`api.php` ではない）
- CSRF トークンは `meta[name="csrf-token"]` から取得（クッキーから取得しない）
- ナビゲーションは必ず `route()` を使う（パスのハードコード禁止）
- AppLayout の使い方: デフォルトスロットに `<div class="rounded bg-white p-6 shadow">` を入れる
- `main`タグ、`py-12`の重複ラップ禁止
- DB の datetime は UTC 保存。Carbon の `toIso8601String()` は JST で返るので、
  フロントに渡す際は `Carbon::createFromFormat('Y-m-d H:i:s', $raw, 'UTC')->toIso8601String()` を使うこと

---

## 実装フェーズ一覧

1. Phase 1: DB マイグレーション（`job_type` カラム追加）
2. Phase 2: AssignmentForm.vue に作業時間スロット追加
3. Phase 3: Coordinator の assignStore/assignmentUpdate でスロット処理
4. Phase 4: User/ProofJobController + ルート作成
5. Phase 5: User/ProofJobs/Index.vue と Set.vue 作成
6. Phase 6: UserNavigationTabs + HandleInertiaRequests 更新
7. Phase 7: Coordinator 編集時の競合警告
8. Phase 8: カレンダーの「校正をセット」メニュー
9. Phase 9: MyJobBox 種類カラムに「校正」バッジ
10. Phase 10: 案件ジョブ履歴に校正依頼を追加

---

## Phase 1: DB マイグレーション

### 1-1. `project_job_assignments` に `job_type` カラム追加

**ファイル**: `database/migrations/2026_04_12_300001_add_job_type_to_project_job_assignments.php`

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') return;
        Schema::table('project_job_assignments', function (Blueprint $table) {
            $table->string('job_type', 20)->nullable()->after('supersedes_assignment_id');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') return;
        Schema::table('project_job_assignments', function (Blueprint $table) {
            $table->dropColumn('job_type');
        });
    }
};
```

**実行**:
```bash
docker compose exec laravel bash -lc "php artisan migrate"
```

### 1-2. ProjectJobAssignment モデルに `job_type` 追加

`app/Models/ProjectJobAssignment.php` の `$fillable` と `$casts` に `job_type` を追加。

---

## Phase 2: AssignmentForm.vue に作業時間スロット追加

**ファイル**: `resources/js/Pages/Coordinator/ProjectJobs/JobAssign/AssignmentForm.vue`

### 2-1. Props 追加

```js
// 既存 props の末尾に追加
showWorkSlots: { type: Boolean, default: false },
initialWorkSlots: { type: Array, default: () => [] },
```

### 2-2. Script に workSlots state 追加

```js
// setup() 内または <script setup> のトップレベルに追加
const workSlots = ref(
    props.initialWorkSlots.length > 0
        ? props.initialWorkSlots.map(s => ({ ...s }))
        : []
);

const SLOT_HOURS   = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0'));
const SLOT_MINUTES = ['00', '15', '30', '45'];

function addWorkSlot() {
    workSlots.value.push({ date: '', startHour: '09', startMinute: '00', endHour: '18', endMinute: '00' });
}

function removeWorkSlot(idx) {
    workSlots.value.splice(idx, 1);
}

function formatSlotDuration(slot) {
    const sh = Number(slot.startHour) * 60 + Number(slot.startMinute);
    const eh = Number(slot.endHour)   * 60 + Number(slot.endMinute);
    const mins = Math.max(0, eh - sh);
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    if (h > 0 && m > 0) return `${h}時間${m}分`;
    if (h > 0) return `${h}時間`;
    return `${m}分`;
}
```

### 2-3. submit/save 時に work_slots を含める

既存の `submit()` または `save()` 関数内で、payload に `work_slots` を追加:

```js
// save() や submit() でフォームデータを組み立てる箇所に追加
if (props.showWorkSlots) {
    payload.work_slots = workSlots.value
        .filter(s => s.date)
        .map(s => ({
            date:        s.date,
            startHour:   String(s.startHour).padStart(2, '0'),
            startMinute: String(s.startMinute).padStart(2, '0'),
            endHour:     String(s.endHour).padStart(2, '0'),
            endMinute:   String(s.endMinute).padStart(2, '0'),
        }));
}
```

### 2-4. Template に作業時間スロットセクション追加

フォームの保存ボタンより上（最下部）に追加:

```html
<!-- 作業日・時間スロット（showWorkSlots=true のときのみ表示） -->
<div v-if="showWorkSlots" class="mt-6 rounded border border-pink-100 bg-pink-50 p-4">
    <div class="mb-3 flex items-center justify-between">
        <h4 class="text-sm font-semibold text-pink-700">作業日・時間</h4>
        <button
            type="button"
            @click="addWorkSlot"
            class="rounded bg-pink-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-pink-700"
        >
            ＋ 追加
        </button>
    </div>
    <div v-if="workSlots.length === 0" class="text-xs text-gray-400">
        「＋ 追加」で作業日・時間を登録できます。
    </div>
    <div class="space-y-2">
        <div
            v-for="(slot, idx) in workSlots"
            :key="idx"
            class="flex flex-wrap items-end gap-3 rounded border border-pink-200 bg-white p-3"
        >
            <!-- 日付 -->
            <div>
                <label class="block text-xs text-gray-500">日付</label>
                <input
                    v-model="slot.date"
                    type="date"
                    class="mt-1 rounded border-gray-300 text-sm shadow-sm focus:border-pink-500 focus:ring-pink-500"
                />
            </div>
            <!-- 開始時刻 -->
            <div>
                <label class="block text-xs text-gray-500">開始</label>
                <div class="mt-1 flex items-center gap-1">
                    <select v-model="slot.startHour" class="rounded border-gray-300 text-sm">
                        <option v-for="h in SLOT_HOURS" :key="h" :value="h">{{ h }}</option>
                    </select>
                    <span class="text-gray-400">:</span>
                    <select v-model="slot.startMinute" class="rounded border-gray-300 text-sm">
                        <option v-for="m in SLOT_MINUTES" :key="m" :value="m">{{ m }}</option>
                    </select>
                </div>
            </div>
            <!-- 終了時刻 -->
            <div>
                <label class="block text-xs text-gray-500">終了</label>
                <div class="mt-1 flex items-center gap-1">
                    <select v-model="slot.endHour" class="rounded border-gray-300 text-sm">
                        <option v-for="h in SLOT_HOURS" :key="h" :value="h">{{ h }}</option>
                    </select>
                    <span class="text-gray-400">:</span>
                    <select v-model="slot.endMinute" class="rounded border-gray-300 text-sm">
                        <option v-for="m in SLOT_MINUTES" :key="m" :value="m">{{ m }}</option>
                    </select>
                </div>
            </div>
            <!-- 時間計算 -->
            <div class="text-xs text-gray-500">{{ formatSlotDuration(slot) }}</div>
            <!-- 削除 -->
            <button
                type="button"
                @click="removeWorkSlot(idx)"
                class="ml-auto rounded bg-red-50 px-2 py-1 text-xs text-red-500 hover:bg-red-100"
            >
                削除
            </button>
        </div>
    </div>
</div>
```

---

## Phase 3: Coordinator assignStore / assignmentUpdate でスロット処理

### 3-1. ヘルパーメソッド追加

`ProofRequestController.php` にプライベートヘルパーを追加:

```php
/**
 * work_slots から ProofSchedule と Event を作成・更新する
 * $proofRequest: ProofRequest
 * $slots: [['date'=>'2026-04-12','startHour'=>'09','startMinute'=>'00','endHour'=>'17','endMinute'=>'00'], ...]
 * $replace: true の場合は既存エントリを削除してから再作成
 */
private function saveWorkSlots(ProofRequest $proofRequest, array $slots, bool $replace = false): void
{
    if (empty($slots)) return;

    // ── ProofSchedule（Coordinator カレンダー用）──────────────
    if ($replace) {
        ProofSchedule::where('proof_request_id', $proofRequest->id)->delete();
    }

    // ── pja101（ユーザー自己割当）を特定または作成 ──────────
    $pja100 = ProjectJobAssignment::where('project_job_id', $proofRequest->project_job_id)
        ->where('user_id', $proofRequest->proofreader_id)
        ->where('sender_id', $proofRequest->proof_coordinator_id)
        ->latest()->first();

    $pja101 = null;
    if ($pja100) {
        $pja101 = ProjectJobAssignment::whereColumn('sender_id', 'user_id')
            ->where(function ($q) use ($pja100) {
                $q->where('source_assignment_id', $pja100->id)
                  ->orWhere('supersedes_assignment_id', $pja100->id);
            })->latest()->first();

        if (! $pja101 && $proofRequest->proofreader_id) {
            // pja101 がなければ作成
            $pja101 = ProjectJobAssignment::create([
                'project_job_id'       => $proofRequest->project_job_id,
                'user_id'              => $proofRequest->proofreader_id,
                'sender_id'            => $proofRequest->proofreader_id,
                'source_assignment_id' => $pja100->id,
                'job_type'             => 'proof',
                'title'                => $proofRequest->title,
                'scheduled'            => true,
                'scheduled_at'         => now(),
            ]);
        }

        if ($replace && $pja101) {
            // 既存イベントを削除
            \App\Models\Event::where('project_job_assignment_id', $pja101->id)->delete();
        }
    }

    foreach ($slots as $slot) {
        $date  = $slot['date'];
        $sH    = str_pad($slot['startHour'],   2, '0', STR_PAD_LEFT);
        $sM    = str_pad($slot['startMinute'], 2, '0', STR_PAD_LEFT);
        $eH    = str_pad($slot['endHour'],     2, '0', STR_PAD_LEFT);
        $eM    = str_pad($slot['endMinute'],   2, '0', STR_PAD_LEFT);

        // JST → UTC 変換
        $startsAt = \Carbon\Carbon::parse("{$date} {$sH}:{$sM}:00", 'Asia/Tokyo')->utc();
        $endsAt   = \Carbon\Carbon::parse("{$date} {$eH}:{$eM}:00", 'Asia/Tokyo')->utc();

        // ProofSchedule 作成
        ProofSchedule::create([
            'proof_request_id' => $proofRequest->id,
            'user_id'          => $proofRequest->proofreader_id,
            'starts_at'        => $startsAt,
            'ends_at'          => $endsAt,
        ]);

        // Event 作成（pja101 がある場合）
        if ($pja101) {
            \App\Models\Event::create([
                'user_id'                    => $proofRequest->proofreader_id,
                'project_job_assignment_id'  => $pja101->id,
                'date'                       => $date,
                'start'                      => "{$date} {$sH}:{$sM}:00",
                'end'                        => "{$date} {$eH}:{$eM}:00",
                'starts_at'                  => $startsAt,
                'ends_at'                    => $endsAt,
                'title'                      => $proofRequest->title,
            ]);
        }
    }
}
```

### 3-2. assignStore() に work_slots 処理追加

既存の `assignStore()` の最後（`return redirect(...)` の前）に追加:

```php
// work_slots 処理
$rawSlots = $request->input('work_slots', []);
if (is_array($rawSlots) && count($rawSlots) > 0) {
    $this->saveWorkSlots($proofRequest->fresh(), $rawSlots, false);
}
```

### 3-3. assignmentUpdate() に work_slots 処理追加（競合対応付き）

```php
// work_slots があれば既存を削除して再作成（replace=true）
$rawSlots = $request->input('work_slots', []);
if (is_array($rawSlots) && count($rawSlots) > 0) {
    $this->saveWorkSlots($proofRequest, $rawSlots, true);
}
```

### 3-4. Inbox/Assign.vue に `:show-work-slots="true"` を渡す

`resources/js/Pages/ProofCoordinator/Inbox/Assign.vue` の AssignmentForm コンポーネントに追加:

```html
<AssignmentForm
    ...既存の props...
    :show-work-slots="true"
/>
```

---

## Phase 4: User/ProofJobController 作成

**ファイル**: `app/Http/Controllers/User/ProofJobController.php`

```php
<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\ProofRequest;
use App\Models\ProofSchedule;
use App\Models\ProjectJobAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProofJobController extends Controller
{
    // ──────────────────────────────────────────────────────
    //  一覧
    // ──────────────────────────────────────────────────────
    public function index(): Response
    {
        $user = Auth::user();

        $proofRequests = ProofRequest::with(['requester', 'projectJob', 'proofreader'])
            ->where('proofreader_id', $user->id)
            ->whereIn('status', ['assigned', 'in_progress', 'completed'])
            ->orderByRaw("FIELD(status, 'in_progress', 'assigned', 'completed')")
            ->orderBy('deadline')
            ->get()
            ->map(function ($pr) use ($user) {
                // pja101 の有無を確認
                $pja100 = ProjectJobAssignment::where('project_job_id', $pr->project_job_id)
                    ->where('user_id', $pr->proofreader_id)
                    ->where('sender_id', $pr->proof_coordinator_id)
                    ->latest()->first();

                $pja101 = null;
                $workSlots = [];
                if ($pja100) {
                    $pja101 = ProjectJobAssignment::whereColumn('sender_id', 'user_id')
                        ->where(function ($q) use ($pja100) {
                            $q->where('source_assignment_id', $pja100->id)
                              ->orWhere('supersedes_assignment_id', $pja100->id);
                        })->latest()->first();

                    if ($pja101) {
                        $workSlots = Event::where('project_job_assignment_id', $pja101->id)
                            ->orderBy('starts_at')
                            ->get()
                            ->map(fn ($ev) => [
                                'date'        => $ev->date ?? substr($ev->start, 0, 10),
                                'startTime'   => substr($ev->start, 11, 5),
                                'endTime'     => substr($ev->end, 11, 5),
                            ])->toArray();
                    }
                }

                return [
                    'id'             => $pr->id,
                    'title'          => $pr->title,
                    'status'         => $pr->status,
                    'deadline'       => $pr->deadline?->toIso8601String(),
                    'requester_name' => $pr->requester?->name,
                    'job_title'      => $pr->projectJob?->title,
                    'is_set'         => $pja101 !== null,
                    'work_slots'     => $workSlots,
                ];
            })->toArray();

        return Inertia::render('User/ProofJobs/Index', [
            'proofRequests' => $proofRequests,
        ]);
    }

    // ──────────────────────────────────────────────────────
    //  セットページ（フォーム表示）
    // ──────────────────────────────────────────────────────
    public function setPage(ProofRequest $proofRequest): Response
    {
        $user = Auth::user();

        // 自分が担当校正員でなければ403
        abort_if($proofRequest->proofreader_id !== $user->id, 403);

        // pja100 を取得
        $pja100 = ProjectJobAssignment::with(['projectJob', 'user', 'statusModel'])
            ->where('project_job_id', $proofRequest->project_job_id)
            ->where('user_id', $proofRequest->proofreader_id)
            ->where('sender_id', $proofRequest->proof_coordinator_id)
            ->latest()->first();

        // 既存の作業スロット取得
        $existingSlots = [];
        if ($pja100) {
            $pja101 = ProjectJobAssignment::whereColumn('sender_id', 'user_id')
                ->where(function ($q) use ($pja100) {
                    $q->where('source_assignment_id', $pja100->id)
                      ->orWhere('supersedes_assignment_id', $pja100->id);
                })->latest()->first();

            if ($pja101) {
                $existingSlots = Event::where('project_job_assignment_id', $pja101->id)
                    ->orderBy('starts_at')
                    ->get()
                    ->map(fn ($ev) => [
                        'date'        => $ev->date ?? substr($ev->start, 0, 10),
                        'startHour'   => substr($ev->start, 11, 2),
                        'startMinute' => substr($ev->start, 14, 2),
                        'endHour'     => substr($ev->end, 11, 2),
                        'endMinute'   => substr($ev->end, 14, 2),
                    ])->toArray();
            }
        }

        // AssignmentForm に必要なルックアップテーブル
        $types        = \App\Models\WorkItemType::orderBy('sort_order')->get(['id','name','group']);
        $sizes        = \App\Models\Size::orderBy('sort_order')->get(['id','name','group']);
        $stages       = \App\Models\Stage::orderBy('sort_order')->get(['id','name']);
        $statuses     = \App\Models\Status::orderBy('sort_order')->get(['id','name','key']);
        $difficulties = \App\Models\Difficulty::orderBy('sort_order')->get(['id','name']);
        $companies    = \App\Models\Company::orderBy('name')->get(['id','name']);

        return Inertia::render('User/ProofJobs/Set', [
            'proofRequest'   => [
                'id'           => $proofRequest->id,
                'title'        => $proofRequest->title,
                'deadline'     => $proofRequest->deadline?->toIso8601String(),
                'status'       => $proofRequest->status,
                'note'         => $proofRequest->note,
                'requester'    => $proofRequest->requester,
                'project_job'  => $proofRequest->projectJob,
            ],
            'assignment'     => $pja100,
            'projectJob'     => $pja100?->projectJob,
            'members'        => $pja100 ? [['id' => $user->id, 'name' => $user->name]] : [],
            'assignments_data' => $pja100 ? [$pja100->toArray()] : [],
            'existingSlots'  => $existingSlots,
            'types'          => $types,
            'sizes'          => $sizes,
            'stages'         => $stages,
            'statuses'       => $statuses,
            'difficulties'   => $difficulties,
            'companies'      => $companies,
            'user_role'      => $user->user_role,
            'user_company_id' => $user->company_id,
            'user_department_id' => $user->department_id,
        ]);
    }

    // ──────────────────────────────────────────────────────
    //  セット保存
    // ──────────────────────────────────────────────────────
    public function set(Request $request, ProofRequest $proofRequest)
    {
        $user = Auth::user();
        abort_if($proofRequest->proofreader_id !== $user->id, 403);

        $slots = $request->input('work_slots', []);

        // pja100 取得
        $pja100 = ProjectJobAssignment::where('project_job_id', $proofRequest->project_job_id)
            ->where('user_id', $proofRequest->proofreader_id)
            ->where('sender_id', $proofRequest->proof_coordinator_id)
            ->latest()->first();

        if (! $pja100) {
            return back()->with('error', '割り当て情報が見つかりません。');
        }

        // pja101 取得または作成
        $pja101 = ProjectJobAssignment::whereColumn('sender_id', 'user_id')
            ->where(function ($q) use ($pja100) {
                $q->where('source_assignment_id', $pja100->id)
                  ->orWhere('supersedes_assignment_id', $pja100->id);
            })->latest()->first();

        if (! $pja101) {
            $pja101 = ProjectJobAssignment::create([
                'project_job_id'       => $proofRequest->project_job_id,
                'user_id'              => $user->id,
                'sender_id'            => $user->id,
                'source_assignment_id' => $pja100->id,
                'job_type'             => 'proof',
                'title'                => $proofRequest->title,
                'scheduled'            => ! empty($slots),
                'scheduled_at'         => ! empty($slots) ? now() : null,
            ]);
        } else {
            $pja101->update([
                'scheduled'    => ! empty($slots),
                'scheduled_at' => ! empty($slots) ? now() : $pja101->scheduled_at,
            ]);
        }

        // 既存イベント削除 → 再作成
        Event::where('project_job_assignment_id', $pja101->id)->delete();
        ProofSchedule::where('proof_request_id', $proofRequest->id)
            ->where('user_id', $user->id)
            ->delete();

        foreach ($slots as $slot) {
            if (empty($slot['date'])) continue;

            $date  = $slot['date'];
            $sH    = str_pad($slot['startHour'],   2, '0', STR_PAD_LEFT);
            $sM    = str_pad($slot['startMinute'], 2, '0', STR_PAD_LEFT);
            $eH    = str_pad($slot['endHour'],     2, '0', STR_PAD_LEFT);
            $eM    = str_pad($slot['endMinute'],   2, '0', STR_PAD_LEFT);

            $startsAt = \Carbon\Carbon::parse("{$date} {$sH}:{$sM}:00", 'Asia/Tokyo')->utc();
            $endsAt   = \Carbon\Carbon::parse("{$date} {$eH}:{$eM}:00", 'Asia/Tokyo')->utc();

            Event::create([
                'user_id'                   => $user->id,
                'project_job_assignment_id' => $pja101->id,
                'date'                      => $date,
                'start'                     => "{$date} {$sH}:{$sM}:00",
                'end'                       => "{$date} {$eH}:{$eM}:00",
                'starts_at'                 => $startsAt,
                'ends_at'                   => $endsAt,
                'title'                     => $proofRequest->title,
            ]);

            ProofSchedule::create([
                'proof_request_id' => $proofRequest->id,
                'user_id'          => $user->id,
                'starts_at'        => $startsAt,
                'ends_at'          => $endsAt,
            ]);
        }

        // ステータス更新
        if ($proofRequest->status === 'assigned' && ! empty($slots)) {
            $proofRequest->update(['status' => 'in_progress']);
        }

        return redirect()->route('user.proof_jobs.index')
            ->with('success', '校正をセットしました。');
    }
}
```

---

## Phase 5: Vue ページ作成

### 5-1. `resources/js/Pages/User/ProofJobs/Index.vue`

```vue
<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import UserNavigationTabs from '@/Components/Tabs/UserNavigationTabs.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    proofRequests: { type: Array, default: () => [] },
});

const statusLabel = {
    pending:     '受理待ち',
    assigned:    '割り当て済み',
    in_progress: '校正中',
    completed:   '完了',
};
const statusBadge = {
    pending:     'bg-gray-100 text-gray-700',
    assigned:    'bg-blue-100 text-blue-800',
    in_progress: 'bg-pink-100 text-pink-800',
    completed:   'bg-yellow-100 text-yellow-800',
};

function fmtDeadline(isoStr) {
    if (!isoStr) return '—';
    const fmt = new Intl.DateTimeFormat('ja-JP', {
        timeZone: 'Asia/Tokyo',
        year: 'numeric', month: 'numeric', day: 'numeric',
        hour: '2-digit', minute: '2-digit', hour12: false,
    });
    const p = Object.fromEntries(fmt.formatToParts(new Date(isoStr)).map(({ type, value }) => [type, value]));
    return `${p.year}年${p.month}月${p.day}日 ${p.hour}時${p.minute}分`;
}
</script>

<template>
    <AppLayout title="校正ジョブ">
        <template #header>
            <h2 class="text-xl font-semibold text-gray-800">校正ジョブ</h2>
        </template>
        <template #tabs>
            <UserNavigationTabs active="proof_jobs" />
        </template>

        <div class="rounded bg-white shadow">
            <div v-if="proofRequests.length === 0"
                 class="px-6 py-12 text-center text-sm text-gray-400">
                割り当てられた校正ジョブはありません。
            </div>
            <table v-else class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">タイトル</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">案件</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">締め切り</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">ステータス</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500">作業時間</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="pr in proofRequests" :key="pr.id" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ pr.title }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ pr.job_title ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ fmtDeadline(pr.deadline) }}</td>
                        <td class="px-4 py-3">
                            <span :class="['rounded-full px-2 py-0.5 text-xs font-semibold', statusBadge[pr.status]]">
                                {{ statusLabel[pr.status] ?? pr.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            <div v-if="pr.work_slots.length > 0" class="space-y-0.5">
                                <div v-for="(slot, i) in pr.work_slots" :key="i">
                                    {{ slot.date }} {{ slot.startTime }}〜{{ slot.endTime }}
                                </div>
                            </div>
                            <span v-else class="text-gray-300">未設定</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Link
                                v-if="pr.status !== 'completed'"
                                :href="route('user.proof_jobs.set_page', { proofRequest: pr.id })"
                                class="rounded bg-pink-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-pink-700"
                            >
                                {{ pr.is_set ? '予定を変更' : '校正をセット' }}
                            </Link>
                            <span v-else class="rounded bg-yellow-100 px-2 py-1 text-xs text-yellow-700">完了済み</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
```

### 5-2. `resources/js/Pages/User/ProofJobs/Set.vue`

`resources/js/Pages/ProofCoordinator/Inbox/Assign.vue` をベースに作成。
以下の違いがある:
- タイトル: 「校正をセット」
- タブ: `UserNavigationTabs active="proof_jobs"`
- `updateUrl = route('user.proof_jobs.set', { proofRequest: proofRequest.id })`
- AssignmentForm の props: `mode="user"`, `:save-only="true"`, `:show-work-slots="true"`, `:initial-work-slots="existingSlots"`, `:update-override-url="updateUrl"`, `:edit-mode="existingSlots.length > 0"`
- 保存は `POST` ではなく既存の AssignmentForm の submit に委ねる

```vue
<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import UserNavigationTabs from '@/Components/Tabs/UserNavigationTabs.vue';
import AssignmentForm from '@/Pages/Coordinator/ProjectJobs/JobAssign/AssignmentForm.vue';
import { Link } from '@inertiajs/vue3';

function fmtDeadline(isoStr) {
    if (!isoStr) return '—';
    const fmt = new Intl.DateTimeFormat('ja-JP', {
        timeZone: 'Asia/Tokyo',
        year: 'numeric', month: 'numeric', day: 'numeric',
        hour: '2-digit', minute: '2-digit', hour12: false,
    });
    const p = Object.fromEntries(fmt.formatToParts(new Date(isoStr)).map(({ type, value }) => [type, value]));
    return `${p.year}年${p.month}月${p.day}日 ${p.hour}時${p.minute}分`;
}

const props = defineProps({
    proofRequest:       { type: Object, required: true },
    assignment:         { type: Object, default: null },
    projectJob:         { type: Object, default: null },
    members:            { type: Array,  default: () => [] },
    assignments_data:   { type: Array,  default: () => [] },
    existingSlots:      { type: Array,  default: () => [] },
    types:              { type: Array,  default: () => [] },
    sizes:              { type: Array,  default: () => [] },
    stages:             { type: Array,  default: () => [] },
    statuses:           { type: Array,  default: () => [] },
    difficulties:       { type: Array,  default: () => [] },
    companies:          { type: Array,  default: () => [] },
    user_role:          { type: String, default: '' },
    user_company_id:    { type: [Number, String], default: null },
    user_department_id: { type: [Number, String], default: null },
});

const updateUrl = route('user.proof_jobs.set', { proofRequest: props.proofRequest.id });
</script>

<template>
    <AppLayout title="校正をセット">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800">校正をセット</h2>
                <Link
                    :href="route('user.proof_jobs.index')"
                    class="text-sm text-gray-500 hover:text-gray-700"
                >
                    ← 一覧に戻る
                </Link>
            </div>
        </template>
        <template #tabs>
            <UserNavigationTabs active="proof_jobs" />
        </template>

        <div class="space-y-4">
            <!-- 校正依頼情報（読み取り専用） -->
            <div class="rounded border border-pink-100 bg-pink-50 p-4 text-sm">
                <p class="mb-1 font-semibold text-pink-700">校正依頼情報</p>
                <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-gray-700 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs font-medium text-gray-500">依頼者</dt>
                        <dd>{{ proofRequest.requester?.name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500">関連案件</dt>
                        <dd>{{ proofRequest.project_job?.title ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500">校正締め切り</dt>
                        <dd :class="proofRequest.deadline && new Date(proofRequest.deadline) < new Date() ? 'font-bold text-red-600' : ''">
                            {{ fmtDeadline(proofRequest.deadline) }}
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- ジョブ割り当てフォーム（作業時間スロット付き） -->
            <div class="rounded bg-white p-6 shadow">
                <AssignmentForm
                    mode="user"
                    :projectJob="projectJob"
                    :members="members"
                    :assignments="assignments_data"
                    :editMode="existingSlots.length > 0"
                    :hide-status="true"
                    :save-only="true"
                    :update-override-url="updateUrl"
                    :show-work-slots="true"
                    :initial-work-slots="existingSlots"
                    :types="types"
                    :sizes="sizes"
                    :stages="stages"
                    :statuses="statuses"
                    :difficulties="difficulties"
                    :companies="companies"
                    :user_role="user_role"
                    :user_company_id="user_company_id"
                    :user_department_id="user_department_id"
                />
            </div>
        </div>
    </AppLayout>
</template>
```

---

## Phase 6: ルート追加 と HandleInertiaRequests 更新

### 6-1. routes/web.php

ユーザーミドルウェアグループに追加（既存の `user.*` ルートと同じミドルウェアグループ内）:

```php
// 校正ジョブ（ユーザー）
Route::get('user/proof-jobs', [\App\Http\Controllers\User\ProofJobController::class, 'index'])->name('user.proof_jobs.index');
Route::get('user/proof-jobs/{proofRequest}/set', [\App\Http\Controllers\User\ProofJobController::class, 'setPage'])->name('user.proof_jobs.set_page');
Route::post('user/proof-jobs/{proofRequest}/set', [\App\Http\Controllers\User\ProofJobController::class, 'set'])->name('user.proof_jobs.set');
```

### 6-2. HandleInertiaRequests.php

`share()` メソッドに追加:

```php
'auth' => [
    // ... 既存の共有データ ...
    'isProofMember' => $request->user()
        ? \App\Models\ProofTeamMember::where('user_id', $request->user()->id)->exists()
        : false,
],
```

### 6-3. UserNavigationTabs.vue

```vue
<!-- 既存の <script setup> に追加 -->
const page = usePage();
const isProofMember = computed(() => page.props.auth?.isProofMember ?? false);
```

テンプレートに追加（既存タブの末尾付近）:

```html
<Link
    v-if="isProofMember"
    :href="route('user.proof_jobs.index')"
    :class="[
        'border-b-2 px-4 py-2 text-sm font-medium whitespace-nowrap',
        active === 'proof_jobs'
            ? 'border-pink-500 text-pink-600'
            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
    ]"
>
    校正ジョブ
</Link>
```

---

## Phase 7: Coordinator 編集時の競合警告

### 7-1. ProofRequestController::edit() に `userHasSetSchedule` 追加

```php
// edit() 内の既存のpja101検索後に追加
$userHasSetSchedule = $pja101 && \App\Models\Event::where('project_job_assignment_id', $pja101->id)->exists();

return Inertia::render('ProofCoordinator/Assignments/Edit', [
    // ... 既存の props ...
    'userHasSetSchedule' => $userHasSetSchedule,
]);
```

### 7-2. Assignments/Edit.vue に警告バナー追加

Props に追加:
```js
userHasSetSchedule: { type: Boolean, default: false },
```

テンプレートの最上部（フォームの前）に追加:

```html
<!-- 競合警告 -->
<div v-if="userHasSetSchedule"
     class="rounded border border-yellow-300 bg-yellow-50 p-4 text-sm">
    <p class="font-semibold text-yellow-800">⚠️ 注意：校正員が既に作業時間をセットしています</p>
    <p class="mt-1 text-yellow-700">
        作業時間を変更すると、校正員のカレンダー予定も上書きされます。
        変更する場合は保存してください。
    </p>
</div>
```

---

## Phase 8: カレンダーの「校正をセット」メニュー

**ファイル**: `resources/js/Pages/User/Calendar.vue`（存在しない場合は対応するカレンダーページを探すこと）

新規イベント作成モーダル / 空きエリアクリック時のパネルに追加:

```html
<!-- isProofMember のときのみ表示 -->
<div v-if="page.props.auth?.isProofMember"
     class="mt-3 border-t border-gray-100 pt-3">
    <Link
        :href="route('user.proof_jobs.index')"
        class="flex items-center gap-2 rounded bg-pink-50 px-3 py-2 text-sm text-pink-700 hover:bg-pink-100"
    >
        <span class="font-medium">校正をセット →</span>
    </Link>
</div>
```

---

## Phase 9: MyJobBox 種類カラムに「校正」バッジ

### 9-1. MyProjectJobController (または MyJobBox コントローラ) で `job_type` を返す

assignments の map で `job_type` フィールドを含めること:

```php
'job_type' => $assignment->job_type,
```

### 9-2. MyJobBox/Index.vue の種類カラム

既存の種類表示ロジックに追加:

```html
<!-- 種類カラムの該当箇所 -->
<span v-if="item.job_type === 'proof'"
      class="rounded-full bg-pink-100 px-2 py-0.5 text-xs font-semibold text-pink-700">
    校正
</span>
```

---

## Phase 10: 案件ジョブ履歴に校正依頼を追加

### 10-1. Coordinator/ProjectJobController::show() に proof_requests を追加

```php
// 既存の jobHistory 取得後に追加
$proofItems = \App\Models\ProofRequest::with(['requester', 'proofreader'])
    ->where('project_job_id', $projectJob->id)
    ->get()
    ->map(fn ($pr) => [
        'id'             => $pr->id,
        'type'           => 'proof',
        'title'          => $pr->title,
        'status'         => $pr->status,
        'deadline'       => $pr->deadline?->toIso8601String(),
        'requester_name' => $pr->requester?->name,
        'proofreader_name' => $pr->proofreader?->name,
        'created_at'     => $pr->created_at->toIso8601String(),
    ])->toArray();

// jobHistory に merge
// 既存の $jobHistory 配列に array_merge($jobHistory, $proofItems) するか、
// 別 prop 'proofHistory' として渡す（実装しやすい方を選択）
```

### 10-2. ProjectJobs/Show.vue に校正履歴セクション追加

```html
<!-- 校正依頼履歴 -->
<div v-if="proofHistory && proofHistory.length > 0" class="rounded bg-white p-6 shadow">
    <h3 class="mb-3 text-sm font-semibold text-gray-700">校正依頼履歴</h3>
    <div class="space-y-2">
        <div v-for="pr in proofHistory" :key="pr.id"
             class="flex items-center justify-between rounded border border-pink-100 bg-pink-50 px-4 py-2 text-sm">
            <div>
                <span class="font-medium text-pink-800">{{ pr.title }}</span>
                <span class="ml-2 text-xs text-gray-500">校正員: {{ pr.proofreader_name ?? '—' }}</span>
            </div>
            <span :class="[
                'rounded-full px-2 py-0.5 text-xs font-semibold',
                pr.status === 'completed' ? 'bg-yellow-100 text-yellow-700' :
                pr.status === 'in_progress' ? 'bg-pink-100 text-pink-800' : 'bg-gray-100 text-gray-600'
            ]">
                {{ { pending: '受理待ち', assigned: '割当済み', in_progress: '校正中', completed: '完了' }[pr.status] ?? pr.status }}
            </span>
        </div>
    </div>
</div>
```

---

## 最終確認チェックリスト

実装完了後に確認すること:

- [ ] `php artisan migrate` 実行済み
- [ ] `npm run build` 実行済み
- [ ] `php artisan config:clear && php artisan cache:clear` 実行済み
- [ ] 校正チームメンバーのユーザーでログインし、UserNavigationTabs に「校正ジョブ」タブが表示される
- [ ] 校正ジョブ一覧に割り当てられた ProofRequest が表示される
- [ ] 「校正をセット」ボタンでフォームに遷移できる
- [ ] 作業時間スロットを追加・保存できる
- [ ] 保存後、ユーザーカレンダーに Events が登録される
- [ ] Coordinator の edit ページで既存スロットがある場合に警告バナーが表示される
- [ ] MyJobBox 一覧で `job_type='proof'` の行に「校正」ピンクバッジが表示される
- [ ] 案件詳細ページに校正履歴が表示される

---

## 注意事項

- `Event` モデルの `start`/`end` は `Y-m-d H:i:s` のローカル時刻（JST）、`starts_at`/`ends_at` は UTC
- カレンダーに渡す datetime は必ず UTC ISO 文字列にすること（`Carbon::createFromFormat(..., 'UTC')->toIso8601String()`）
- `ProofSchedule` と `Event` の両方を同期させること（片方だけ更新しない）
- Coordinator が work_slots を変更したとき (`assignmentUpdate`) は `replace=true` で既存を全削除後に再作成
- `job_type = 'proof'` は pja101 作成時にセットすること（pja100 には不要）
