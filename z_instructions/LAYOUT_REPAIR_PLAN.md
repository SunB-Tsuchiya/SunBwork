# SunBWork レイアウト修繕計画書
作成日: 2026-05-02

> **ベース仕様書:** `z_instructions/LAYOUT_SPEC_V2.md`
> **管理書:** `z_instructions/LAYOUT_REPAIR_MANAGER.md`
> **プロンプト:** `z_instructions/LAYOUT_REPAIR_PROMPT.md`

---

## 作業方針

1. **フェーズ1** — Coordinator残修正（最高頻度ページ）
2. **フェーズ2** — User / JobBox / Events（ユーザー向け主要ページ）
3. **フェーズ3** — Admin（管理機能）
4. **フェーズ4** — Leader / Diaries / Messages
5. **フェーズ5** — ProofCoordinator / Proof / Prepress / Clerk

各フェーズは前フェーズ完了後に着手。フェーズ内の項目は番号順に実施。

### 修正のゴール（LAYOUT_SPEC_V2.md より）

全修正後に各ページが満たすべき条件:
1. `#header` スロットに `<h2>` が入っている
2. 戻るボタンは `#header` 内・h2 の左隣に `Link + route()` で配置
3. 新規作成・編集・削除ボタンは `#headerExtras` スロットに配置
4. プライマリボタンは `bg-indigo-600`（`bg-blue-600` 禁止）
5. 戻るラベルは `← 〇〇に戻る`（遷移先明記）
6. コンテンツカード内に戻るボタン・アクションボタンを置かない

---

## フェーズ1：Coordinator 残修正

### R-01 案件一覧（Index）の新規作成ボタンを `#headerExtras` へ移動

**対象:** `Coordinator/ProjectJobs/Index.vue`

**現状の問題:**
- 新規作成ボタン（`bg-blue-600`）がカードコンテンツ内の `justify-between` 行にある
- 検索ボタンも `bg-blue-600`

**修正内容:**
1. `#header` スロットを追加（現状は h2 なし）:
   ```vue
   <template #header>
     <h2 class="text-xl font-semibold leading-tight text-gray-800">案件一覧</h2>
   </template>
   ```
2. `#headerExtras` スロットを追加し、新規作成ボタンを移動:
   ```vue
   <template #headerExtras>
     <div class="flex items-center gap-2">
       <Link :href="route('coordinator.project_jobs.bulk_create.index')"
         class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
       >テンプレートから一括作成</Link>
       <Link :href="route('coordinator.project_jobs.create')"
         class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
       >新規作成</Link>
     </div>
   </template>
   ```
3. カード内の `mb-6 flex items-center justify-between` 見出し行を削除
4. 検索ボタン `bg-blue-600` → `bg-indigo-600`

---

### R-02 案件作成（Create）の `#header` 修正・ボタン整理

**対象:** `Coordinator/ProjectJobs/Create.vue`

**現状の問題:**
- `#header` の `h2` が「【進行管理】XXXさんのページ」（ナビ共通文言）になっている
- 実際のページ見出し「プロジェクトジョブ作成」がカード内の `h1` に入っている
- 戻るボタンがない
- フォーム内保存ボタンが `bg-blue-600`、カードの外にある

**修正内容:**
1. `#header` を修正:
   ```vue
   <template #header>
     <div class="flex items-center gap-3">
       <Link :href="route('coordinator.project_jobs.index')"
         class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
       >← 案件一覧に戻る</Link>
       <h2 class="text-xl font-semibold leading-tight text-gray-800">案件作成</h2>
     </div>
   </template>
   ```
2. カード内の `<h1 class="mb-6 text-2xl font-bold">プロジェクトジョブ作成</h1>` を削除
3. ページ最下部のフォームボタン行:
   - 「一覧へ」ボタン（現状）→ `Link` + `キャンセル` スタイル（`bg-gray-100`）に変更
   - 「保存」ボタン `bg-blue-600` → `bg-indigo-600`
   - 配置: `mt-6 flex justify-end gap-3` でキャンセル左・保存右

---

### R-03 案件編集（Edit）の `#header` 修正・戻るボタン移動

**対象:** `Coordinator/ProjectJobs/Edit.vue`

**現状の問題:**
- `#header` が「【進行管理】XXXさんのページ」
- 戻るボタン「一覧へ戻る」がフォーム内 (`line 186`)
  - クラス: `rounded bg-gray-200 px-4 py-2` → サイズ・ラベル不統一
- 保存ボタン `bg-orange-500`（プライマリは indigo に統一）

**修正内容:**
1. `#header` を修正（Create と同様に戻るボタン付き）:
   ```vue
   <template #header>
     <div class="flex items-center gap-3">
       <Link :href="route('coordinator.project_jobs.index')"
         class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
       >← 案件一覧に戻る</Link>
       <h2 class="text-xl font-semibold leading-tight text-gray-800">案件編集</h2>
     </div>
   </template>
   ```
2. カード内の `<h1>案件編集</h1>`（存在する場合）を削除
3. フォーム内戻るボタン（line 186 付近）を削除
4. 保存ボタン `bg-orange-500` → `bg-indigo-600`（フォーム下部右に配置）
5. フォームボタン行: `mt-6 flex justify-end gap-3` にキャンセルリンク左・保存右
6. **カード幅:** `rounded bg-white p-6 shadow` → `mx-auto max-w-2xl rounded bg-white p-6 shadow`（Edit はフォーム系のため）

---

### R-04 ジョブ割り当て一覧（JobAssign/Index）の検索ボタン色修正

**対象:** `Coordinator/ProjectJobs/JobAssign/Index.vue`

**現状の問題:**
- 検索ボタン `bg-blue-600` → `bg-indigo-600` に変更
- `#header` に見出しはあるが「【進行管理】XXXさんのページ」。実際の見出し「ジョブ割り当て一覧: 〇〇」はカード内の `h1`

**修正内容:**
0. **カード幅はそのまま維持**（Index/Show 系のため `rounded bg-white p-6 shadow`、`mx-auto max-w-*` は追加しない）
1. 検索・絞り込みボタン `bg-blue-600` → `bg-indigo-600`
2. `#header` の h2 を案件名付きに変更:
   ```vue
   <template #header>
     <div class="flex items-center gap-3">
       <Link :href="route('coordinator.project_jobs.show', { projectJob: projectJob.id })"
         class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
       >← 案件詳細に戻る</Link>
       <h2 class="text-xl font-semibold leading-tight text-gray-800">ジョブ割り当て一覧</h2>
     </div>
   </template>
   ```
3. カード内の `<h1 class="mb-4 text-2xl font-bold">ジョブ割り当て一覧：{{ projectJob.title }}</h1>` を削除

---

### R-05 ジョブ割り当て詳細（JobAssign/Show）の戻るボタン修正

**対象:** `Coordinator/ProjectJobs/JobAssign/Show.vue`

**現状の問題:**
- `#header` スロットはあるが h2 のみ（戻るボタンなし）
- 戻るボタン（`route(...) px-4 py-2`）がコンテンツカード内 (line 20)
- ラベルが「戻る」のみ

**修正内容:**
1. `#header` に戻るボタンを追加:
   ```vue
   <template #header>
     <div class="flex items-center gap-3">
       <Link :href="route('coordinator.project_jobs.assignments.index', { projectJob: projectJob.id })"
         class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
       >← 割り当て一覧に戻る</Link>
       <h2 class="text-xl font-semibold leading-tight text-gray-800">ジョブ割り当て詳細</h2>
     </div>
   </template>
   ```
2. カード内の戻るボタン（line 20 付近）を削除

---

### R-06 ジョブ割り当て案件選択（JobAssign/SelectProject）の戻るボタン修正

**対象:** `Coordinator/ProjectJobs/JobAssign/SelectProject.vue`

**現状の問題:**
- `#header` スロットはあるが戻るボタンが `#header` 外（カード内、line 43付近）
- ラベルが「戻る」のみ
- カードが `mx-auto max-w-lg rounded bg-white p-6 shadow`（Show 系のため `mx-auto max-w-lg` を削除）

**修正内容:**
1. `#header` に戻るボタンを追加:
   ```vue
   <template #header>
     <div class="flex items-center gap-3">
       <Link :href="route('coordinator.jobbox')"
         class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
       >← ジョブ一覧に戻る</Link>
       <h2 class="text-xl font-semibold leading-tight text-gray-800">案件選択</h2>
     </div>
   </template>
   ```
2. カード内の戻るボタンを削除
3. **カード幅:** `mx-auto max-w-lg rounded bg-white p-6 shadow` → `rounded bg-white p-6 shadow`（Select 系は Show 扱い）

---

### R-07 外注先ページ群（Subcontractors）の戻るボタンスタイル統一

**対象:** 3ファイル
- `Coordinator/Subcontractors/Show.vue`
- `Coordinator/Subcontractors/Create.vue`
- `Coordinator/Subcontractors/Edit.vue`

**現状の問題:**
- 3ファイルとも戻るボタンが `text-gray-600 hover:text-gray-900`（テキストリンクスタイル）
- `#header` 内に配置されているが、クラスが統一されていない
- Show.vue: `class="text-gray-600 hover:text-gray-900 text-sm"` → ボタンスタイルに
- Create.vue: 戻るボタンが `#header` 外のカード内（`<div>← 一覧に戻る</div>` を `Link` ボタンスタイルに）
- Edit.vue: 同様

**修正内容（3ファイル共通）:**
```vue
<!-- 修正後の戻るボタン（#header 内） -->
<Link :href="route('...')"
  class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
>← 〇〇に戻る</Link>
```
- Show.vue: `← 外注先一覧に戻る` → `route('coordinator.subcontractors.index')`（**カード幅: そのまま**、Show 系も mx-auto max-w-2xl を適用）
- Create.vue: `← 外注先一覧に戻る` → `route('coordinator.subcontractors.index')`（カード内から `#header` へ移動。**カード幅:** `mx-auto max-w-2xl rounded bg-white p-6 shadow` に変更）
- Edit.vue: `← 外注先詳細に戻る` → `route('coordinator.subcontractors.show', { subcontractor: props.subcontractor.id })`（カード内から `#header` へ移動。**カード幅:** `mx-auto max-w-2xl rounded bg-white p-6 shadow` に変更）

---

### R-08 チームメンバー管理ページのボタン色修正

**対象:** 2ファイル
- `Coordinator/ProjectTeamMembers/Create.vue`
- `Coordinator/ProjectTeamMembers/Index.vue`

**現状の問題:**
- `bg-blue-600` が複数箇所に残っている

**修正内容:**
- `bg-blue-600` → `bg-indigo-600`（保存・追加・検索ボタン）
- `hover:bg-blue-700` → `hover:bg-indigo-700`
- それ以外のボタン（キャンセル等）は `bg-gray-100` / `bg-gray-200`
- **Create.vue カード幅:** `rounded bg-white p-6 shadow` → `mx-auto max-w-2xl rounded bg-white p-6 shadow`（Create はフォーム系）
- **Index.vue カード幅:** そのまま（`rounded bg-white p-6 shadow`、Index 系は幅制限なし）

---

### R-09 スケジュールコメント作成ページの修正

**対象:** `Coordinator/ProjectSchedules/Comments/Create.vue`

**現状の問題:**
- 戻るボタンが `button @click="goBack"` で `bg-gray-300`（`bg-gray-200` に統一）
- `goBack()` 関数が `router.back()` 相当
- 保存ボタン `bg-blue-600`
- `mx-auto max-w-2xl` 重複ラップ

**修正内容:**
1. 戻るボタンを `Link + route()` に変更（`#header` 内に移動）:
   - 遷移先: `route('coordinator.project_schedules.show', { project: projectId })` 等（実装時にルート名を確認）
2. **カード幅:** `mx-auto max-w-2xl rounded bg-white p-6 shadow` → `mx-auto max-w-2xl rounded bg-white p-6 shadow`（Create はフォーム系、max-w-3xl に統一）
3. 保存ボタン `bg-blue-600` → `bg-indigo-600`

---

## フェーズ2：User / JobBox / Events

### R-10 イベント詳細（Events/Show）への `#header` スロット追加

**対象:** `Events/Show.vue`

**現状の問題:**
- `#header` スロットなし（N-10でボタン修正済みだが `#header` 未対応）
- 戻るボタンがコンテンツ内カードの `border-t bg-gray-50` 行に置かれている（line 220付近）
- ラベルが「戻る」のみ
- 編集・削除ボタンも同じカード内にあり `bg-blue-600` 等が混在
- 外側ラッパーが `mx-auto max-w-2xl space-y-4`（Show 系のため `mx-auto max-w-2xl` を削除）

**修正内容:**
1. `#header` スロットを追加:
   ```vue
   <template #header>
     <div class="flex items-center gap-3">
       <button @click="goBack"
         class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
       >← 戻る</button>
       <h2 class="text-xl font-semibold leading-tight text-gray-800">予定詳細</h2>
     </div>
   </template>
   ```
   ※ 遷移元が不定のため `goBack()` 維持。ラベルは「← 戻る」（遷移先不明のため例外許容）
2. `#headerExtras` に編集・削除ボタンを移動:
   ```vue
   <template #headerExtras>
     <div class="flex items-center gap-2">
       <!-- hide_edit=false かつ 権限あり の場合のみ表示 -->
       <Link v-if="!props.hide_edit && !props.view_as_coordinator" :href="route('events.edit', { event: props.event.id })"
         class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
       >編集</Link>
       <button v-if="!props.hide_edit && !props.view_as_coordinator" @click="deleteEvent"
         class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
       >削除</button>
     </div>
   </template>
   ```
3. カード内ボタン行（border-t bg-gray-50 の行）から戻る・編集・削除ボタンを削除
4. `bg-blue-600` → `bg-indigo-600`
5. **カード幅:** 外側 `mx-auto max-w-2xl space-y-4` → `space-y-4`（mx-auto max-w-2xl を維持または追加）

---

### R-11 イベント編集（Events/Edit）への `#header` スロット追加

**対象:** `Events/Edit.vue`

**現状の問題:**
- `#header` スロットなし
- カードが `mx-auto max-w-2xl rounded bg-white p-6 shadow`（Edit 系のため `max-w-3xl` に変更）

**修正内容:**
1. `#header` スロット追加:
   ```vue
   <template #header>
     <div class="flex items-center gap-3">
       <button @click="goBack"
         class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
       >← 戻る</button>
       <h2 class="text-xl font-semibold leading-tight text-gray-800">予定編集</h2>
     </div>
   </template>
   ```
2. **カード幅:** `mx-auto max-w-2xl rounded bg-white p-6 shadow` → `mx-auto max-w-2xl rounded bg-white p-6 shadow`（Edit はフォーム系、max-w-3xl に統一）
3. フォームボタン: キャンセル左・保存右に統一

---

### R-12 イベント作成（Events/Create）への `#header` スロット追加

**対象:** `Events/Create.vue`

**現状の問題:**
- `#header` スロットなし
- カードが `mx-auto max-w-2xl rounded bg-white p-6 shadow`（Create 系のため幅はそのまま正しい）
- `bg-blue-600` 複数箇所

**修正内容:**
1. `#header` スロット追加:
   ```vue
   <template #header>
     <div class="flex items-center gap-3">
       <button @click="goBack"
         class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
       >← 戻る</button>
       <h2 class="text-xl font-semibold leading-tight text-gray-800">予定作成</h2>
     </div>
   </template>
   ```
2. **カード幅:** `mx-auto max-w-2xl rounded bg-white p-6 shadow` → そのまま維持（Create 系として正しい）
3. `bg-blue-600` → `bg-indigo-600`

---

### R-13 ジョブ作成（Events/Create_Job）への `#header` スロット追加

**対象:** `Events/Create_Job.vue`

**現状の問題:**
- `#header` スロットなし
- カードが `mx-auto max-w-2xl rounded bg-white p-6 shadow`（Create 系のため幅はそのまま正しい）
- `bg-blue-600` 複数箇所

**修正内容:**
1. `#header` スロット追加:
   ```vue
   <template #header>
     <div class="flex items-center gap-3">
       <button @click="goBack"
         class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
       >← 戻る</button>
       <h2 class="text-xl font-semibold leading-tight text-gray-800">ジョブ作成</h2>
     </div>
   </template>
   ```
2. **カード幅:** `mx-auto max-w-2xl rounded bg-white p-6 shadow` → そのまま維持（Create 系として正しい）
3. `bg-blue-600` → `bg-indigo-600`

---

### R-14 ジョブ詳細（JobBox/Show）の戻るボタンを `#header` へ移動

**対象:** `JobBox/Show.vue`

**現状の問題:**
- `#header` スロットはあるが h2 のみ（戻るボタンなし）
- 戻るボタン（`routeBack()`）がカードコンテンツの `border-t bg-gray-50 px-5 py-3` 行にある
- ラベルが「戻る」のみ
- 外側ラッパーが `mx-auto max-w-2xl space-y-4`（Show 系のため `mx-auto max-w-2xl` を削除）
- 編集・削除ボタンも同行にある（`bg-yellow-500`, `bg-red-500`）

**修正内容:**
1. `#header` に戻るボタンを追加:
   ```vue
   <template #header>
     <div class="flex items-center gap-3">
       <Link :href="routeBack()"
         class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
       >← 戻る</Link>
       <h2 class="text-xl font-semibold leading-tight text-gray-800">ジョブ割り当て — メッセージ表示</h2>
     </div>
   </template>
   ```
2. `#headerExtras` に編集・削除を移動:
   ```vue
   <template #headerExtras>
     <div v-if="isPrivilegedUser" class="flex items-center gap-2">
       <Link :href="assignmentEditHref"
         class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
       >編集</Link>
       <button v-if="canEditDelete" @click="deleteMessage"
         class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
       >削除</button>
     </div>
   </template>
   ```
3. **カード幅:** `mx-auto max-w-2xl space-y-4` → `space-y-4`（mx-auto max-w-2xl を維持または追加）
4. カード内のボタン行（border-t行）から戻る・編集・削除ボタンを削除

---

### R-15 ジョブスケジュール（JobBox/Schedule）の戻るボタン修正

**対象:** `JobBox/Schedule.vue`

**現状の問題:**
- `#header` スロットはあるが戻るボタンが `#header` 外のカード内
- ラベルが「戻る」のみ
- 外側ラッパーが `mx-auto max-w-2xl space-y-4`（Schedule はフォーム系のため `max-w-3xl` に変更）
- `backHref` は computed で動的生成（移動時も維持）

**修正内容:**
1. `#header` に戻るボタンを移動:
   ```vue
   <template #header>
     <div class="flex items-center gap-3">
       <Link :href="backHref"
         class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
       >← ジョブ詳細に戻る</Link>
       <h2 class="text-xl font-semibold leading-tight text-gray-800">スケジュール設定</h2>
     </div>
   </template>
   ```
2. カード内の戻るボタンを削除
3. **カード幅:** `mx-auto max-w-2xl space-y-4` → `mx-auto max-w-2xl space-y-4`（Schedule はフォーム系、max-w-3xl に統一）

---

### R-16 マイジョブ詳細（MyJobBox/Show）の戻るボタン修正

**対象:** `MyJobBox/Show.vue`

**現状の問題:**
- `#header` スロットはあるが戻るボタンが `#header` 外のカード内
- ラベルが「戻る」のみ
- 外側ラッパーが `mx-auto max-w-2xl space-y-4`（Show 系のため `mx-auto max-w-2xl` を削除）

**修正内容:**
1. `#header` に戻るボタンを移動:
   ```vue
   <template #header>
     <div class="flex items-center gap-3">
       <Link :href="routeBack()"
         class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
       >← 戻る</Link>
       <h2 class="text-xl font-semibold leading-tight text-gray-800">ジョブ割り当て — 詳細</h2>
     </div>
   </template>
   ```
2. **カード幅:** `mx-auto max-w-2xl space-y-4` → `space-y-4`（mx-auto max-w-2xl を維持または追加）

---

### R-17 ジョブ通知一覧（JobNotifications/Index）への `#header` スロット追加

**対象:** `JobNotifications/Index.vue`

**現状の問題:**
- `#header` スロットなし
- `bg-blue-600` 使用

**修正内容:**
1. `#header` スロット追加:
   ```vue
   <template #header>
     <h2 class="text-xl font-semibold leading-tight text-gray-800">ジョブ通知</h2>
   </template>
   ```
2. `bg-blue-600` → `bg-indigo-600`

---

## フェーズ3：Admin

### R-18 ユーザー一覧（Admin/Users/Index）のボタン修正

**対象:** `Admin/Users/Index.vue`

**現状の問題:**
- `bg-blue-600` がボタンで使われている（検索・絞り込みボタン）
- `#header` はあるが内容確認が必要（モーダル開くボタンが `bg-blue-600`）

**修正内容:**
1. `bg-blue-600` → `bg-indigo-600`（検索・絞り込みボタン）
2. `hover:bg-blue-700` → `hover:bg-indigo-700`

---

### R-19 チーム詳細（Admin/Teams/Show）の修正

**対象:** `Admin/Teams/Show.vue`

**現状の問題:**
- `mx-auto max-w-4xl` 重複ラップ
- 戻るボタンが `goBack()`（`window.history.back()` 相当）+ ラベル「一覧へ戻る」
- ボタンスタイル `rounded border px-4 py-2 text-sm`（戻るボタンスタイルに統一）
- `bg-blue-500` のボタンあり

**修正内容:**
1. `#header` に戻るボタンを追加（実装時ルート名確認）:
   ```vue
   <template #header>
     <div class="flex items-center gap-3">
       <Link :href="route('admin.teams.index')"
         class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
       >← チーム一覧に戻る</Link>
       <h2 class="text-xl font-semibold leading-tight text-gray-800">チーム詳細</h2>
     </div>
   </template>
   ```
2. **カード幅:** `mx-auto max-w-4xl rounded bg-white p-6 shadow` → `rounded bg-white p-6 shadow`（Show 系も mx-auto max-w-2xl を適用、mx-auto + max-w 両方削除）
3. カード内の「一覧へ戻る」ボタンを削除
4. `bg-blue-500` → `bg-indigo-600`

---

## フェーズ4：Leader / Diaries / Messages

### R-20 案件一覧（Leader/ProjectJobs/Index）の重複ラップ削除

**対象:** `Leader/ProjectJobs/Index.vue`

**現状の問題:**
- デフォルトスロット内に `mx-auto max-w-6xl rounded bg-white p-6 shadow` （`mx-auto` 重複）

**修正内容:**
- **カード幅:** `mx-auto max-w-6xl rounded bg-white p-6 shadow` → `rounded bg-white p-6 shadow`（Index 系は幅制限なし、mx-auto + max-w 両方削除）

---

### R-21 日報一覧・詳細（Diaries/Index, Show）の修正

**対象:** 2ファイル
- `Diaries/Index.vue`
- `Diaries/Show.vue`

**Diaries/Index.vue の問題:**
- `bg-blue-600` のボタンあり

**Diaries/Show.vue の問題:**
- 戻るボタンが `button @click="back"` でコンテンツ内（line 1186付近）
- 編集ボタン `bg-blue-600`
- `#header` スロットはある（line 1147）

**修正内容:**
- Index.vue: `bg-blue-600` → `bg-indigo-600`
- Show.vue:
  1. `#header` に戻るボタン追加（`back()` 関数は `window.history.back()` のため、具体的な遷移先 `route('diaries.index')` に変更）
  2. 編集ボタンを `#headerExtras` に移動: `bg-blue-600` → `bg-indigo-600`
  3. カード内の戻る・編集ボタン削除

---

### R-22 日報インタラクション一覧（Diaries/Interactions/Index）のボタン色修正

**対象:** `Diaries/Interactions/Index.vue`

**現状の問題:**
- `bg-blue-600` のボタンあり

**修正内容:**
- `bg-blue-600` → `bg-indigo-600`

---

### R-23 メッセージ詳細（Messages/Show）の戻るボタン修正

**対象:** `Messages/Show.vue`

**現状の問題:**
- 戻るボタンが `<a :href="route('messages.index')" class="text-sm text-blue-600 underline">← 一覧に戻る</a>` （テキストリンクスタイル）

**修正内容:**
- `#header` スロット内の `<a>` タグを `<Link>` ボタンスタイルに変更:
  ```vue
  <Link :href="route('messages.index')"
    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
  >← メッセージ一覧に戻る</Link>
  ```

---

## フェーズ5：ProofCoordinator / Proof / Prepress / Clerk

### R-24 校正コーディネーター発注先詳細（Dispatchers/Show）の戻るボタン修正

**対象:** `ProofCoordinator/Dispatchers/Show.vue`

**現状の問題:**
- 戻るボタンが `class="text-gray-600 hover:text-gray-900"` テキストリンクスタイル

**修正内容:**
- `#header` 内の戻るボタンをボタンスタイルに変更:
  ```vue
  <Link :href="route('proof_coordinator.dispatchers.index')"
    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
  >← 一覧に戻る</Link>
  ```

---

### R-25 校正コーディネーター割り当て詳細（Assignments/Show）の重複ラップ削除

**対象:** `ProofCoordinator/Assignments/Show.vue`

**現状の問題:**
- `mx-auto max-w-2xl space-y-4` 重複ラップ
- 戻るボタンを確認し `#header` に移動

**修正内容:**
1. `#header` に戻るボタンを追加（実装時にルート名・遷移先を確認）
2. **カード幅:** `mx-auto max-w-2xl space-y-4` → `space-y-4`（Show 系も mx-auto max-w-2xl を適用、mx-auto + max-w 両方削除）

---

## 作業ログ

| 日付 | フェーズ | ID | 内容 | 状態 |
|------|--------|-----|------|------|
| 2026-05-02 | — | — | 計画書・管理書・プロンプト作成 | 完了 |
