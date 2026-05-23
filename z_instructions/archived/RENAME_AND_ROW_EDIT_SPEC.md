# 設計図: 進行管理表・テンプレート — ラベル変更 + 行インライン編集

作成日: 2026-04-04
対象: `ProgressSheets/Show.vue` / `ProgressTemplates/Edit.vue`

---

## 背景・現状の問題

### 現在のパネル配置（両ファイル共通）

| 位置 | ProgressSheets/Show.vue | ProgressTemplates/Edit.vue |
|------|------------------------|---------------------------|
| 左パネル | 行管理（台割） | 台割行テンプレート |
| 右パネル | 列・ステージ構成 | 列・ステージ構成 |

**問題①**: ユーザーの感覚では「台割アイテム（表紙・P.1-4 等）が列、ステージが行」なのに、
ラベルが逆（台割アイテムを「行管理」と呼んでいる）。

**問題②**: `ProgressSheets/Show.vue` の編集モードで、行ラベルのインライン編集ができない。
- 左パネルの行一覧は `<span>{{ row.label }}</span>` の表示のみ（編集ボタンなし）
- `onEditRow` は `prompt()` を使うが、ProgressTable に `:edit-mode="false"` を渡しているため呼ばれない
- → **行のタイトルを変更する手段がない**

**問題③**: ProgressSheets では行追加フォームがパネル上部にあるが分かりにくい。
ProgressTemplates では「＋行を追加」ボタンが下部にあり一応機能するが、
グループ内への子行追加は隠れている。

---

## 変更仕様

### 1. ラベル変更（両ファイル）

| 変更前 | 変更後 |
|--------|--------|
| 行管理（台割） | 列管理 |
| 台割行テンプレート | 列管理 |
| 縦軸（行） | 縦軸（列） |
| 列・ステージ構成 | 行・ステージ構成 |
| 横軸（列） | 横軸（行） |
| ← 列・ステージを追加してください | ← 行・ステージを追加してください |
| 各ステージ内のセル詳細は「列・ステージ構成」の設定 | 各ステージ内のセル詳細は「行・ステージ構成」の設定 |
| コメント `// ── 編集モード：行管理 + 列ツリー` | `// ── 編集モード：列管理 + 行ツリー` |

変更対象ファイル:
- `resources/js/Pages/Coordinator/ProgressSheets/Show.vue`
  - 行89（`<h3>行管理（台割）</h3>`）
  - 行210（`<h3>列・ステージ構成</h3>`）
  - コメント行73、730
- `resources/js/Pages/Coordinator/ProgressTemplates/Edit.vue`
  - 行41（`<h3>台割行テンプレート</h3>`）
  - 行43（`縦軸（行）`）
  - 行157（`<h3>列・ステージ構成</h3>`）
  - 行159（`横軸（列）`）
  - 行196（`← 列・ステージを追加してください`）
  - 行244（`「列・ステージ構成」の設定に従います`）

また `ProgressTemplates/Show.vue` の表示側も変更:
- 「台割行テンプレート」→「列管理」
- 「列・ステージ構成」→「行・ステージ構成」

---

### 2. ProgressSheets/Show.vue — 行インライン編集

#### 2a. 状態管理（追加）

```js
const editingRowId = ref(null);   // インライン編集中の行ID
const editingRowLabel = ref('');  // 編集中のラベル文字列
```

#### 2b. 関数（追加・変更）

```js
// インライン編集開始
function startEditRow(row) {
  editingRowId.value = row.id;
  editingRowLabel.value = row.label;
}

// インライン編集確定
function commitEditRow(row) {
  const label = editingRowLabel.value.trim();
  if (!label || label === row.label) {
    editingRowId.value = null;
    return;
  }
  router.put(
    route('coordinator.progress_sheets.rows.update', { sheet: props.sheet.id, row: row.id }),
    { label },
    {
      preserveScroll: true,
      onSuccess: (page) => {
        syncRowsFromPage(page);
        editingRowId.value = null;
      },
    }
  );
}

// キャンセル
function cancelEditRow() {
  editingRowId.value = null;
  editingRowLabel.value = '';
}

// onEditRow は削除（prompt() は使わない）
```

#### 2c. テンプレート変更（行一覧部分）

グループ親行の `<span class="flex-1 ...">{{ row.label }}</span>` を:

```html
<!-- 編集中 -->
<input
  v-if="editingRowId === row.id"
  v-model="editingRowLabel"
  type="text"
  class="flex-1 rounded border border-indigo-300 px-2 py-0.5 text-sm focus:outline-none"
  @keydown.enter.prevent="commitEditRow(row)"
  @keydown.escape="cancelEditRow"
  @blur="commitEditRow(row)"
/>
<!-- 通常表示（クリックで編集開始） -->
<span
  v-else
  class="flex-1 cursor-pointer text-sm font-medium text-indigo-700 hover:underline"
  @click="startEditRow(row)"
>{{ row.label }}</span>
```

フラット行の `<span class="flex-1 text-sm">{{ row.label }}</span>` も同様（ただし text-indigo-700 ではなく text-gray-800）。

#### 2d. 行追加 UI の改善

現在: パネル上部に独立したフォーム（input + 「追加」ボタン）
変更後: リストの**下部**に `＋ 列を追加` ボタン（ダッシュスタイル、TemplatesのEditと同じ見た目）

```html
<button
  type="button"
  class="mt-2 flex w-full items-center justify-center rounded border border-dashed border-gray-300 py-1.5 text-sm text-gray-500 hover:border-indigo-400 hover:text-indigo-500"
  @click="addRow"  <!-- 現在のaddRow()をそのまま使うが入力欄を廃止 -->
>
  ＋ 列を追加
</button>
```

**行追加のUX変更**: 「追加」ボタンをクリックすると、リストの末尾にインライン編集状態の空行を追加して即座に編集モードにする（テキスト入力後Enterで確定、Escでキャンセル・削除）。

```js
function addRow() {
  // ラベル空でPOSTし、サーバーから返ってきたら最後の行をeditingRowIdにセット
  router.post(
    route('coordinator.progress_sheets.rows.store', { sheet: props.sheet.id }),
    { label: '新しい列' },   // 暫定ラベル（すぐ編集モードになる）
    {
      preserveScroll: true,
      onSuccess: (page) => {
        syncRowsFromPage(page);
        // 追加された行（末尾のトップレベル行）を即編集モードに
        const newRow = localRows.value.filter(r => !r.parent_id).at(-1);
        if (newRow) startEditRow(newRow);
      },
    }
  );
}
```

`newRowLabel` ref と独立したフォームは**削除**。

---

### 3. ProgressTemplates/Edit.vue — 行インライン編集の確認

#### 現状確認

現在のテンプレート:
- フラット行: `<input v-model="row.label" type="text" ...>` ✅（編集可）
- 子行: `<input v-model="child.label" type="text" ...>` ✅（編集可）
- グループ親見出し: `<input v-model="row.label" type="text" ...>` ✅（編集可）

→ **ProgressTemplates/Edit.vue は編集機能が既にある**。変更はラベル名のみ。

ただし「行を増やせるようにしてほしい」という要望に対し、現在の UI を確認:
- 下部に `＋ 行を追加` ボタンあり ✅

→ **ProgressTemplates/Edit.vue への機能追加は不要**。ラベル変更のみ。

---

### 4. ProgressTemplates/Show.vue — ラベル変更のみ

```
「台割行テンプレート」→「列管理」
「列・ステージ構成」→「行・ステージ構成」
```

---

## 実装手順

1. **ラベル変更**（3ファイル: Show.vue/ProgressSheets、Edit.vue/ProgressTemplates、Show.vue/ProgressTemplates）
   - テキスト置換のみ。ロジック変更なし。

2. **ProgressSheets/Show.vue — インライン編集 + 行追加 UI**
   - `editingRowId` / `editingRowLabel` ref 追加
   - `startEditRow` / `commitEditRow` / `cancelEditRow` 関数追加
   - `onEditRow` 削除（prompt() 廃止）
   - テンプレートの行一覧: `<span>` → 条件付き `<input>`/`<span>`
   - `newRowLabel` ref と追加フォームを削除
   - `addRow()` を「新しい列」ラベルで即POST + 即編集モード
   - 下部に `＋ 列を追加` ダッシュボタン追加

3. **npm run build**（ziggy 再生成不要、route 変更なし）

---

## ファイル一覧

| ファイル | 変更種別 |
|---------|---------|
| `resources/js/Pages/Coordinator/ProgressSheets/Show.vue` | ラベル変更 + インライン編集 + 行追加UI |
| `resources/js/Pages/Coordinator/ProgressTemplates/Edit.vue` | ラベル変更のみ |
| `resources/js/Pages/Coordinator/ProgressTemplates/Show.vue` | ラベル変更のみ |

**migration / routes / controller の変更なし**（`rows.update` ルートは既存）

---

## 注意点

- `syncRowsFromPage(page)` は Show.vue に既存の関数（`onSuccess` で `page.props.rows` を `localRows` に同期）
- グループ親行の編集中にESCを押した場合、ラベルが元に戻るだけでPOSTしない
- `commitEditRow` は `@blur` でも呼ぶ（他の要素クリック時に自動確定）
- `addRow` で追加したデフォルトラベル「新しい列」はすぐ編集モードになるので、ユーザーは即書き換えられる

---

## 引き継ぎ時の確認事項

この設計図を読んだ Claude は以下を実行:
1. `resources/js/Pages/Coordinator/ProgressSheets/Show.vue` を Read して現在の行数確認
2. 上記仕様に従って変更を実施
3. `npm run build` して成功確認
