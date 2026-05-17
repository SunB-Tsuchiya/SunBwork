# SCRIPT1_PROMPT.md — 新セッション開始用プロンプト

---

## このプロンプトの使い方

新しいセッションを開始するときに、Claude Code に以下をそのまま貼り付けてください。

---

## プロンプト本文（ここから貼り付け）

```
z_instructions/SCRIPT_PLAN1.md と z_instructions/SCRIPT_MANAGER1.md を読んでください。
「スクリプトページ機能」の実装作業を続けます。

## プロジェクト概要（サマリー）
- Laravel 11 / Vue 3 / Inertia.js / Vite / Tailwind CSS の業務管理SPA
- `members/scripts/` 配下にスクリプトツールのセクションを作成
- 権限: superadmin/admin は常に可、leader は leader_permissions.script_access=true のみ

## 設計の概要
1. scripts テーブル（DB管理）
2. leader_permissions に script_access カラム追加
3. ScriptController（index/show）
4. AppLayout.vue にスクリプトアイコン追加（使い方ガイドアイコンの隣）
5. Scripts/Index.vue / Scripts/Show.vue（動的コンポーネント）
6. Components/Scripts/ImageRenamer.vue（ブラウザ完結型リネームツール）

## 第1弾スクリプト: 画像ファイル一括リネーム
- slug: image-renamer
- 技術: File System Access API + PapaParse + xlsx
- CSV/Excel でIDとタイトルを読み込み、ローカルフォルダの画像ファイルを一括リネーム
- ブラウザ上で完結（サーバーに画像をアップロードしない）
- Undoマニフェスト（JSON）をダウンロードして元に戻せる

## 現在の進捗
SCRIPT_MANAGER1.md の進捗テーブルを確認してください。
どのフェーズまで完了しているかを確認し、次のタスクから作業を再開してください。

## 作業ルール
- CLAUDE.md を必ず参照
- Artisan はコンテナ内: docker compose exec laravel bash -lc "php artisan ..."
- Vue/JSファイル変更後は npm run build を実行
- 設計変更が必要な場合は先に説明して確認を取ること
- 質問は1つずつ行うこと
```

---

## 設計サマリー（Claude Code が知っておくべき情報）

### ファイル対応表

| ファイル | Phase | 状態 |
|---------|-------|------|
| `database/migrations/xxxx_create_scripts_table.php` | 1 | |
| `database/migrations/xxxx_add_script_access_to_leader_permissions.php` | 1 | |
| `app/Models/Script.php` | 1 | |
| `app/Models/LeaderPermission.php` | 1 | 変更 |
| `app/Http/Controllers/ScriptController.php` | 1 | |
| `routes/web.php` | 1 | 変更 |
| `app/Http/Middleware/HandleInertiaRequests.php` | 1 | 変更: canAccessScripts追加 |
| `app/Http/Controllers/Admin/LeaderPermissionController.php` | 1 | 変更: script_access対応 |
| `resources/js/Pages/Admin/LeaderPermissions/Edit.vue` | 1 | 変更: script_accessトグル |
| `resources/js/layouts/AppLayout.vue` | 2 | 変更: スクリプトアイコン |
| `resources/js/Pages/Scripts/Index.vue` | 2 | |
| `resources/js/Pages/Scripts/Show.vue` | 2 | |
| `resources/js/Components/Scripts/ImageRenamer.vue` | 3 | |

### 権限確認の実装パターン

```php
// ScriptController での権限チェック
$user = Auth::user();
if (in_array($user->user_role, ['superadmin', 'admin'])) { /* OK */ }
elseif ($user->user_role === 'leader') {
    $ok = LeaderPermission::where('user_id', $user->id)->value('script_access');
    if (!$ok) abort(403);
} else {
    abort(403);
}
```

### HandleInertiaRequests での共有データ（auth.user に追加）

```php
'canAccessScripts' => (function() use ($request) {
    $user = $request->user();
    if (!$user) return false;
    if (in_array($user->user_role, ['superadmin', 'admin'])) return true;
    if ($user->user_role === 'leader') {
        return (bool)(LeaderPermission::where('user_id', $user->id)->value('script_access') ?? false);
    }
    return false;
})(),
```

### AppLayout.vue でのアイコン表示条件（scriptの後に追加）

```js
const canAccessScripts = computed(() =>
    page.props.auth?.user?.canAccessScripts ?? false
);
```

### ImageRenamer の状態管理

```js
const state = ref('idle'); // idle | csv_loaded | folder_selected | previewing | executing | done
const csvRecords = ref([]); // { id: string, title: string }[]
const dirHandle = ref(null); // FileSystemDirectoryHandle
const candidates = ref([]); // { original, renamed, status, warning }[]
const results = ref([]); // 実行結果
```

---
