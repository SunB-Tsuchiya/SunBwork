# SunBWork レイアウトガイドライン
作成日: 2026-04-22

このドキュメントは、SunBWork プロジェクトの全ページに統一して適用すべき UI・レイアウトルールを定めます。
新規ページの作成・既存ページの修正時は必ずこのガイドラインに従ってください。

---

## 目次

1. [AppLayout の使い方](#1-applayout-の使い方)
2. [ボタン種別・デザイン定義](#2-ボタン種別デザイン定義)
3. [ボタン配置ルール](#3-ボタン配置ルール)
4. [表記統一](#4-表記統一)
5. [ページ構造テンプレート](#5-ページ構造テンプレート)
6. [共通コンポーネント仕様（L-02で実装予定）](#6-共通コンポーネント仕様)
7. [適用チェックリスト](#7-適用チェックリスト)

---

## 1. AppLayout の使い方

### スロット一覧

| スロット | 内容 | 使用例 |
|--------|------|--------|
| `#header` | ページ見出し + 戻るボタン | `<h2>` タグ、`Link` 戻るボタン |
| `#headerExtras` | ページ右上アクションボタン群 | 「新規作成」「編集」「削除」ボタン |
| `#tabs` | ページ内タブナビゲーション | タブ切替UI |
| デフォルト | ページ本体コンテンツ | カード・テーブル等 |

### 基本構造

```vue
<AppLayout title="ページタイトル">
  <template #header>
    <div class="flex items-center gap-3">
      <!-- 戻るボタン（詳細・編集ページのみ） -->
      <Link
        :href="route('coordinator.xxx.index')"
        class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
      >← 〇〇に戻る</Link>
      <h2 class="text-xl font-semibold leading-tight text-gray-800">ページ見出し</h2>
    </div>
  </template>

  <template #headerExtras>
    <!-- 新規作成・編集・削除ボタン -->
    <Link :href="route('coordinator.xxx.create')" class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
      新規作成
    </Link>
  </template>

  <!-- ページ本体 -->
  <div class="rounded bg-white p-6 shadow">
    <!-- コンテンツ -->
  </div>
</AppLayout>
```

### NG パターン（禁止）

```vue
<!-- NG: main タグを使わない -->
<main class="py-12">...</main>

<!-- NG: py-12 / max-w-7xl / mx-auto の重複ラップ（AppLayout が提供済み） -->
<div class="py-12">
  <div class="mx-auto max-w-7xl px-4">...</div>
</div>

<!-- NG: ToastUnified の重複配置（AppLayout 内にグローバル配置済み） -->
<ToastUnified />
```

---

## 2. ボタン種別・デザイン定義

### 種別一覧

| 種別 | 用途 | Tailwind クラス |
|------|------|----------------|
| **プライマリ** | 新規作成・保存・送信（最重要アクション） | `rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700` |
| **セカンダリ** | 編集・複製・補助的アクション | `rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50` |
| **危険** | 削除・取り消し（確認ダイアログ経由） | `rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700` |
| **戻る** | 前ページ・一覧への遷移 | `rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300` |
| **キャンセル** | フォーム・モーダル内での中止 | `rounded bg-gray-100 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-200` |
| **情報系** | 詳細表示・遷移系（危険ではない補助） | `rounded border border-blue-300 bg-white px-4 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50` |
| **無効（disabled）** | 操作不可状態 | `rounded bg-gray-300 px-4 py-2 text-sm font-medium text-gray-400 cursor-not-allowed` |

### サイズ変形

| サイズ | 用途 | padding の調整 |
|-------|------|--------------|
| 標準 | ページアクション | `px-4 py-2` |
| 小（sm） | テーブル内・ツールバー内 | `px-3 py-1.5` |
| 大（lg） | フォーム送信ボタン | `px-6 py-3` |

---

## 3. ボタン配置ルール

### 「戻る」系ボタン

- **必ず `#header` スロット内**、見出しテキストの**左隣**に配置
- 一覧ページ（index）には不要
- ラベルは「〇〇に戻る」と遷移先を明記（「戻る」だけはNG）

```vue
<template #header>
  <div class="flex items-center gap-3">
    <Link :href="route('coordinator.project_jobs.index')"
      class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
    >← 案件一覧に戻る</Link>
    <h2 class="text-xl font-semibold leading-tight text-gray-800">案件詳細</h2>
  </div>
</template>
```

### 「新規作成」「編集」「削除」系ボタン

- **`#headerExtras` スロット**に配置（ページ右上）
- 複数ある場合は左から「セカンダリ → プライマリ → 危険」の順

```vue
<template #headerExtras>
  <div class="flex items-center gap-2">
    <button type="button" class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
      複製
    </button>
    <Link :href="route('coordinator.xxx.edit', { id: item.id })"
      class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
    >編集</Link>
    <button type="button" @click="confirmDelete"
      class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
    >削除</button>
  </div>
</template>
```

### フォーム内ボタン

- 「キャンセル（左）」「保存（右）」の順で横並び
- `justify-end` で右寄せ

```vue
<div class="mt-6 flex justify-end gap-3">
  <button type="button" @click="cancel"
    class="rounded bg-gray-100 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-200"
  >キャンセル</button>
  <button type="submit"
    class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
  >保存</button>
</div>
```

### モーダル内ボタン

- フォームと同様「キャンセル（左）」「実行（右）」の順
- 削除モーダルは「キャンセル（左）」「削除（右・danger）」の順

---

## 4. 表記統一

### ボタンラベル

| 現状（統一前・使用禁止） | 統一後 |
|------------------------|--------|
| 戻る | 〇〇に戻る（遷移先を明記） |
| 一覧へ | 一覧に戻る |
| キャンセル（ページ遷移用） | 〇〇に戻る（遷移先を明記） |
| プロジェクト詳細に戻る | 案件詳細に戻る |
| ← 戻る | ← 〇〇に戻る |

### ページ見出し・タイトル

| 現状 | 統一後 |
|------|--------|
| プロジェクト詳細 | 案件詳細 |
| プロジェクト一覧 | 案件一覧 |
| JobBox — ジョブ関連メッセージ | ジョブ一覧（JobBox） |

### 汎用表記

- 案件 = ProjectJob（「プロジェクト」はUI上では「案件」と表記）
- ジョブ = ProjectJobAssignment（「割り当て」「割当」は混在を避け「ジョブ」に統一）

---

## 5. ページ構造テンプレート

### 一覧ページ（Index）

```vue
<AppLayout title="〇〇一覧">
  <template #header>
    <h2 class="text-xl font-semibold leading-tight text-gray-800">〇〇一覧</h2>
  </template>

  <template #headerExtras>
    <Link :href="route('coordinator.xxx.create')"
      class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
    >新規作成</Link>
  </template>

  <div class="rounded bg-white p-6 shadow">
    <!-- 検索・フィルター -->
    <div class="mb-4 flex items-center gap-3">
      ...
    </div>
    <!-- テーブル -->
    <table class="w-full text-sm">
      ...
    </table>
  </div>
</AppLayout>
```

### 詳細ページ（Show）

```vue
<AppLayout title="〇〇詳細">
  <template #header>
    <div class="flex items-center gap-3">
      <Link :href="route('coordinator.xxx.index')"
        class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
      >← 〇〇一覧に戻る</Link>
      <h2 class="text-xl font-semibold leading-tight text-gray-800">〇〇詳細</h2>
    </div>
  </template>

  <template #headerExtras>
    <div class="flex items-center gap-2">
      <Link :href="route('coordinator.xxx.edit', { id: item.id })"
        class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
      >編集</Link>
    </div>
  </template>

  <div class="rounded bg-white p-6 shadow">
    ...
  </div>
</AppLayout>
```

### 作成・編集ページ（Create / Edit）

```vue
<AppLayout title="〇〇作成">
  <template #header>
    <div class="flex items-center gap-3">
      <Link :href="route('coordinator.xxx.index')"
        class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
      >← 〇〇一覧に戻る</Link>
      <h2 class="text-xl font-semibold leading-tight text-gray-800">〇〇作成</h2>
    </div>
  </template>

  <div class="rounded bg-white p-6 shadow">
    <form @submit.prevent="submit">
      <!-- フォーム内容 -->
      <div class="mt-6 flex justify-end gap-3">
        <Link :href="route('coordinator.xxx.index')"
          class="rounded bg-gray-100 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-200"
        >キャンセル</Link>
        <button type="submit"
          class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
        >保存</button>
      </div>
    </form>
  </div>
</AppLayout>
```

---

## 6. 共通コンポーネント仕様

> L-02 の実装フェーズで作成予定。以下は仕様のみ定義。

### `BackButton.vue`

「〇〇に戻る」ボタンの共通コンポーネント。

```vue
<!-- 使い方 -->
<BackButton :href="route('coordinator.project_jobs.index')">案件一覧に戻る</BackButton>
```

**Props:**
| prop | 型 | 説明 |
|------|----|------|
| `href` | `string` | 遷移先URL（Ziggy の `route()` を使用） |

**スロット:** デフォルトスロットにラベルテキスト（「← 」は自動付与）

---

### `PageActions.vue`

ページ右上アクションボタン群のラッパー。`#headerExtras` スロットで使用。

```vue
<!-- 使い方 -->
<template #headerExtras>
  <PageActions>
    <button ...>編集</button>
    <button ...>削除</button>
  </PageActions>
</template>
```

デフォルトスロット内のボタンを `flex items-center gap-2` で横並びにする。

---

## 7. 適用チェックリスト

各ページの修正時に以下を確認する：

### レイアウト
- [ ] `<main>` タグを使っていないか
- [ ] `py-12` / `max-w-7xl` / `mx-auto` を重複ラップしていないか
- [ ] `ToastUnified` をページ内で重複させていないか

### 戻るボタン
- [ ] `#header` スロット内・見出しの左隣に配置されているか
- [ ] ラベルが「← 〇〇に戻る」の形式になっているか（遷移先が明記されているか）
- [ ] 一覧ページ（Index）では戻るボタンがないか（不要）

### アクションボタン
- [ ] 新規作成・編集・削除ボタンが `#headerExtras` スロットにあるか
- [ ] ボタンの並び順が「セカンダリ → プライマリ → 危険」になっているか

### フォーム
- [ ] フォームのボタンが「キャンセル（左）」「保存（右）」の順か
- [ ] `justify-end` で右寄せになっているか

### 表記
- [ ] 「プロジェクト詳細」ではなく「案件詳細」になっているか
- [ ] 「戻る」だけでなく「〇〇に戻る」と遷移先が明記されているか
- [ ] 「一覧へ」ではなく「一覧に戻る」になっているか

---

## 8. L-02 適用対象ページ一覧

### 優先度 高（Phase 2 で対応）

| ページ | ファイルパス | 主な修正内容 |
|--------|------------|------------|
| 案件詳細 | `Coordinator/ProjectJobs/Show.vue` | 戻るボタン位置・ボタン種別統一 |
| テンプレート編集 | `Coordinator/ProgressTemplates/Edit.vue` | 戻るボタン追加（現状なし） |
| 進行管理表 | `Coordinator/ProgressSheets/Show.vue` | 戻るボタンを `#header` に移動 |
| カレンダー | `Coordinator/ProjectSchedules/Calendar.vue` | 戻るボタン確認・表記統一 |
| ジョブ一覧（Coordinator） | `Coordinator/JobBox/Index.vue` | 戻るボタン・ボタン種別統一 |
| ジョブ一覧（User） | `JobBox/Index.vue` | 戻るボタン・ボタン種別統一 |

### 優先度 中（順次対応）

| ページ | ファイルパス | 主な修正内容 |
|--------|------------|------------|
| 案件一覧 | `Coordinator/ProjectJobs/Index.vue` | ボタン種別統一 |
| ジョブ割り当て | `Coordinator/ProjectJobAssignments/` | 戻るボタン・表記統一 |
| ユーザー管理 | `Admin/Users/` | ボタン種別・表記統一 |
| テンプレート一覧 | `Coordinator/ProgressTemplates/Index.vue` | ボタン種別統一 |

---

*このドキュメントは L-02 実施後、コンポーネント化が完了した時点で更新予定。*
