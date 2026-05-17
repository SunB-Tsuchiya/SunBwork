# SCRIPTS_SECTION_GUIDELINES.md — スクリプトセクション 設計ガイドライン

作成日: 2026-05-16  
対象: Claude Code（実装・修正・新規スクリプト追加時に参照すること）

---

## 1. セクション概要

`/scripts` 配下は「社内向け業務ツール」の置き場。  
権限を持つユーザー（superadmin / admin / 個別許可された leader）のみがアクセスできる。

- `/scripts` — ツール一覧（`Scripts/Index.vue`）
- `/scripts/{slug}` — ツール実行画面（`Scripts/Show.vue` + 各ツールコンポーネント）
- 入り口はヘッダーのターミナルアイコン（`AppLayout.vue` 内 `v-if="canAccessScripts"`）

---

## 2. ファイル構成

```
routes/web.php
    scripts.index  GET /scripts
    scripts.show   GET /scripts/{script:slug}

app/Http/Controllers/ScriptController.php
app/Models/Script.php

resources/js/
    Pages/Scripts/
        Index.vue          ← スクリプト一覧（スタイルはガイドのIndex.vueに準拠）
        Show.vue           ← ツール表示ページ。componentMapで動的ロード
    Components/Scripts/
        ImageRenamer.vue   ← 画像リネームツール
        {ToolName}.vue     ← 新規ツールはここに追加

database/migrations/
    xxxx_create_scripts_table.php
    xxxx_add_script_access_to_leader_permissions.php

app/Http/Middleware/HandleInertiaRequests.php
    auth.canAccessScripts  ← leader の script_access を考慮した bool

app/Models/LeaderPermission.php
    script_access カラム

app/Http/Controllers/Admin/LeaderPermissionController.php
resources/js/Pages/Admin/LeaderPermissions/Edit.vue
    → 上記2ファイルに script_access を含めること（新規追加時は変更不要）
```

---

## 3. 権限モデル

| ロール | アクセス条件 |
|--------|-------------|
| superadmin | 常に可 |
| admin | 常に可 |
| leader | `leader_permissions.script_access = true` の場合のみ |
| その他（coordinator / user 等） | 不可（403） |

### 権限チェックの実装パターン（ScriptController）

```php
private function checkAccess(): void
{
    $user = Auth::user();
    if (in_array($user->user_role, ['superadmin', 'admin'])) return;
    if ($user->user_role === 'leader') {
        if (LeaderPermission::where('user_id', $user->id)->value('script_access')) return;
    }
    abort(403, 'スクリプトへのアクセス権限がありません。');
}
```

### Inertia 共有データ

`HandleInertiaRequests.php` の `auth` 配下（`auth.user` 配下ではない）:
```php
'auth' => [
    'user' => [...],
    'leaderPermissions' => ...,
    'canAccessScripts' => ...,   // ← ここ
]
```

AppLayout.vue での参照:
```js
const canAccessScripts = computed(() => page.props.auth?.canAccessScripts ?? false);
// NG: page.props.auth?.user?.canAccessScripts  ← user 配下ではない
```

---

## 4. DB設計（scriptsテーブル）

| カラム | 型 | 説明 |
|--------|-----|------|
| id | bigint PK | |
| name | string(100) | 一覧・ヘッダーに表示する名称 |
| slug | string(100) unique | URLスラッグ（例: `image-renamer`）|
| description | text | 一覧ページに表示する説明 |
| component_key | string(100) | Vueコンポーネントのキー（例: `ImageRenamer`）|
| sort_order | int default 0 | 一覧表示順 |
| is_active | boolean default true | false にすると404 |

### component_key の命名規則

- PascalCase（例: `ImageRenamer`, `CsvMerger`, `PdfSplitter`）
- `Scripts/Show.vue` の `componentMap` に対応エントリを追加すること

---

## 5. 新規スクリプトを追加する手順

### Step 1: DBレコードを追加

```php
// migration または tinker で
Script::create([
    'name'          => '新しいツール名',
    'slug'          => 'new-tool-slug',
    'description'   => '一覧に表示する説明文',
    'component_key' => 'NewTool',
    'sort_order'    => 2,
    'is_active'     => true,
]);
```

### Step 2: Vueコンポーネントを作成

`resources/js/Components/Scripts/NewTool.vue` を作成。  
**必須要素**（後述§6参照）をすべて含めること。

### Step 3: componentMap に登録

`resources/js/Pages/Scripts/Show.vue` の `componentMap` に追加:

```js
const componentMap = {
    ImageRenamer: defineAsyncComponent(() => import('@/Components/Scripts/ImageRenamer.vue')),
    NewTool:      defineAsyncComponent(() => import('@/Components/Scripts/NewTool.vue')),
};
```

### Step 4: ビルド

```bash
npm run build
```

---

## 6. スクリプトコンポーネントの必須要素

`Components/Scripts/` 配下の各ツールコンポーネントには以下を必ず含めること。

### 6-1. 使い方ガイドパネル（必須）

- コンポーネント内に折りたたみ式のガイドを設ける
- **デフォルトは非表示**（`showGuide = ref(false)`）。ボタンを押したときだけ表示される
- ステップインジケーターの直下、操作UIの上に配置
- 「使い方を見る / 閉じる」ボタンをステップインジケーターの右端に配置
- 内容として以下を含めること:
  - このツールでできること（変換例があると良い）
  - 必要なもの（ブラウザ要件・ファイル形式等）
  - 操作手順（番号付きリスト）
  - プレビューアイコンの見方（✓ / ⚠ / − など）
  - 注意事項（データ消失リスク・バックアップ推奨など）

### 6-2. ステップインジケーター（必須）

- ページ最上部に配置
- 現在のステップを視覚的に示す（indigo = アクティブ、green = 完了、gray = 未達）
- ガイドトグルボタンをインジケーターバーの右端に配置

### 6-3. エラーメッセージ表示（必須）

- `errorMsg` ref を持ち、操作ブロックの上に赤いアラートとして表示
- ファイル読込失敗・権限拒否・ブラウザ非対応など主要エラーを捕捉すること

### 6-4. ブラウザ非対応警告（ブラウザAPI使用時は必須）

- File System Access API 等を使う場合、`'showDirectoryPicker' in window` で判定
- 非対応ブラウザには `amber` 系の警告を全画面表示し、操作UIを隠す

### 6-5. 確認ダイアログ（不可逆操作を行うツールは必須）

- リネーム・削除・上書き等の操作前に `showConfirm = ref(false)` ベースのモーダルを表示
- 影響範囲（件数等）を明示する

### 6-6. 進捗表示（時間のかかる処理は必須）

- バッチ処理時はインジケーターバーで進捗（0〜100%）を表示
- UIがフリーズしているように見えないよう `await` ループ中に進捗を更新

### 6-7. 結果サマリーと後処理UI（必須）

- 処理完了後は成功/エラー/スキップの件数を数値で表示
- ログや結果をダウンロードできるボタン（JSON/CSV）を設ける
- 「最初からやり直す」ボタンで状態をリセットできること

### 6-8. リセット関数（必須）

- `function reset()` を必ず実装し、すべての状態変数を初期値に戻す

---

## 7. レイアウト規約

### 7-1. コンポーネントは AppLayout を使わない

`Show.vue` が AppLayout を担うため、各ツールコンポーネントはコンテンツのみを返す。

```vue
<!-- NG -->
<AppLayout>...</AppLayout>

<!-- OK -->
<template>
    <div class="rounded bg-white p-6 shadow">...</div>
</template>
```

### 7-2. Show.vue のヘッダー構造

```vue
<template #header>
    <div class="flex items-center gap-3">
        <Link :href="route('scripts.index')" class="text-sm text-gray-500 hover:text-gray-700">
            ← スクリプト一覧
        </Link>
        <span class="text-gray-300">|</span>
        <h2 class="text-base sm:text-xl font-semibold leading-tight text-gray-800">{{ script.name }}</h2>
    </div>
</template>
```

### 7-3. カラーパレット（スクリプトセクション）

スクリプトセクションのテーマカラーは **indigo / blue** 系を基調とする（ガイドの青・管理の赤・リーダーのオレンジと区別する）。

| 用途 | クラス例 |
|------|---------|
| プライマリボタン | `bg-indigo-600 hover:bg-indigo-700 text-white` |
| アクティブステップ | `bg-indigo-600 text-white` |
| 完了ステップ | `bg-green-100 text-green-700` |
| ガイドパネル背景 | `bg-gradient-to-br from-indigo-50 to-blue-50` |
| 警告（操作注意） | `bg-amber-50 border-amber-200 text-amber-700` |
| エラー | `bg-red-50 border-red-200 text-red-700` |
| スキップ行 | `bg-gray-50 text-gray-400` |

### 7-4. Index.vue のカードスタイル

`Index.vue` はカードを `cardStyles` 配列で色分けしており、ツールの追加は自動的に次の色が適用される（手動指定不要）。

---

## 8. スクリプトコンポーネントの props

Show.vue からは `script` オブジェクトが渡される:

```js
defineProps({
    script: { type: Object, required: true },
    // { id, name, slug, description, component_key }
});
```

ツール独自の設定は `script` オブジェクトで渡すのではなく、コンポーネント内に定数として持つこと。

---

## 9. ガイドと実装のセット原則

**スクリプトとガイドは必ずセットで実装・更新すること。**

- ツールの仕様が変わったらガイドの内容も同時に更新する
- 新しいエラーケースを追加したらガイドの「注意事項」に反映する
- ガイドに書いた内容とツールの実際の動作が食い違わないこと

---

## 10. 実装上の注意点

### File System Access API
- Chrome/Edge 123+ 専用。Safari / Firefox では `'showDirectoryPicker' in window` が false
- `showDirectoryPicker({ mode: 'readwrite' })` でリード/ライト権限を一括取得する
- `fileHandle.move(newName)` は Chrome 123+ のみ。存在しない場合はコピー＆削除のフォールバックを実装すること

### CSV 解析
- PapaParse を使用。Shift-JIS は先頭バイトで判定し `TextDecoder` で変換してから渡す
- ID列・タイトル列は複数候補名（`id / ID / 番号` 等）から自動マッチ
- Excel（.xlsx）は SheetJS（xlsx）ライブラリで解析。`raw: false` を指定して数値の先頭ゼロ保持

### ファイル名のサニタイズ
- Windows禁止文字 `\ / : * ? " < > |` は `_` に置換
- NFC 正規化を必ず行う（Unicode 文字化け防止）
- 255バイト上限に収まるようタイトル部分を切り詰める

### さくら本番環境への影響
- スクリプトツールはブラウザ完結型（サーバーにファイルを送らない）なので、さくらのサーバー制約の影響を受けない
- ただし新規 migration を追加した場合は本番で `php artisan migrate` を実行すること

---

## 11. 関連ファイル

| ファイル | 役割 |
|---------|------|
| `z_instructions/SCRIPT_PLAN1.md` | 第1版詳細仕様・DB設計 |
| `z_instructions/SCRIPT_MANAGER1.md` | 第1版進捗管理・作業ログ |
| `z_instructions/SCRIPT1_PROMPT.md` | 新セッション開始用プロンプト |
| `z_instructions/SCRIPTS_SECTION_GUIDELINES.md` | このファイル（実装規約） |

---
