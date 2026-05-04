# Prepress Board V2 設計書

## 概要

現在の3列カンバンボードを、物理ホワイトボード（A4紙を貼るタイプ）を再現した
4列アコーディオンボードに全面改修する。

---

## 変更ファイル一覧

| ファイル | 変更内容 |
|---------|---------|
| `app/Models/PrepressTicket.php` | 新ステータス `submitting`（入稿予定）を追加 |
| `resources/js/Pages/Prepress/Board.vue` | ボードUI全面改修 |

DBマイグレーション不要（`status` カラムは `varchar(20)` で enum 制約なし）

---

## ステータス定義（変更後）

| DBキー | ラベル | 旧ラベル |
|--------|--------|---------|
| `pending` | 準備 | 予定 |
| `in_progress` | 作業中 | 作業中（変更なし） |
| `submitting` | 入稿予定 | **新規追加** |
| `completed` | 完了 | 完了（変更なし） |

### ドラッグ&ドロップ 遷移ルール

| ドラッグ元 | 許可される移動先 | 備考 |
|-----------|----------------|------|
| 準備 (pending) | 作業中 (in_progress) のみ | 準備は作業中へしか移動不可 |
| 作業中 (in_progress) | 入稿予定 / 完了 | 自由 |
| 入稿予定 (submitting) | 作業中 / 完了 | 差戻も可 |
| 完了 (completed) | 入稿予定 (submitting) のみ | 差戻専用 |

---

## レイアウト設計

### 全体幅

AppLayout の `max-w-7xl`（1280px）を突き破り **90vw** を確保する。
21インチデスクトップ（1920px）では 1728px 相当。

```html
<div style="width: 90vw; margin-left: calc((90vw - 100%) / -2);">
```

### アコーディオン状態

```
openPanel: 'none' | 'ready' | 'completed'
```

| openPanel | 左ボード | 右ボード |
|-----------|---------|---------|
| `none`（デフォルト） | 作業中 | 入稿予定 |
| `ready` | **準備** | 作業中 |
| `completed` | 入稿予定 | **完了** |

常に2列表示。ボードは `grid grid-cols-2 gap-6`。

### ヘッダーボタン配置

ボードエリアの**上部・左右**にアコーディオントグルボタンを配置。

```
┌────────────────────────────────────────────────────────────────┐
│ [◀ 準備BOX]                                   [完了BOX ▶]    │
├─────────────────────────────┬──────────────────────────────────┤
│                             │                                  │
│       ボード（左）           │        ボード（右）              │
│                             │                                  │
└─────────────────────────────┴──────────────────────────────────┘
```

- 準備BOX ボタン: 左寄せ。`openPanel === 'ready'` でハイライト（黄色系）
- 完了BOX ボタン: 右寄せ。`openPanel === 'completed'` でハイライト（緑系）
- 現在アクティブなパネルを再クリック → `'none'` に戻す（デフォルト2列に戻る）

---

## カードデザイン

### 構造

```
┌──────────────────────────────┐
│  A4縦画像の上半分（サムネイル）  │
│  (aspect-ratio ≈ √2:1)       │
│  object-fit: cover           │
│  object-position: top        │
├──────────────────────────────┤
│ #伝票番号  案件名              │ ← text-xs、line-clamp-2
└──────────────────────────────┘
```

- 画像エリアのアスペクト比: `aspect-[297/210]` (A4縦の上半分 ≈ 1.414:1)
- 画像なし: グレー背景 + 「画像なし」テキスト（プレースホルダー）
- ドラッグ中は opacity-50 + scale-95

### ボード内グリッド

1ボード = **2列グリッド**（左右2枚並び）。3枚以上は折り返して縦スクロール。

```
┌──────────────────────────────────────────────┐
│ ボードヘッダー（ラベル + 枚数バッジ）          │
├─────────────────────┬────────────────────────┤
│ カード1             │ カード2                 │
├─────────────────────┼────────────────────────┤
│ カード3             │ カード4（あれば）        │
└─────────────────────┴────────────────────────┘
```

ボード本体: `overflow-y-auto`、`max-h-[calc(100vh-220px)]` でスクロール制御

---

## ドラッグ&ドロップ実装

### 遷移バリデーション

```js
const VALID_TRANSITIONS = {
    pending:     ['in_progress'],
    in_progress: ['submitting', 'completed'],
    submitting:  ['in_progress', 'completed'],
    completed:   ['submitting'],
};
```

`onDrop(colKey)` 内でチェック:
```js
if (!VALID_TRANSITIONS[ticket.status]?.includes(colKey)) return; // 無効な移動は無視
```

ドラッグ中に**移動不可のボード**は `opacity-40 cursor-not-allowed` スタイルを付与し視覚フィードバック。

---

## カラーテーマ

| ボード | ヘッダー背景 | ボーダー | ボタン色 |
|--------|------------|---------|---------|
| 準備 | bg-yellow-100 | border-yellow-400 | bg-yellow-200 |
| 作業中 | bg-blue-100 | border-blue-400 | — |
| 入稿予定 | bg-purple-100 | border-purple-400 | — |
| 完了 | bg-green-100 | border-green-500 | bg-green-200 |

---

## モデル変更（PrepressTicket.php）

```php
const STATUS_SUBMITTING = 'submitting';

const STATUS_LABELS = [
    'pending'     => '準備',
    'in_progress' => '作業中',
    'submitting'  => '入稿予定',   // 新規追加
    'completed'   => '完了',
];
```

---

## COLUMNS 定義（Board.vue）

```js
const COLUMNS = [
    { key: 'pending',     label: '準備',    color: 'border-yellow-400 bg-yellow-50', header: 'bg-yellow-100 text-yellow-800' },
    { key: 'in_progress', label: '作業中',  color: 'border-blue-400 bg-blue-50',     header: 'bg-blue-100 text-blue-800' },
    { key: 'submitting',  label: '入稿予定', color: 'border-purple-400 bg-purple-50', header: 'bg-purple-100 text-purple-800' },
    { key: 'completed',   label: '完了',    color: 'border-green-500 bg-green-50',   header: 'bg-green-100 text-green-800' },
];
```

---

## computed: visibleColumns

```js
const visibleColumns = computed(() => {
    if (openPanel.value === 'ready')     return ['pending',     'in_progress'];
    if (openPanel.value === 'completed') return ['submitting',  'completed'];
    return ['in_progress', 'submitting']; // デフォルト
});
```

---

## ライトボックス

既存の実装を維持。クリックで全画像を表示。

---

## 伝票登録モーダル

既存の実装を維持（変更なし）。

---

## 注意事項

1. さくら本番に `submitting` ステータスのデータが存在しないため、migrate は不要。ただしデプロイ後に初めてステータス変更すると `submitting` の値がDBに書き込まれる。
2. 既存の `pending` データは「準備」として表示される（ラベル変更のみ、DBキーは同じ）。
3. 90vw でのはみ出しは AppLayout の `overflow-hidden` が干渉しないか確認が必要。もし干渉する場合は AppLayout 側の親要素に `overflow: visible` を付与するか、`position: fixed` ベースのレイアウトに切り替える。
