# SunBWork レイアウト修繕 Claude向けプロンプトファイル
作成日: 2026-05-02

---

## このファイルの使い方

新しい Claude セッションを開始するとき、このファイルの内容をそのまま冒頭に貼り付けてください。
または「LAYOUT_REPAIR_PROMPT.md を読んで修繕作業を続けてください」と指示してください。

---

## セッション開始時のプロンプト（コピーして使用）

```
あなたはこれからSunBWorkプロジェクトのレイアウト修繕作業を行います。

まず以下のファイルを必ず順番に読んでください：
1. `/home/tchirosb/SunBWork/CLAUDE.md`（プロジェクト全体ルール・必読）
2. `/home/tchirosb/SunBWork/z_instructions/LAYOUT_REPAIR_MANAGER.md`（作業管理書・フロー・進捗一覧）
3. `/home/tchirosb/SunBWork/z_instructions/LAYOUT_REPAIR_PLAN.md`（各作業の詳細仕様）
4. `/home/tchirosb/SunBWork/z_instructions/LAYOUT_SPEC_V2.md`（ガイドライン正解パターン）

読み終えたら、以下を報告してください：
- 現在の進捗状況（未着手・進行中・完了の件数）
- 次に着手すべき推奨作業（理由も添えて）

作業は LAYOUT_REPAIR_MANAGER.md に記載された「作業フロー（5ステップ）」と「安全ルール」に従って進めてください。
特に STEP 2（設計・方針の提示）でユーザーの「OK」を得るまで絶対に実装を始めないこと。

各R-xx作業の完了・進捗状況は必ず LAYOUT_REPAIR_MANAGER.md に記録してください：
- 作業完了時: 進捗一覧のステータスを「✅ 完了」に更新し、作業ログに変更ファイルを記録
- ビルド成功・ユーザー確認待ちの場合: ステータスを「🔨 実装中」に更新

**【作業ペース厳守】**
- STEP 4（動作確認依頼）の後は必ず止まる。ユーザーの「OK」を待ってから STEP 5 に進む。
- STEP 5 完了後は「次は R-xx（内容）が推奨です。進めますか？」と聞いて止まる。
- ユーザーが「yes」「OK」「進めて」などと言うまで、次の作業のファイル読み込みも設計提示も行わない。
```

---

## 設計サマリー（Claude向け補足）

### プロジェクト背景

- **業種:** 印刷・組版会社向け社内管理システム（Laravel 11 + Vue 3 + Inertia.js）
- **目的:** LAYOUT_SPEC_V2.md で定義した統一ガイドラインを全ページに適用する
- **前提:** 修繕計画第1版・第2版（B/L/F/G/V/N シリーズ）はすべて完了済み

### ガイドラインの核心（必ず守る）

1. **`#header` スロット:** 全ページ必須。`<h2 class="text-xl font-semibold leading-tight text-gray-800">` を入れる
2. **戻るボタン:** `#header` 内・h2 の左隣。`Link + route()`、クラス `rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300`、ラベル `← 〇〇に戻る`
3. **アクションボタン:** `#headerExtras` スロット。プライマリは `bg-indigo-600`（`bg-blue-600` 禁止）
4. **コンテンツ内にボタンを置かない:** 戻るボタン・新規作成・編集・削除は header スロット。カード内はフォームボタンのみ
5. **重複ラップ禁止:** `mx-auto max-w-7xl`、`py-12` はAppLayoutが提供済み。`mx-auto` 単体も基本不要

### 作業ID一覧

| フェーズ | ID | 内容（短縮） |
|--------|-----|------------|
| Coordinator残修正 | R-01 | 案件一覧: 新規作成を `#headerExtras` へ、色統一 |
| Coordinator残修正 | R-02 | 案件作成: `#header` 修正・戻るボタン追加 |
| Coordinator残修正 | R-03 | 案件編集: `#header` 修正・戻るボタン移動 |
| Coordinator残修正 | R-04 | ジョブ割り当て一覧: 検索ボタン色・`#header` 修正 |
| Coordinator残修正 | R-05 | ジョブ割り当て詳細: 戻るを `#header` へ |
| Coordinator残修正 | R-06 | 案件選択: 戻るを `#header` へ |
| Coordinator残修正 | R-07 | 外注先3ページ: 戻るボタンスタイル統一 |
| Coordinator残修正 | R-08 | チームメンバー: ボタン色統一 |
| Coordinator残修正 | R-09 | スケジュールコメント作成: 戻る修正 |
| User/JobBox/Events | R-10 | Events/Show: `#header` 追加・ボタン整理 |
| User/JobBox/Events | R-11 | Events/Edit: `#header` 追加・max-w 削除 |
| User/JobBox/Events | R-12 | Events/Create: `#header` 追加・ボタン色 |
| User/JobBox/Events | R-13 | Events/Create_Job: `#header` 追加・ボタン色 |
| User/JobBox/Events | R-14 | JobBox/Show: 戻る・編集・削除を header へ |
| User/JobBox/Events | R-15 | JobBox/Schedule: 戻るを `#header` へ |
| User/JobBox/Events | R-16 | MyJobBox/Show: 戻るを `#header` へ |
| User/JobBox/Events | R-17 | JobNotifications/Index: `#header` 追加・色修正 |
| Admin | R-18 | Admin/Users/Index: ボタン色統一 |
| Admin | R-19 | Admin/Teams/Show: 戻るボタン・max-w修正 |
| Leader/Diaries/Messages | R-20 | Leader/ProjectJobs/Index: max-w削除 |
| Leader/Diaries/Messages | R-21 | Diaries/Index・Show: ボタン修正 |
| Leader/Diaries/Messages | R-22 | Diaries/Interactions/Index: ボタン色 |
| Leader/Diaries/Messages | R-23 | Messages/Show: 戻るボタンスタイル統一 |
| ProofCoordinator等 | R-24 | ProofCoordinator/Dispatchers/Show: 戻るスタイル |
| ProofCoordinator等 | R-25 | ProofCoordinator/Assignments/Show: max-w削除 |

### よくある落とし穴（過去の修正から）

- `goBack()` / `window.history.back()` は SSR・直リンクで壊れる → `Link + route()` に変更
- ルート名の確認を怠ると 404 → 必ず `routes/web.php` でルート名を確認してから使う
- `bg-blue-600` → `bg-indigo-600`（hover も `hover:bg-indigo-700`）
- さくら本番では `route()` 必須・ハードコードパス禁止
- Vue/JSファイル変更後は必ず `npm run build`（プロジェクトルートで実行）
- Artisan は `docker compose exec laravel bash -lc "php artisan ..."`

### 戻るボタン実装パターン（コピー用）

```vue
<!-- パターン1: 固定遷移先 -->
<Link :href="route('coordinator.project_jobs.index')"
  class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
>← 案件一覧に戻る</Link>

<!-- パターン2: タブ付き遷移 -->
<Link :href="route('coordinator.project_jobs.show', { projectJob: projectJob.id }) + '?tab=progress'"
  class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
>← 案件詳細に戻る</Link>

<!-- パターン3: 動的遷移先（computed/function使用） -->
<Link :href="routeBack()"
  class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
>← 戻る</Link>

<!-- パターン4: 遷移元不明（例外的に button 使用） -->
<button @click="goBack"
  class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
>← 戻る</button>
```

### `#header` + `#headerExtras` 基本パターン（コピー用）

```vue
<!-- 詳細ページ（Show）の基本形 -->
<template #header>
  <div class="flex items-center gap-3">
    <Link :href="route('xxx.index')"
      class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
    >← 〇〇一覧に戻る</Link>
    <h2 class="text-xl font-semibold leading-tight text-gray-800">〇〇詳細</h2>
  </div>
</template>

<template #headerExtras>
  <div class="flex items-center gap-2">
    <Link :href="route('xxx.edit', { id: item.id })"
      class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
    >編集</Link>
    <button type="button" @click="confirmDelete"
      class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
    >削除</button>
  </div>
</template>

<!-- 一覧ページ（Index）の基本形 -->
<template #header>
  <h2 class="text-xl font-semibold leading-tight text-gray-800">〇〇一覧</h2>
</template>

<template #headerExtras>
  <Link :href="route('xxx.create')"
    class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
  >新規作成</Link>
</template>
```

### 主要ファイルパス（よく触るもの）

```
resources/js/Pages/Coordinator/ProjectJobs/Index.vue          ← R-01
resources/js/Pages/Coordinator/ProjectJobs/Create.vue         ← R-02
resources/js/Pages/Coordinator/ProjectJobs/Edit.vue           ← R-03
resources/js/Pages/Coordinator/ProjectJobs/JobAssign/Index.vue ← R-04
resources/js/Pages/Coordinator/ProjectJobs/JobAssign/Show.vue  ← R-05
resources/js/Pages/Coordinator/ProjectJobs/JobAssign/SelectProject.vue ← R-06
resources/js/Pages/Coordinator/Subcontractors/Show.vue        ← R-07
resources/js/Pages/Coordinator/Subcontractors/Create.vue      ← R-07
resources/js/Pages/Coordinator/Subcontractors/Edit.vue        ← R-07
resources/js/Pages/Coordinator/ProjectTeamMembers/Create.vue  ← R-08
resources/js/Pages/Coordinator/ProjectTeamMembers/Index.vue   ← R-08
resources/js/Pages/Coordinator/ProjectSchedules/Comments/Create.vue ← R-09
resources/js/Pages/Events/Show.vue                            ← R-10
resources/js/Pages/Events/Edit.vue                            ← R-11
resources/js/Pages/Events/Create.vue                          ← R-12
resources/js/Pages/Events/Create_Job.vue                      ← R-13
resources/js/Pages/JobBox/Show.vue                            ← R-14
resources/js/Pages/JobBox/Schedule.vue                        ← R-15
resources/js/Pages/MyJobBox/Show.vue                          ← R-16
resources/js/Pages/JobNotifications/Index.vue                 ← R-17
resources/js/Pages/Admin/Users/Index.vue                      ← R-18
resources/js/Pages/Admin/Teams/Show.vue                       ← R-19
resources/js/Pages/Leader/ProjectJobs/Index.vue               ← R-20
resources/js/Pages/Diaries/Index.vue                          ← R-21
resources/js/Pages/Diaries/Show.vue                           ← R-21
resources/js/Pages/Diaries/Interactions/Index.vue             ← R-22
resources/js/Pages/Messages/Show.vue                          ← R-23
resources/js/Pages/ProofCoordinator/Dispatchers/Show.vue      ← R-24
resources/js/Pages/ProofCoordinator/Assignments/Show.vue      ← R-25
routes/web.php（ルート名確認用）
```
