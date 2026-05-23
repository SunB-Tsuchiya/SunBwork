# SCRIPT_PLAN1.md — スクリプトページ機能 詳細仕様

作成日: 2026-05-16  
担当: Claude Code

---

## 1. 機能概要

`/scripts` 配下に「社内向けスクリプトツール」のセクションを設ける。  
- `scripts/index` : 利用可能なスクリプトの一覧（名前・説明）  
- `scripts/{slug}` : 各スクリプトの実行画面  
- ヘッダーにアイコンを追加（「使い方ガイド」アイコンの横）  
- **閲覧権限**: superadmin/admin は常に可、leader は `leader_permissions.script_access` が true の場合のみ、その他ロールは不可

---

## 2. 対象スクリプト（第1弾）

| slug | 名前 | component_key | 説明 |
|------|------|---------------|------|
| `image-renamer` | 画像ファイル一括リネーム | `ImageRenamer` | CSVリストとIDで画像ファイルを一括リネーム |

※ 将来的に追加可能な構造にする（DB管理）

---

## 3. DB設計

### 3-1. `scripts` テーブル（新規）

```
id              bigint PK
name            string(100)      スクリプト表示名
slug            string(100) unique  URLスラッグ
description     text             説明文（indexに表示）
component_key   string(100)      Vueコンポーネント識別子
sort_order      int default 0    表示順
is_active       boolean default true
created_at, updated_at
```

初期シードデータ（migration に同梱）:
```php
Script::create([
    'name'          => '画像ファイル一括リネーム',
    'slug'          => 'image-renamer',
    'description'   => 'CSVまたはExcelのIDとタイトルリストをもとに、画像ファイルを一括でリネームします。',
    'component_key' => 'ImageRenamer',
    'sort_order'    => 1,
    'is_active'     => true,
]);
```

### 3-2. `leader_permissions` テーブル（カラム追加）

```
ALTER TABLE leader_permissions ADD script_access boolean DEFAULT false;
```

- 既存レコードは false（デフォルト: 許可なし）
- Admin/SuperAdmin が個別にON/OFFする

---

## 4. 権限設計

| ロール | アクセス条件 |
|--------|-------------|
| superadmin | 常に可 |
| admin | 常に可 |
| leader | `leader_permissions.script_access = true` の場合のみ |
| その他 | 不可（403） |

### Inertia 共有データへの追加

`HandleInertiaRequests.php` の `auth.user` に `canAccessScripts` フラグを追加:

```php
'canAccessScripts' => (function() use ($request) {
    $user = $request->user();
    if (!$user) return false;
    if (in_array($user->user_role, ['superadmin', 'admin'])) return true;
    if ($user->user_role === 'leader') {
        return LeaderPermission::where('user_id', $user->id)
            ->value('script_access') ?? false;
    }
    return false;
})(),
```

AppLayout.vue でアイコン表示制御:
```js
const canAccessScripts = computed(() =>
    page.props.auth?.user?.canAccessScripts ?? false
);
```

---

## 5. ルーティング設計

`routes/web.php` のガイドルート直後に追加（認証必須ミドルウェア内）:

```php
// スクリプト
Route::prefix('scripts')->name('scripts.')->group(function () {
    Route::get('/', [App\Http\Controllers\ScriptController::class, 'index'])->name('index');
    Route::get('/{script:slug}', [App\Http\Controllers\ScriptController::class, 'show'])->name('show');
});
```

---

## 6. コントローラー設計

### `app/Http/Controllers/ScriptController.php`

```php
class ScriptController extends Controller
{
    private function checkAccess(): void
    {
        $user = Auth::user();
        $role = $user->user_role;
        if (in_array($role, ['superadmin', 'admin'])) return;
        if ($role === 'leader') {
            $ok = LeaderPermission::where('user_id', $user->id)->value('script_access');
            if ($ok) return;
        }
        abort(403, 'スクリプトへのアクセス権限がありません。');
    }

    public function index()
    {
        $this->checkAccess();
        $scripts = Script::where('is_active', true)->orderBy('sort_order')->get();
        return Inertia::render('Scripts/Index', ['scripts' => $scripts]);
    }

    public function show(Script $script)
    {
        $this->checkAccess();
        if (!$script->is_active) abort(404);
        return Inertia::render('Scripts/Show', ['script' => $script]);
    }
}
```

---

## 7. モデル設計

### `app/Models/Script.php`

```php
class Script extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'component_key', 'sort_order', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];
}
```

---

## 8. フロントエンド設計

### 8-1. AppLayout.vue — スクリプトアイコン追加

「使い方ガイド」アイコンの直後、Settings Dropdown の前に挿入:

```vue
<!-- スクリプト -->
<div v-if="canAccessScripts" class="group relative">
    <Link :href="route('scripts.index')" class="flex h-8 w-8 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-gray-700">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <!-- Command/Terminal アイコン -->
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0021 18V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v12a2.25 2.25 0 002.25 2.25z" />
        </svg>
    </Link>
    <div class="pointer-events-none absolute right-0 top-9 z-50 w-44 rounded-md bg-gray-800 px-3 py-2 text-xs text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100">
        <p class="font-medium">スクリプト</p>
        <p class="text-gray-300">業務ツール・スクリプト</p>
    </div>
</div>
```

### 8-2. Scripts/Index.vue

- AppLayout 使用
- スクリプトカード一覧表示（Guide/Index.vue と同じカードスタイル）
- 各カードから `scripts.show` へ遷移

### 8-3. Scripts/Show.vue

- AppLayout 使用
- `script.component_key` に基づいて動的に Vue コンポーネントをロード
- コンポーネントマップ方式:

```js
const componentMap = {
    'ImageRenamer': defineAsyncComponent(() =>
        import('@/Components/Scripts/ImageRenamer.vue')
    ),
};
const CurrentTool = computed(() => componentMap[props.script.component_key] ?? null);
```

### 8-4. LeaderPermissions/Edit.vue — `script_access` 追加

`permItems` 配列に以下を追加:
```js
{ key: 'script_access', label: 'スクリプトツール' },
```

---

## 9. ImageRenamer コンポーネント仕様

### 9-1. 使用技術

| 技術 | 目的 | インストール要否 |
|------|------|----------------|
| File System Access API | フォルダ選択・ファイル列挙・リネーム | 不要（Chrome/Edge 内蔵） |
| PapaParse | CSV 解析 | 要: `npm install papaparse` |
| xlsx (SheetJS) | Excel (.xlsx) 解析 | 要: `npm install xlsx` |
| Vue 3 (Composition API) | UI | 既存 |

**ブラウザ要件**: Chrome 123+ または Edge 123+（File System Access API + `FileSystemHandle.move()` が必要）

### 9-2. 状態遷移

```
idle
  ↓ CSVまたはExcelファイルを選択
csv_loaded（解析済みレコード一覧）
  ↓ フォルダを選択
folder_selected（ファイル列挙済み）
  ↓ 照合・バリデーション
previewing（変更候補一覧・警告表示）
  ↓ 「実行」ボタン押下 → 確認ダイアログ
executing（進捗バー表示）
  ↓ 完了
done（結果サマリ・Undoマニフェストダウンロード）
```

### 9-3. CSV/Excel 入力仕様

- 対応拡張子: `.csv`, `.xlsx`
- CSV 文字コード: UTF-8 / BOM付きUTF-8 / Shift_JIS（自動判定）
- CSV 区切り文字: カンマ、セミコロン（自動判定）
- ID列: `id`, `ID`, `番号` のいずれかで認識（大文字小文字無視）
- タイトル列: `title`, `タイトル`, `名前`, `name` のいずれかで認識
- ヘッダー行あり/なしの両対応（PapaParse の `header: true` + カラム名マッピング）
- 先頭ゼロ保持（文字列として扱う）
- 重複IDは警告表示（後勝ち or スキップを選択）
- タイトル空欄: スキップ（警告）

### 9-4. ファイル照合仕様

- ファイル名のベース名（拡張子除く）= ID として照合
- 大文字小文字の正規化（小文字で比較）
- 前後空白除去
- 先頭ゼロの正規化（`"001"` と `"1"` は別扱い。文字列として一致させる）
- 対象拡張子ホワイトリスト: `.jpg`, `.jpeg`, `.png`, `.tif`, `.tiff`, `.webp`, `.gif`, `.bmp`
- サブフォルダは対象外（フラットな1階層のみ）
- 隠しファイル（`.` 始まり）はスキップ
- 同一IDに複数ファイル: 警告表示してスキップ

### 9-5. リネームルール

```
新ファイル名 = {ID}_{タイトル}.{元拡張子（小文字）}
```

**禁止文字の置換**（OS安全なファイル名のため）:
- `\ / : * ? " < > |` → `_`
- 改行・タブ → 除去
- 連続する `__` → `_`（正規化）
- 末尾の空白・ピリオド → 除去
- Unicode正規化: NFC に統一

**長さ制限**:
- ファイル名全体が255バイトを超える場合: タイトル部分を切り詰め

**同名衝突**:
- リネーム後のファイル名が既存ファイルと衝突する場合はスキップ（警告）

### 9-6. 安全設計

- **Dry-run** 先行: 実行前に必ずプレビュー表示
- **件数サマリ**: 成功予定/スキップ/エラー予定の3種類を表示
- **実行確認ダイアログ**: 「{n}件のファイルをリネームします。元に戻す場合は…」
- **Undoマニフェスト**: 実行完了後に `rename_manifest_YYYYMMDD_HHmmss.json` をダウンロード
  - 形式: `[{ "original": "001.jpg", "renamed": "001_タイトル.jpg", "timestamp": "..." }]`
- **途中失敗**: 失敗したファイルをリストアップして継続（全件停止しない）
- **既リネーム済みファイルの再処理**: リネーム後の名前と一致する場合はスキップ

### 9-7. リネーム実行（File System Access API）

```js
// rename実装
async function renameFile(dirHandle, oldName, newName) {
    const fileHandle = await dirHandle.getFileHandle(oldName);
    if (typeof fileHandle.move === 'function') {
        // Chrome 123+ - ネイティブ移動（最速）
        await fileHandle.move(newName);
    } else {
        // フォールバック: コピー＆削除
        const file = await fileHandle.getFile();
        const newHandle = await dirHandle.getFileHandle(newName, { create: true });
        const writable = await newHandle.createWritable();
        await writable.write(await file.arrayBuffer());
        await writable.close();
        await dirHandle.removeEntry(oldName);
    }
}
```

### 9-8. エラーハンドリング

| エラー | ユーザー向け表示 | 継続可否 |
|--------|-----------------|---------|
| CSV読込失敗 | 「ファイルを読み込めませんでした。文字コードを確認してください。」 | 停止 |
| 必須列不足 | 「ID列またはタイトル列が見つかりません。」 | 停止 |
| フォルダアクセス拒否 | 「フォルダへのアクセスが拒否されました。」 | 停止 |
| 個別ファイルリネーム失敗 | 「{ファイル名}: リネームに失敗しました」 | 継続 |
| 同名衝突 | 「{ファイル名}: 同名のファイルが存在するためスキップしました」 | 継続 |
| ブラウザ非対応 | 「このツールはChrome/Edgeでのみ動作します。」 | 機能無効化 |

### 9-9. ログ（ローカル保存）

実行後にダウンロードできる JSON:
```json
{
    "executed_at": "2026-05-16T10:30:00+09:00",
    "user_name": "H. Tsuchiya",
    "source_file": "photo_list.csv",
    "total": 120,
    "success": 118,
    "skipped": 2,
    "errors": 0,
    "results": [
        { "original": "001.jpg", "renamed": "001_北信越の特急あさま_車窓から.jpg", "status": "success" },
        ...
    ]
}
```

---

## 10. 変更ファイル一覧

### 新規作成

| ファイル | 内容 |
|---------|------|
| `database/migrations/xxxx_create_scripts_table.php` | scriptsテーブル作成＋初期シード |
| `database/migrations/xxxx_add_script_access_to_leader_permissions.php` | script_accessカラム追加 |
| `app/Http/Controllers/ScriptController.php` | index/showアクション |
| `app/Models/Script.php` | Scriptモデル |
| `resources/js/Pages/Scripts/Index.vue` | スクリプト一覧ページ |
| `resources/js/Pages/Scripts/Show.vue` | スクリプト実行ページ（動的コンポーネント） |
| `resources/js/Components/Scripts/ImageRenamer.vue` | 画像リネームツール本体 |

### 修正

| ファイル | 変更内容 |
|---------|---------|
| `app/Models/LeaderPermission.php` | script_access追加 |
| `app/Http/Controllers/Admin/LeaderPermissionController.php` | script_access対応 |
| `resources/js/Pages/Admin/LeaderPermissions/Edit.vue` | script_accessトグル追加 |
| `app/Http/Middleware/HandleInertiaRequests.php` | canAccessScriptsフラグ追加 |
| `resources/js/layouts/AppLayout.vue` | スクリプトアイコン追加 |
| `routes/web.php` | scriptsルート追加 |

---

## 11. フェーズ別実装計画

### Phase 1: 基盤（DB・ルート・権限）
- migrations作成・実行
- Script/LeaderPermissionモデル更新
- ScriptController作成
- routes/web.php更新
- HandleInertiaRequests更新
- LeaderPermissionController更新・Edit.vue更新

### Phase 2: UI（アイコン・一覧・showページ）
- AppLayout.vue スクリプトアイコン追加
- Scripts/Index.vue 作成
- Scripts/Show.vue 作成（動的コンポーネント）

### Phase 3: ImageRenamerツール本体
- Phase 3a: CSV/Excel解析（PapaParse + xlsx）
- Phase 3b: フォルダ選択 + ファイル列挙 + 照合
- Phase 3c: プレビューテーブル表示
- Phase 3d: リネーム実行 + Undoマニフェスト生成・ダウンロード

### Phase 4: 品質・エッジケース
- エラーハンドリング強化
- Shift-JIS対応（TextDecoder API）
- 進捗バー表示
- ブラウザ非対応時のフォールバックUI

---

## 12. 外部ライブラリ

```bash
npm install papaparse xlsx
```

PapaParse: CSV解析  
xlsx (SheetJS): Excel解析

---

## 13. 要確認事項（実装前に確定済み）

- [x] 権限方式: leader_permissions に script_access カラム追加（個別制御）
- [x] showページ: ツール実行画面を埋め込む
- [x] スクリプトデータ管理: DB管理
- [ ] **スクリプトのDB管理画面（Admin向けCRUD）は第1版では省略してよいか？** → シード固定で問題なければPhase1を省略可
- [ ] **npm install（papaparse, xlsx）の実行タイミング** → Phase3開始前

---

## 14. Genspark設計との対応

newscript.md の推奨方式「ブラウザ完結型」を採用。  
主要設計（照合仕様・リネームルール・安全設計・Undo）はnewscript.mdの仕様をベースに、  
このサイトの技術スタック（Vue3/Inertia/Vite）および制約（さくらサーバー制限）に合わせて調整済み。

---
