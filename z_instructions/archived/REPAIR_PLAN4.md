# SunBWork 修繕計画書 第4版 — レスポンシブ対応
作成日: 2026-05-12

---

## 背景・目的

スマートフォン（375px〜）でも主要操作ができるようにする。
現状はナビゲーションタブが全ロールで未対応のため、スマホでは縦長の崩れたボタンが並ぶ状態。

## 対応しない範囲（明示的除外）

- 進行表一覧・ジョブ一覧・作業量分析など横幅が広いテーブル（横スクロール現状維持）
- 全ページの完全なモバイル最適化（業務向けシステムのためPCが基本）

## ブレークポイント基準

- モバイル: `< 640px`（Tailwind の `sm` 未満）
- デスクトップ: `≥ 640px`（Tailwind の `sm` 以上）

---

## フェーズ1：タブナビゲーション（最優先）

### R-01 全タブコンポーネント — モバイルドロップダウン追加

**対象ファイル（8ファイル）:**

| ファイル | 現在の nav class | タブ数 | ロールカラー |
|---------|----------------|--------|------------|
| `SuperAdminNavigationTabs.vue` | `flex space-x-8` | 7 | yellow |
| `AdminNavigationTabs.vue` | `flex flex-wrap gap-2` | 最大12 | red |
| `LeaderNavigationTabs.vue` | `flex flex-wrap gap-2` | 最大10 | orange |
| `ClerkNavigationTabs.vue` | `flex flex-wrap gap-2` | 1 | purple |
| `CoordinatorNavigationTabs.vue` | `flex space-x-8` | 8 | green |
| `ProofCoordinatorNavigationTabs.vue` | `flex flex-wrap gap-2` | 6 | pink |
| `PrepressNavigationTabs.vue` | `flex flex-wrap gap-2` | 3 | green-700 |
| `UserNavigationTabs.vue` | `flex flex-wrap gap-2` | 最大8 | blue |

**問題:**
- `CoordinatorNavigationTabs` と `SuperAdminNavigationTabs` が `flex space-x-8` を使用 → 折り返しなし・モバイルで各タブが縦長ボックスになる
- 他コンポーネントは折り返しするが、モバイル幅では複数行になり UI が崩れる

**対応方針:**

```
モバイル (sm:hidden): <select> で全タブを表示。選択時に router.get() でナビゲート
デスクトップ (hidden sm:flex): 現在のタブ表示を維持（スタイル変更なし）
```

**実装テンプレート:**

```vue
<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    active: { type: String, default: '' },
});

// タブ定義: condition が false のものはドロップダウンからも除外
const tabs = computed(() => [
    { key: 'foo', href: route('xxx.index'), label: 'ラベル' },
    { key: 'bar', href: route('xxx.show'), label: '別ラベル', condition: someCondition.value },
].filter(t => t.condition !== false));

function onMobileSelect(e) {
    const href = e.target.value;
    if (href) router.get(href);
}
</script>

<template>
    <div class="mb-6">
        <!-- モバイル: ドロップダウン -->
        <div class="sm:hidden">
            <select
                @change="onMobileSelect"
                class="w-full rounded-md border border-[ロールカラー]-300 bg-white px-3 py-2 text-sm text-[ロールカラー]-700 shadow-sm focus:border-[ロールカラー]-500 focus:outline-none focus:ring-1 focus:ring-[ロールカラー]-500"
            >
                <option value="">— ページを選択 —</option>
                <option
                    v-for="t in tabs"
                    :key="t.key"
                    :value="t.href"
                    :selected="active === t.key"
                >{{ t.label }}</option>
            </select>
        </div>

        <!-- デスクトップ: タブ（現状スタイル維持。space-x-8 を使っていたコンポーネントは flex-wrap gap-2 に変更） -->
        <nav class="hidden sm:flex flex-wrap gap-2" aria-label="Tabs">
            <!-- 既存のタブリンク（変更なし） -->
        </nav>
    </div>
</template>
```

**各コンポーネントの個別注意事項:**

| コンポーネント | 注意事項 |
|---|---|
| `SuperAdminNavigationTabs` | `flex space-x-8` → desktop側を `flex-wrap gap-2` に変更。`route().has('...')` の存在チェックをtabs computed内で `condition` に変換 |
| `CoordinatorNavigationTabs` | `flex space-x-8` → `flex-wrap gap-2` に変更。`getJobboxLink()` 等のgetter関数をtabs computed内に取り込む（try/catch パターンを維持） |
| `AdminNavigationTabs` | `can('...')` の権限チェックを tabs computed 内の `condition: can('...')` に変換。`isRepresentative` も同様 |
| `LeaderNavigationTabs` | `can()`・`isDepartmentLeader`・`isAdminOrAbove` を `condition` に変換 |
| `ProofCoordinatorNavigationTabs` | `pendingCount` プロップがある。ドロップダウンのラベルを `'校正依頼受信' + (pendingCount > 0 ? ' (' + pendingCount + '件)' : '')` の形式にする |
| `UserNavigationTabs` | `isProofMember` を `condition` に変換。校正ジョブタブはピンク色だが、ドロップダウンは色の区別不要 |
| `ClerkNavigationTabs` | タブが1件のみだが、統一のため同パターンで実装する |
| `PrepressNavigationTabs` | 特別な条件なし。シンプルに実装 |

---

## フェーズ2：フォーム類

### R-02 案件作成フォーム — レスポンシブ対応

**対象ファイル:** `resources/js/Pages/Coordinator/ProjectJobs/Create.vue`

**問題（コード確認済み）:**
- クライアント選択行: `flex items-center gap-2` で「ID入力・名前入力・詳細検索ボタン」が横並び → モバイルで極端に狭くなる
- 「詳細検索」ボタンが小さくなりタップしにくい

**対応:**
- クライアント選択エリアのレイアウトを `flex-col sm:flex-row` に変更
- ID入力・名前入力欄にそれぞれ `w-full` を確保
- 「詳細検索」ボタンをモバイルでは独立行（`w-full sm:w-auto`）に

---

### R-03 ジョブ割り振りフォーム — レスポンシブ対応

**対象ファイル:** `resources/js/Components/AssignmentForm.vue`

**状態:** 要調査。ファイル規模が大きいため、着手前にコードを確認してから詳細仕様を策定する。

---

### R-04 カレンダーイベント登録フォーム — レスポンシブ対応

**対象ファイル:** `resources/js/Pages/Events/Create.vue`

**状態:** 要調査。着手前にコードを確認してから詳細仕様を策定する。

---

## フェーズ3：カレンダー

### R-05 ユーザーカレンダー — モバイルデフォルトビュー変更

**対象ファイル:**
- `resources/js/Components/Calendar.vue`
- （CalendarController.php: 初期ビューの prop を変更する場合）

**問題:** `timeGridWeek`（週表示）はモバイルで横幅が広く使いづらい。

**対応:**
- FullCalendar の初期表示ビューをモバイルでは `listWeek` にする
- `onMounted` 内で `window.innerWidth < 640` を検出し、`initialView` を動的に切り替える

---

## フェーズ4：AppLayout 整理

### R-06 AppLayout — ハンバーガーメニューのサブタブ整合性

**対象ファイル:** `resources/js/layouts/AppLayout.vue`

**現状の問題:**
- ハンバーガーメニュー内のサブタブ（lines 591–729）が NavigationTabs コンポーネントより項目が少ない（Coordinator のハンバーガーにはジョブ一覧・進行表・設定が欠けているなど）
- R-01 実装後はタブコンポーネント側でモバイル対応するため、ハンバーガーのサブタブは重複・冗長になる

**対応:** R-01 完了後に判断する。ハンバーガーのサブタブセクションを削除するか、NavigationTabs と完全同期するかを選ぶ。

---

---

## フェーズ5：ヘッダー・ボタン細部調整

### R-07 AppLayout ヘッダー — flex-wrap 化・高さ調整

**対象ファイル:** `resources/js/layouts/AppLayout.vue`

**現状の問題:**
- ヘッダーコンテナが `flex justify-between` で `#headerExtras` が `flex-shrink-0`
- モバイルで headerExtras が先にスペースを占有し、`#header`（タイトル・戻るボタン）が極端に狭くなる
- 結果としてタイトルや戻るボタンのテキストが縦文字に崩れる

**対応:**
```html
<!-- 変更前 -->
<div class="mx-auto flex min-h-[4.5rem] max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
    <div class="flex-1">
    <div class="ml-4 flex-shrink-0">

<!-- 変更後 -->
<div class="mx-auto flex flex-wrap items-center justify-between gap-x-4 gap-y-2 max-w-7xl px-4 py-3 sm:min-h-[4.5rem] sm:py-4 sm:px-6 lg:px-8">
    <div class="min-w-0 flex-1">
    <div class="flex-shrink-0">
```

- `flex-wrap` 追加 → 幅が足りなければ headerExtras が次行に折り返す
- `gap-x-4 gap-y-2` → 折り返し時の縦方向の隙間を確保
- `min-h-[4.5rem]` → `sm:min-h-[4.5rem]` に（モバイルは高さ固定しない）
- `py-4` → `py-3 sm:py-4`（モバイルの縦余白を削減）
- `ml-4` を削除（gap-x-4 で代替）
- `flex-1` div に `min-w-0` 追加（flex child のテキストオーバーフロー防止）

---

### R-08 戻るボタン — whitespace-nowrap 追加

**対象:** `#header` スロット内の「← 戻る」「← ○○に戻る」ボタン・リンク全般

**問題:** ボタン内テキストに `whitespace-nowrap` がないため、狭い幅で文字が縦に折り返す

**対応:** sed で Pages/ 配下のヘッダー内戻るボタンに `whitespace-nowrap` を追加。
具体的には `px-3 py-1.5 text-sm font-medium text-gray-700` パターンに `whitespace-nowrap` を追加する。

---

### R-09 ヘッダータイトル — モバイルフォントサイズ縮小

**対象:** `#header` スロット内の `<h2 class="text-xl font-semibold ...">` 全般

**問題:** `text-xl`（20px）はモバイルで大きすぎ、他のボタンと並ぶと窮屈

**対応:** `text-xl font-semibold leading-tight text-gray-800` → `text-base sm:text-xl font-semibold leading-tight text-gray-800` に sed 一括変更

---

### R-11 テーブル — 横スクロール対応

**対象ファイル:** `overflow-x-auto` 未適用でテーブルを持つ 37 ファイル

**問題:** `<table class="min-w-full ...">` がモバイル幅に収まらず、セルが縦に押しつぶされる。

**対応:** 各 `<table>` を `<div class="overflow-x-auto">` でラップする。

```html
<!-- 変更前 -->
<table class="min-w-full divide-y divide-gray-200">

<!-- 変更後 -->
<div class="overflow-x-auto">
<table class="min-w-full divide-y divide-gray-200">
...
</table>
</div>
```

**方法:** perl 一括スクリプトで `<table` の直前に `<div class="overflow-x-auto">` を挿入し、`</table>` の直後に `</div>` を挿入する。

**注意:** `overflow-x-auto` は内容が親幅を超えたときのみスクロールバーを表示するため、小さいテーブルには見た目の変化なし。

---

### R-10 検索フィールド隣ボタン — モバイルサイズ調整

**対象ファイル:** 要調査（検索フォームを持つ Index ページ等）

**問題:** 検索インプット横のボタンが `px-4 py-2` 等のデスクトップサイズのまま

**対応:** 着手前にコードを確認して詳細仕様を策定する

---

## 作業ID一覧（全フェーズ）

| ID | 内容 | 変更ファイル数 | 難易度 |
|----|------|------------|--------|
| R-01 | 全タブコンポーネントにモバイルドロップダウン追加 | 8ファイル | 中（パターン統一） |
| R-02 | ProjectJobs/Create.vue フォームレスポンシブ | 1ファイル | 小 |
| R-03 | AssignmentForm.vue フォームレスポンシブ | 1ファイル | 要調査 |
| R-04 | Events/Create.vue フォームレスポンシブ | 1ファイル | 要調査 |
| R-05 | Calendar.vue モバイルデフォルトビュー変更 | 1〜2ファイル | 小 |
| R-06 | AppLayout ハンバーガーメニュー整理 | 1ファイル | R-01完了後に判断 |
| R-07 | AppLayout ヘッダー flex-wrap 化 | 1ファイル | 小 |
| R-08 | 戻るボタン whitespace-nowrap 追加 | 多ファイル（sed） | 小 |
| R-09 | ヘッダー h2 タイトルフォントサイズ縮小 | 多ファイル（sed） | 小 |
| R-10 | 検索フィールド隣ボタンサイズ調整 | 要調査 | 中 |
| R-11 | テーブル横スクロール対応 | 37ファイル（perl 一括） | 小 |
