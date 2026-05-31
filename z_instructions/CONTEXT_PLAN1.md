# CONTEXT_PLAN1 — SuperAdmin コンテキスト会社フィルタリング

## 目的

SuperAdmin がコンテキスト切り替えで会社を選択したとき、各ページのデータが選択会社のものに絞り込まれるようにする。  
グローバル管理モード（会社未選択）では会社固有ページに「会社を選択してください」バナーを表示し、データは表示しない。

---

## 背景

現状の問題:
- `clerk.announcements.index`: sender_id = SuperAdmin でフィルターするため、全社の通知が混在して表示される
- `coordinator.jobbox`: user_id = SuperAdmin でフィルターするため、SuperAdmin には何も表示されない
- `user.jobbox.index`: 同上
- `coordinator.project_jobs.index`: user_id = SuperAdmin でフィルターするため、何も表示されない

---

## 設計方針

### バックエンド
- SuperAdmin + グローバルモード（context_company_id = null）→ `isGlobalMode: true` を渡し、データは空配列
- SuperAdmin + 会社X選択 → `company_id = X` でデータをフィルタリング
- 一般ユーザー → 既存ロジックそのまま

### フロントエンド
- 共通コンポーネント `SuperAdminGlobalGuard.vue` を新規作成
- 各ページで `isGlobalMode` prop を受け取り、true のとき Guard バナーを表示

---

## 変更ファイル一覧

### 新規作成
| ファイル | 役割 |
|---------|------|
| `resources/js/Components/SuperAdminGlobalGuard.vue` | グローバルモード警告バナー（再利用コンポーネント） |

### バックエンド修正
| ファイル | 修正内容 |
|---------|---------|
| `app/Http/Controllers/Clerk/AnnouncementController.php` | `index()`: SuperAdmin時 target_company_id でフィルター |
| `app/Http/Controllers/Coordinator/ProjectJobController.php` | `index()`: SuperAdmin時 company_id でフィルター |
| `app/Http/Controllers/ProjectJobs/JobBoxController.php` | `global()`: SuperAdmin時 company_id でフィルター / `user()`: SuperAdmin時 company_id でフィルター |

### フロントエンド修正
| ファイル | 修正内容 |
|---------|---------|
| `resources/js/Pages/Clerk/Announcements/Index.vue` | `isGlobalMode` prop 受取・Guard 表示 |
| `resources/js/Pages/Coordinator/ProjectJobs/Index.vue` | `isGlobalMode` prop 受取・Guard 表示 |
| `resources/js/Pages/Coordinator/JobBox/Index.vue` | `isGlobalMode` prop 受取・Guard 表示 |
| `resources/js/Pages/JobBox/Index.vue` | `isGlobalMode` prop 受取・Guard 表示（user jobbox 兼用） |

---

## 詳細仕様

### 1. SuperAdminGlobalGuard.vue

```vue
<script setup>
defineProps({ show: Boolean })
</script>
<template>
  <div v-if="show" class="rounded-lg border border-yellow-300 bg-yellow-50 p-8 text-center">
    <p class="text-base font-medium text-yellow-800">会社を選択してください</p>
    <p class="mt-1 text-sm text-yellow-700">
      グローバル管理モードではこのページを表示できません。<br>
      画面上部の会社切り替えから会社を選択してください。
    </p>
  </div>
  <slot v-else />
</template>
```

### 2. Clerk/AnnouncementController::index()

```php
public function index(Request $request)
{
    $user = $request->user();
    $contextId = $user->isSuperAdmin() ? session('superadmin_context.company_id') : null;

    // SuperAdmin + グローバルモード
    if ($user->isSuperAdmin() && $contextId === null) {
        return Inertia::render('Clerk/Announcements/Index', [
            'announcements' => [],
            'isGlobalMode' => true,
        ]);
    }

    $query = Announcement::where('sender_id', $user->id);

    // SuperAdmin + 会社選択: target_company_id = X OR target_company_id IS NULL（全社宛）
    if ($user->isSuperAdmin() && $contextId) {
        $query->where(function ($q) use ($contextId) {
            $q->where('target_company_id', $contextId)
              ->orWhereNull('target_company_id');
        });
    }

    $announcements = $query->withCount(...)->orderByDesc('created_at')->get()->map(...);

    return Inertia::render('Clerk/Announcements/Index', [
        'announcements' => $announcements,
        'isGlobalMode' => false,
    ]);
}
```

### 3. Coordinator/ProjectJobController::index()

```php
public function index(Request $request)
{
    $user = $request->user();
    $contextId = $user->isSuperAdmin() ? session('superadmin_context.company_id') : null;

    // SuperAdmin + グローバルモード
    if ($user->isSuperAdmin() && $contextId === null) {
        return Inertia::render('Coordinator/ProjectJobs/Index', [
            'jobs' => [], 'favoriteJobs' => [], 'jobid' => null,
            'registerFlags' => [], 'monthOptions' => [], 'q' => '', 'period' => '',
            'isGlobalMode' => true,
        ]);
    }

    // SuperAdmin + 会社選択: company_id でフィルター
    if ($user->isSuperAdmin() && $contextId) {
        $query = ProjectJob::with('client')->where('company_id', $contextId);
        // 検索・期間フィルターは既存ロジックを再利用
    } else {
        // 既存ロジック（user_id または coordinator）
        $query = ProjectJob::with('client')->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhereHas('coordinators', fn ($c) => $c->where('users.id', $user->id));
        });
    }
    // 残りのフィルター・map 処理は既存と同じ
    return Inertia::render('Coordinator/ProjectJobs/Index', [..., 'isGlobalMode' => false]);
}
```

### 4. JobBoxController::global() — SuperAdmin パス追加

既存の `$base` クエリの where 条件を分岐する:
```php
if ($user->isSuperAdmin() && $contextId === null) {
    return inertia('Coordinator/JobBox/Index', [
        'projectJob' => null, 'messages' => [],
        'isGlobalMode' => true, ...
    ]);
}

// $base の user フィルターを分岐
if ($user->isSuperAdmin() && $contextId) {
    $base->where('project_jobs.company_id', $contextId);
} else {
    $base->where(function ($qry) use ($user) { /* 既存 */ });
}
```

month options の計算も同様に分岐。

### 5. JobBoxController::user() — SuperAdmin パス追加

```php
if ($user->isSuperAdmin() && $contextId === null) {
    return inertia('JobBox/Index', [
        'projectJob' => null, 'messages' => [],
        'isGlobalMode' => true, 'routeContext' => 'user', ...
    ]);
}

// project_jobs join を追加し company_id でフィルター
if ($user->isSuperAdmin() && $contextId) {
    $base->join('project_jobs', 'project_job_assignments.project_job_id', '=', 'project_jobs.id')
         ->where('project_jobs.company_id', $contextId);
    // sender != assignee の条件は除外（全受信を表示）
} else {
    $base->where('project_job_assignments.user_id', $user->id)
         ->where('job_assignment_messages.sender_id', '!=', $user->id);
}
```

---

## 注意事項

- `isGlobalMode` は常に boolean として渡す（省略なし）
- 一般ユーザーへの影響なし（`isSuperAdmin()` ガード内のみ変更）
- JobBoxController の `user()` は `project_jobs` join を新規追加するため month options クエリも合わせて修正
- `JobBox/Index.vue` は coordinator jobbox（`global()` → `routeContext:'coordinator'`）と user jobbox（`user()` → `routeContext:'user'`）の両方で使用されることに注意

---

## フェーズ

1. SuperAdminGlobalGuard.vue 作成
2. Clerk/AnnouncementController 修正 + Index.vue 修正
3. Coordinator/ProjectJobController 修正 + Index.vue 修正
4. JobBoxController::global() 修正 + Coordinator/JobBox/Index.vue 修正
5. JobBoxController::user() 修正 + JobBox/Index.vue 修正
6. npm run build + 動作確認
