# SunBWork レイアウト統一仕様書 V2
作成日: 2026-05-02

> **この文書の目的:**
> Coordinator ロールで確立したレイアウトパターンを基準に、サイト全体のレイアウト・ボタン・戻るナビゲーションを統一するための完全仕様書。
> 既存の LAYOUT_GUIDELINES.md (L-01) を引き継ぎ、コードサンプルと全ページの現状・対応方針を追加した最新版。

---

## 目次

1. [AppLayout の正しい使い方](#1-applayout-の正しい使い方)
2. [ボタン種別・デザイン定義（確定版）](#2-ボタン種別デザイン定義)
3. [ボタン配置ルール](#3-ボタン配置ルール)
4. [戻るボタンの実装方針](#4-戻るボタンの実装方針)
5. [コンテナ幅ルール](#5-コンテナ幅ルール)
6. [ページ構造テンプレート（コピー用）](#6-ページ構造テンプレート)
7. [表記統一ルール](#7-表記統一ルール)
8. [適用チェックリスト](#8-適用チェックリスト)
9. [全ページ現状一覧と対応方針](#9-全ページ現状一覧と対応方針)

---

## 1. AppLayout の正しい使い方

### 1-1. スロット一覧

| スロット | 役割 | 使用ルール |
|--------|------|-----------|
| `#header` | ページ見出し（＋戻るボタン） | **全ページ必須**。一覧ページは見出しのみ、詳細・編集は戻るボタン付き |
| `#headerExtras` | ページ右上アクションボタン | 新規作成・編集・削除ボタンを置く。**コンテンツ内には置かない** |
| `#tabs` | ページ内タブナビゲーション | 複数ロールで使われる汎用ページのみ |
| デフォルト | ページ本体コンテンツ | `<div class="rounded bg-white p-6 shadow">` で包む |

### 1-2. AppLayout が提供済みのもの（重複させない）

AppLayout は内部で以下を提供している:
- `py-12` の上下余白
- `max-w-7xl mx-auto sm:px-6 lg:px-8` の最大幅ラッパー
- グローバル `ToastUnified`

**NG パターン（絶対禁止）:**
```vue
<!-- NG: main タグ -->
<main class="py-12">...</main>

<!-- NG: py-12 / max-w-7xl の重複ラップ -->
<div class="py-12">
  <div class="mx-auto max-w-7xl px-4">...</div>
</div>

<!-- NG: ToastUnified 重複配置 -->
<ToastUnified />

<!-- NG: #header スロットなし（AppLayout を使っているのに見出しなし）-->
<AppLayout title="ページタイトル">
  <!-- template #header がない -->
  <div class="rounded bg-white p-6 shadow">...
```

---

## 2. ボタン種別・デザイン定義

### 確定 Tailwind クラス一覧

| 種別 | 主な用途 | Tailwind クラス |
|------|---------|----------------|
| **プライマリ** | 新規作成・保存・送信 | `rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700` |
| **セカンダリ** | 編集・複製・補助アクション | `rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50` |
| **危険** | 削除・取り消し | `rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700` |
| **戻る** | 前ページ・一覧への遷移 | `rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300` |
| **キャンセル** | フォーム・モーダル内の中止 | `rounded bg-gray-100 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-200` |
| **情報系** | 詳細表示・補助遷移 | `rounded border border-blue-300 bg-white px-4 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50` |
| **無効** | 操作不可状態 | `rounded bg-gray-300 px-4 py-2 text-sm font-medium text-gray-400 cursor-not-allowed` |

### 注意: 現状の不統一

以下のクラスは **使用禁止**（既存コードに残っているが新規作成・修正時は使わないこと）:
- `bg-blue-600` → プライマリは `bg-indigo-600` に統一
- `border-green-600 text-green-700` → セカンダリは `border-gray-300` に統一
- `bg-yellow-600`（編集ボタン）→ `bg-indigo-600` または `border border-gray-300 bg-white` に統一
- `rounded bg-gray-300` + `goBack()` → 戻るボタンは `Link` + `route()` が基本

### サイズ変形

| サイズ | 用途 | padding |
|-------|------|---------|
| 標準 | ページアクション全般 | `px-4 py-2` |
| 小（sm） | #header 内戻るボタン、テーブル内 | `px-3 py-1.5` |
| 大（lg） | フォームの主要送信ボタン | `px-6 py-3` |

---

## 3. ボタン配置ルール

### 3-1. 「戻る」ボタン

- **配置:** 必ず `#header` スロット内、`<h2>` の**左隣**
- **不要なページ:** 一覧ページ（Index）。戻るボタンがあるとUXが悪い
- **ラベル形式:** `← 〇〇に戻る`（遷移先を必ず明記。「戻る」だけはNG）

```vue
<template #header>
  <div class="flex items-center gap-3">
    <Link
      :href="route('coordinator.project_jobs.index')"
      class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
    >← 案件一覧に戻る</Link>
    <h2 class="text-xl font-semibold leading-tight text-gray-800">案件詳細</h2>
  </div>
</template>
```

### 3-2. 「新規作成」「編集」「削除」ボタン

- **配置:** 必ず `#headerExtras` スロット（ページ右上）
- コンテンツカードの内側・見出しの右隣 → NG（既存コードに残っているが修正対象）
- 複数ボタンの並び順: 左から「セカンダリ → プライマリ → 危険」

```vue
<template #headerExtras>
  <div class="flex items-center gap-2">
    <button type="button"
      class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
    >複製</button>
    <Link :href="route('coordinator.xxx.edit', { id: item.id })"
      class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
    >編集</Link>
    <button type="button" @click="confirmDelete"
      class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
    >削除</button>
  </div>
</template>
```

### 3-3. フォーム内ボタン

「キャンセル（左）」→「保存（右）」の順で右寄せ:

```vue
<div class="mt-6 flex justify-end gap-3">
  <Link :href="route('coordinator.xxx.index')"
    class="rounded bg-gray-100 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-200"
  >キャンセル</Link>
  <button type="submit"
    class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
  >保存</button>
</div>
```

### 3-4. モーダル内ボタン

通常: 「キャンセル（左）」→「実行（右）」
削除確認: 「キャンセル（左）」→「削除（右・danger）」

---

## 4. 戻るボタンの実装方針

### 4-1. 基本: Link + route() を使う（最優先）

遷移先が確定している場合は `<Link>` コンポーネントと `route()` を使う:

```vue
<Link
  :href="route('coordinator.project_jobs.index')"
  class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
>← 案件一覧に戻る</Link>
```

### 4-2. タブ付き遷移: クエリパラメータで遷移先タブを指定

```vue
<!-- 進行管理表から案件詳細の「進行管理表タブ」に戻る例 -->
<Link
  :href="route('coordinator.project_jobs.show', { projectJob: projectJob.id }) + '?tab=progress'"
  class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
>← 案件詳細に戻る</Link>
```

### 4-3. 動的な遷移先: 関数で URL を生成

遷移先がコンテキスト（prop）によって変わる場合:

```vue
<script setup>
function routeBack() {
  if (props.projectJob?.id) {
    return route('coordinator.project_jobs.show', { projectJob: props.projectJob.id });
  }
  return route('coordinator.jobbox');
}
</script>

<template>
  <Link :href="routeBack()"
    class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
  >← 戻る</Link>
</template>
```

### 4-4. 避けるべきパターン

```vue
<!-- NG: window.history.back() - SSR/直リンクで壊れる -->
<button @click="window.history.back()">戻る</button>

<!-- NG: router.back() - 同上 -->
<button @click="router.back()">戻る</button>

<!-- NG: ボタンではなくリンクスタイルのテキスト -->
<a :href="route('xxx.index')" class="text-gray-600 hover:text-gray-900">← 一覧に戻る</a>

<!-- NG: #header 以外の場所（コンテンツカード内）に戻るボタン -->
<div class="rounded bg-white p-6 shadow">
  <Link :href="route('xxx')" class="...">戻る</Link>  <!-- ← ここはNG -->
  <table>...
```

**例外:** `Events/Show.vue` のように「どこから来たかわからない」ページは `window.history.back()` が許容される（修正済み: `goBack()` 関数でラップ）。ただしこのパターンは新規ページでは使わない。

---

## 5. コンテナ幅ルール

### 5-1. ページ種別ごとの幅標準（確定版）

| ページ種別 | カードクラス | 基準ページ |
|-----------|------------|----------|
| **Index / 一覧系** | `rounded bg-white p-6 shadow`（フルワイド） | `Coordinator/ProjectJobs/Index.vue` |
| **Create / Edit / Show / Select 系** | `mx-auto max-w-2xl rounded bg-white p-6 shadow` | `Events/Create_Job.vue` |

```vue
<!-- Index 系（フルワイド） -->
<div class="rounded bg-white p-6 shadow">
  <!-- コンテンツ -->
</div>

<!-- Create / Edit / Show / Select 系（max-w-2xl に統一） -->
<div class="mx-auto max-w-2xl rounded bg-white p-6 shadow">
  <!-- コンテンツ / フォーム -->
</div>
```

**NG パターン:**
```vue
<!-- NG: Create/Edit/Show/Select なのに幅制限なし -->
<div class="rounded bg-white p-6 shadow"><form ...>

<!-- NG: Index なのに mx-auto max-w-* で幅制限 -->
<div class="mx-auto max-w-4xl rounded bg-white p-6 shadow">

<!-- NG: max-w-2xl 以外（旧コード、修正対象） -->
<div class="mx-auto max-w-3xl rounded bg-white p-6 shadow">
```

### 5-3. 特殊ページ: カード列挙型

詳細ページでセクションをカード形式で列挙する場合（Leader/ProjectJobs/Show.vue のパターン）:

```vue
<!-- OK: カード複数を space-y-4 で積む -->
<div class="space-y-4">
  <div class="rounded-xl border border-orange-100 bg-white shadow-sm">
    <div class="border-b border-orange-100 bg-orange-50 px-6 py-4">
      <h3 class="font-semibold text-orange-800">セクション見出し</h3>
    </div>
    <div class="p-6">...コンテンツ...</div>
  </div>
</div>
```

---

## 6. ページ構造テンプレート

### 6-1. 一覧ページ（Index）

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
    <!-- 検索・フィルター行 -->
    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center">
      <input v-model="q" @keyup.enter="search" placeholder="検索..."
        class="w-72 rounded border px-3 py-2 text-sm" />
      <button @click="search" class="rounded bg-indigo-600 px-3 py-2 text-sm text-white hover:bg-indigo-700">検索</button>
      <button @click="clearSearch" class="rounded border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">クリア</button>
    </div>
    <!-- テーブル -->
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left font-medium text-gray-500">項目名</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
          <tr v-for="item in items" :key="item.id" class="hover:bg-gray-50">
            <td class="px-4 py-3">{{ item.name }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</AppLayout>
```

### 6-2. 詳細ページ（Show）

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
      <Link :href="route('coordinator.xxx.edit', { xxx: item.id })"
        class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
      >編集</Link>
      <button type="button" @click="confirmDelete"
        class="rounded bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
      >削除</button>
    </div>
  </template>

  <div class="mx-auto max-w-2xl rounded bg-white p-6 shadow">
    <!-- コンテンツ -->
  </div>
</AppLayout>
```

### 6-3. 作成・編集ページ（Create / Edit）

> **カード幅:** `mx-auto max-w-2xl rounded bg-white p-6 shadow`（Index/Show とは異なる。基準: `Events/Create_Job.vue`）

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

  <div class="mx-auto max-w-2xl rounded bg-white p-6 shadow">
    <form @submit.prevent="submit">
      <!-- フォームフィールド -->
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

### 6-4. スティッキーヘッダー付きページ（Coordinator/ProjectJobs/Show.vue 準拠）

タブや大量のコンテンツがある詳細ページ向け:

```vue
<AppLayout title="案件詳細">
  <template #header>
    <div class="flex items-center gap-3">
      <Link :href="route('coordinator.project_jobs.index')"
        class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300"
      >← 案件一覧に戻る</Link>
      <h2 class="text-xl font-semibold leading-tight text-gray-800">
        【進行管理】{{ $page.props.auth.user.name }}さんのページ
      </h2>
    </div>
  </template>

  <!-- スティッキーヘッダー（タブ・タイトル行含む） -->
  <div class="sticky top-0 z-20 rounded-t bg-white px-6 pt-6 pb-0 shadow-md">
    <div class="mb-4">
      <h1 class="text-2xl font-bold text-gray-900">{{ item.title }}</h1>
    </div>
    <!-- タブ行 -->
    <div class="flex gap-1 border-b border-gray-200">
      <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
        :class="activeTab === tab.key
          ? 'border-b-2 border-indigo-500 px-4 py-2 text-sm font-semibold text-indigo-700'
          : 'px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700'"
      >{{ tab.label }}</button>
    </div>
  </div>

  <!-- タブコンテンツ -->
  <div class="rounded-b bg-white px-6 pt-4 pb-6 shadow">
    <div v-show="activeTab === 'overview'">...</div>
    <div v-show="activeTab === 'progress'">...</div>
  </div>
</AppLayout>
```

### 6-5. フォームフィールド標準スタイル

```vue
<!-- ラベル + テキスト入力 -->
<div>
  <label class="block text-sm font-medium text-gray-700">
    フィールド名 <span class="text-red-500">*</span>
  </label>
  <input v-model="form.field" type="text"
    class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none" />
  <p v-if="errors.field" class="mt-1 text-xs text-red-500">{{ errors.field }}</p>
</div>

<!-- セレクトボックス -->
<select v-model="form.field"
  class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none">
  <option value="">選択してください</option>
</select>

<!-- テキストエリア -->
<textarea v-model="form.field" rows="4"
  class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-indigo-400 focus:outline-none">
</textarea>
```

---

## 7. 表記統一ルール

### 7-1. ボタンラベル

| 現状（禁止） | 統一後 |
|-----------|--------|
| 戻る | ← 〇〇に戻る（遷移先を明記） |
| ← 一覧に戻る | ← 〇〇一覧に戻る（何の一覧かを明記） |
| 一覧へ | 一覧に戻る |
| 一覧へ戻る | 一覧に戻る（「へ」→「に」） |
| キャンセル（ページ遷移目的） | ← 〇〇に戻る に変更 |
| プロジェクト詳細に戻る | 案件詳細に戻る |
| 詳細に戻る | 〇〇詳細に戻る（何の詳細かを明記） |

### 7-2. ページ見出し・タイトル

| 現状 | 統一後 |
|------|--------|
| プロジェクト詳細 | 案件詳細 |
| プロジェクト一覧 | 案件一覧 |
| JobBox — ジョブ関連メッセージ | ジョブ一覧（JobBox） |
| ジョブ割り当て一覧 | ジョブ割り当て一覧（#header の h2 は個別ページ見出しに） |

### 7-3. 用語統一

| 用語 | 統一後 | 説明 |
|-----|--------|------|
| プロジェクト | 案件 | UI 上では常に「案件」 |
| 割り当て / 割当 | ジョブ | UI 表示は「ジョブ」に統一 |
| 確認済 / 確認済み | 確認済み | 表記揺れ統一（末尾「み」あり） |
| セット / セット済み | セット済み | 表記揺れ統一 |

### 7-4. `#header` の h2 と AppLayout の `title` prop

- `title` prop: ブラウザタブ・AppLayout 内部の識別用（英語可）
- `#header` の `<h2>`: ユーザーが見る画面上の見出し（日本語表記で統一）

---

## 8. 適用チェックリスト

各ページの新規作成・修正時に確認:

### レイアウト構造
- [ ] `<template #header>` スロットを使っているか
- [ ] `<main>` タグを使っていないか
- [ ] `py-12` / `max-w-7xl` / `mx-auto max-w-7xl` を重複ラップしていないか
- [ ] `ToastUnified` をページ内で重複させていないか
- [ ] **Index 系:** `<div class="rounded bg-white p-6 shadow">` を使っているか（`mx-auto max-w-*` なし、フルワイド）
- [ ] **Create / Edit / Show / Select 系:** `<div class="mx-auto max-w-2xl rounded bg-white p-6 shadow">` を使っているか

### 戻るボタン
- [ ] 詳細・編集・作成ページ: `#header` スロット内・見出しの左隣に配置されているか
- [ ] ラベルが `← 〇〇に戻る` の形式になっているか
- [ ] `window.history.back()` / `router.back()` を使っていないか（基本は Link + route()）
- [ ] 一覧ページ（Index）には戻るボタンがないか

### アクションボタン
- [ ] 新規作成・編集・削除ボタンが `#headerExtras` スロットにあるか
- [ ] コンテンツカード内の見出し横にアクションボタンを置いていないか
- [ ] ボタンの並び順が「セカンダリ → プライマリ → 危険」になっているか
- [ ] プライマリは `bg-indigo-600`（`bg-blue-600` ではない）か

### フォーム
- [ ] ボタンが「キャンセル（左）」「保存（右）」の順か
- [ ] `justify-end` で右寄せになっているか

### 表記
- [ ] 「プロジェクト詳細」ではなく「案件詳細」になっているか
- [ ] 「戻る」だけでなく「〇〇に戻る」と遷移先が明記されているか
- [ ] 「一覧へ」「一覧へ戻る」ではなく「一覧に戻る」になっているか

---

## 9. 全ページ現状一覧と対応方針

### 凡例

| 記号 | 意味 |
|------|------|
| ✅ 適用済み | ガイドライン準拠済み |
| ⚠️ 部分適用 | 一部修正が必要 |
| ❌ 未適用 | 全面的に修正が必要 |
| — 対象外 | Auth/エラー系など適用不要 |

---

### Coordinator ロール（最も整備されている）

| ファイル | 現状 | 主な問題点 |
|---------|------|-----------|
| `Coordinator/ProjectJobs/Show.vue` | ✅ 適用済み | ガイドライン基準ページ |
| `Coordinator/ProjectJobs/Index.vue` | ⚠️ 部分適用 | 新規作成ボタンがカード内（`bg-blue-600`）。`#headerExtras` に移動 + `bg-indigo-600` に変更が必要 |
| `Coordinator/ProjectJobs/Create.vue` | 要確認 | — |
| `Coordinator/ProjectJobs/Edit.vue` | 要確認 | — |
| `Coordinator/ProjectJobs/BulkCreate.vue` | ✅ 適用済み | L-02で対応済み |
| `Coordinator/ProjectJobs/JobAssign/Index.vue` | ⚠️ 部分適用 | `#header` に見出しのみ。戻るボタン・`#headerExtras` に新規作成ボタンが未対応 |
| `Coordinator/ProjectJobs/JobAssign/Show.vue` | ⚠️ 部分適用 | 戻るボタンが `px-4 py-2`（`px-3 py-1.5` に）+ ラベルが「戻る」のみ |
| `Coordinator/ProjectJobs/JobAssign/SelectProject.vue` | ❌ 未適用 | 戻るボタンが `#header` 外（カード内）、ラベル「戻る」のみ |
| `Coordinator/ProgressSheets/Show.vue` | ✅ 適用済み | ガイドライン準拠 |
| `Coordinator/ProgressTemplates/Edit.vue` | ✅ 適用済み | L-02で対応済み |
| `Coordinator/ProgressTemplates/Index.vue` | 要確認 | — |
| `Coordinator/ProjectSchedules/Calendar.vue` | ✅ 適用済み | L-02で対応済み |
| `Coordinator/ProjectSchedules/Comments/Create.vue` | ⚠️ 部分適用 | 戻るボタンが `bg-gray-300`（`bg-gray-200` に）+ `router.back()` 使用 |
| `Coordinator/JobBox/Index.vue` | ✅ 適用済み | `#headerExtras` に新規作成 |
| `Coordinator/Subcontractors/Create.vue` | ⚠️ 部分適用 | 戻るボタンがリンクスタイル（ボタンスタイルに変更）、`#header` 外 |
| `Coordinator/Subcontractors/Edit.vue` | ⚠️ 部分適用 | 同上 |
| `Coordinator/Dashboard.vue` | ✅ 適用済み | 一覧ページ相当 |
| `Coordinator/Settings/Index.vue` | 要確認 | N-08で作成 |

---

### Leader ロール

| ファイル | 現状 | 主な問題点 |
|---------|------|-----------|
| `Leader/ProjectJobs/Show.vue` | ✅ 適用済み | L-02で対応済み |
| `Leader/ProjectJobs/Index.vue` | ⚠️ 部分適用 | `mx-auto max-w-6xl` 重複ラップあり |
| `Leader/Teams/Show.vue` | ✅ 適用済み | L-02で対応済み |
| `Leader/Teams/Create.vue` | ✅ 適用済み | L-02で対応済み |
| `Leader/Teams/Edit.vue` | ✅ 適用済み | L-02で対応済み |
| `Leader/Dashboard.vue` | ✅ 適用済み | — |

---

### User ロール

| ファイル | 現状 | 主な問題点 |
|---------|------|-----------|
| `User/ProjectJobs/Show.vue` | ✅ 適用済み | L-02で対応済み |
| `User/ProgressSheets/Show.vue` | ✅ 適用済み | L-02で対応済み |
| `User/ProofJobs/Show.vue` | ✅ 適用済み | L-02で対応済み |
| `User/ProofJobs/Set.vue` | ✅ 適用済み | L-02で対応済み |
| `User/ProjectJobs/Index.vue` | 要確認 | — |

---

### 共通・横断ページ

| ファイル | 現状 | 主な問題点 |
|---------|------|-----------|
| `JobBox/Index.vue` | ✅ 適用済み | L-02で対応済み |
| `JobBox/Show.vue` | ⚠️ 部分適用 | 戻るボタン(`routeBack()`)がコンテンツカード内。`#header` への移動が必要 |
| `JobBox/Schedule.vue` | ⚠️ 部分適用 | 戻るボタン(`backHref`)がコンテンツ内、ラベル「戻る」のみ |
| `JobBox/Edit.vue` | 要確認 | — |
| `MyJobBox/Show.vue` | ⚠️ 部分適用 | 戻るボタンがコンテンツ内（`#header` に移動が必要） |
| `MyJobBox/Edit_user.vue` | 要確認 | — |
| `MyJobBox/Create_user.vue` | 要確認 | — |
| `Events/Show.vue` | ⚠️ 部分適用 | `#header` スロットなし。戻るボタンがコンテンツ内（N-10で `goBack()` 修正済みだが `#header` への移動は未対応） |
| `Events/Edit.vue` | ❌ 未適用 | `#header` スロットなし |
| `Events/Create.vue` | ⚠️ 部分適用 | `#header` スロットなし。`mx-auto max-w-2xl` 重複ラップ |
| `Events/Create_Job.vue` | ❌ 未適用 | `#header` スロットなし |
| `Events/InteractionsShow.vue` | ⚠️ 部分適用 | `mx-auto max-w-2xl` 重複ラップ |
| `Calendar/Index.vue` | 要確認 | ユーザーカレンダー |
| `Diaries/Show.vue` | ⚠️ 部分適用 | `window.history.back()` 使用 |
| `Diaries/Edit.vue` | ⚠️ 部分適用 | `#header` スロットなし、`window.history.back()` 使用 |
| `Diaries/Create.vue` | ❌ 未適用 | `#header` スロットなし |

---

### Admin ロール

| ファイル | 現状 | 主な問題点 |
|---------|------|-----------|
| `Admin/Users/Index.vue` | ⚠️ 部分適用 | `#header` スロットに見出しなし（`<h2>` がカード内） |
| `Admin/Users/Show.vue` | ⚠️ 部分適用 | `mx-auto max-w-2xl` 重複ラップ |
| `Admin/Users/CsvPreview.vue` | ⚠️ 部分適用 | 戻るボタンがリンクスタイル（テキストリンク） |
| `Admin/Teams/Show.vue` | ⚠️ 部分適用 | `mx-auto max-w-4xl` 重複ラップ。戻るボタンが `goBack()`（`window.history.back()` ベース） |
| `Admin/Companies/Create.vue` | ⚠️ 部分適用 | `mx-auto max-w-2xl` 重複ラップ。戻るボタンがテキストリンク |
| `Admin/Dashboard.vue` | ✅ 適用済み | — |

---

### SuperAdmin ロール

| ファイル | 現状 | 主な問題点 |
|---------|------|-----------|
| `SuperAdmin/AdminPermissions/Edit.vue` | ⚠️ 部分適用 | `goBack()` 使用（`window.history.back()` ベース）。`mx-auto max-w-lg` 重複ラップ |
| `SuperAdmin/AdminUsers/Show.vue` | ⚠️ 部分適用 | `mx-auto max-w-lg` 重複ラップ |
| `SuperAdmin/Companies/Create.vue` | ⚠️ 部分適用 | `mx-auto max-w-2xl` 重複ラップ |
| `SuperAdmin/Dashboard.vue` | ✅ 適用済み | — |

---

### その他ロール・機能ページ

| ファイル | 現状 | 主な問題点 |
|---------|------|-----------|
| `Clients/Index.vue` | ✅ 適用済み | `#header` あり |
| `Clients/Show.vue` | ⚠️ 部分適用 | 戻るボタンがリンクスタイル |
| `Clients/Create.vue` | ⚠️ 部分適用 | 戻るボタンがリンクスタイル、`#header` 外 |
| `Clients/Edit.vue` | ⚠️ 部分適用 | 戻るボタンが「一覧へ戻る」（「に」に統一）、`bg-gray-300` |
| `Clients/CsvUpload.vue` | 要確認 | — |
| `Messages/Index.vue` | ✅ 適用済み | — |
| `Messages/Show.vue` | ⚠️ 部分適用 | 戻るボタンが `<a>` タグ + テキストリンクスタイル |
| `Messages/Create.vue` | ✅ 適用済み | — |
| `Chat/Index.vue` | ⚠️ 部分適用 | `mx-auto max-w-4xl` 重複ラップ |
| `Chat/CreateRoom.vue` | ⚠️ 部分適用 | `mx-auto max-w-2xl` 重複ラップ |
| `WorkloadAnalyzer/AnalysisGuide.vue` | ⚠️ 部分適用 | 戻るボタンが `<a>` タグ + テキストリンクスタイル |
| `ProofCoordinator/Dispatchers/Show.vue` | ⚠️ 部分適用 | 戻るボタンがリンクスタイル |
| `ProofCoordinator/Assignments/Show.vue` | ⚠️ 部分適用 | `mx-auto max-w-2xl` 重複ラップ、戻るボタン未確認 |
| `JobNotifications/Index.vue` | ⚠️ 部分適用 | `#header` スロットなし |
| `WorkRecord/Index.vue` | ✅ 適用済み | `#header` あり |
| `Announcements/Index.vue` | ✅ 適用済み | `#header` あり |
| `Announcements/Show.vue` | ✅ 適用済み | `#header` あり |

---

## 10. 対応優先度

### 優先度 A（影響大・頻繁にアクセスされる）

1. `Coordinator/ProjectJobs/Index.vue` — 新規作成ボタン位置・色
2. `Coordinator/ProjectJobs/JobAssign/Index.vue` — 戻るボタン・新規作成ボタン
3. `JobBox/Show.vue` — 戻るボタンを `#header` に移動
4. `Events/Show.vue` — `#header` スロット追加
5. `Diaries/Show.vue` / `Diaries/Edit.vue` — `window.history.back()` 廃止

### 優先度 B（中程度）

6. `Admin/Users/Index.vue` / `Admin/Users/Show.vue`
7. `Clients/Create.vue` / `Clients/Edit.vue` / `Clients/Show.vue`
8. `MyJobBox/Show.vue` — 戻るボタン位置
9. `JobBox/Schedule.vue` — 戻るボタン位置・ラベル
10. `Leader/ProjectJobs/Index.vue` — 重複ラップ削除

### 優先度 C（低頻度・管理機能）

11. `Admin/Teams/Show.vue` / `Admin/Companies/Create.vue`
12. `SuperAdmin/*` 各ページ
13. `Chat/Index.vue` / `Chat/CreateRoom.vue`
14. `ProofCoordinator/*` 各ページ

---

*このドキュメントはサイト全体のレイアウト統一作業の基準仕様書です。各ページ修正時は Section 8「適用チェックリスト」に従って確認してください。*
