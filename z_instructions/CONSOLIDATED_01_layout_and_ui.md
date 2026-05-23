# レイアウトと UI（統合ドキュメント）
最終更新: 2026-05-23

CLAUDE.md の UI/レイアウトセクションが最上位の権威。本ファイルはその詳細リファレンス。

---

## AppLayout 使用規則（最重要）

すべての Inertia ページは `AppLayout` を使うこと。

### 正しいページ構造

```vue
<AppLayout title="ページタイトル">
  <template #header>
    <h2 class="text-xl font-semibold leading-tight text-gray-800">見出し</h2>
  </template>

  <div class="rounded bg-white p-6 shadow">
    <!-- コンテンツ -->
  </div>
</AppLayout>
```

**`py-12 > max-w-7xl mx-auto` は AppLayout の内部に実装済み。ページ側で重複させない。**

### NG パターン（やってはいけない）

```vue
<!-- NG: py-12 / max-w-7xl をページ側で書く -->
<AppLayout>
  <div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
      <div class="rounded bg-white p-6 shadow">...</div>
    </div>
  </div>
</AppLayout>

<!-- NG: <main> タグ使用 -->
<!-- NG: ToastUnified を各ページで重複配置（AppLayout に既存） -->
```

---

## AppLayout スロット

| スロット | 用途 |
|---------|------|
| `#header` | ページ見出し（h2）、戻るボタンなど |
| `#headerExtras` | ヘッダー右側の追加コンテンツ |
| `#tabs` | ナビゲーションタブ |
| デフォルト | メインコンテンツ（カード等） |

### AppLayout が provide する値

- `authUser` — ログインユーザーオブジェクト
- `user` — ページの user prop

---

## 戻るボタン配置（L-02 標準）

`#header` スロット内に `div.flex.items-center.gap-3` で Link + h2 を横並びにする。

```vue
<template #header>
  <div class="flex items-center gap-3">
    <Link
      :href="route('some.index')"
      class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap"
    >← 一覧に戻る</Link>
    <h2 class="text-xl font-semibold leading-tight text-gray-800">ページタイトル</h2>
  </div>
</template>
```

- `#headerExtras` や `#header` の外には置かない
- `← ` は全角スペース、`whitespace-nowrap` を付ける

---

## ロール別バッジカラー

| ロール | Tailwind クラス |
|-------|---------------|
| SuperAdmin | `bg-yellow-100 text-yellow-800` |
| Admin | `bg-red-100 text-red-800` |
| Leader | `bg-orange-100 text-orange-800` |
| Coordinator | `bg-green-100 text-green-800` |
| Clerk | `bg-purple-100 text-purple-800` |
| User | `bg-blue-100 text-blue-800` |

---

## AppLayout ヘッダー右ボタン（グローバル）

AppLayout のヘッダー右エリアに以下のアイコンボタンが配置済み（追加不要）:

- **スクリプトツール**（スパナアイコン）— `auth.canAccessScripts` が true のユーザーのみ表示
- **更新ログ**（時計アイコン）— `route('changelogs.index')` へのリンク、全ユーザー表示

---

## テーブル・カード共通スタイル

```
テーブル: min-w-full divide-y divide-gray-200
ヘッダー行: bg-gray-50
データ行: hover:bg-gray-50
カード: rounded bg-white p-6 shadow
```

---

## Ziggy route() 呼び出し

パラメータは必ずオブジェクト形式で渡す:

```js
// OK
route('coordinator.project_jobs.show', { projectJob: job.id })
route('changelogs.show', { changelog: log.id })

// NG
route('coordinator.project_jobs.show', job.id)  // 第2引数に直接 ID
```

---

## レスポンシブ

- Tailwind の `sm:` / `md:` / `lg:` プレフィックスで対応
- ファイル名の大文字小文字を一貫させる（大文字小文字違いだけの差分は TypeScript/jsconfig 警告の原因）

---

## ToastUnified

AppLayout 内にグローバル配置済み。各ページで重複させない。
