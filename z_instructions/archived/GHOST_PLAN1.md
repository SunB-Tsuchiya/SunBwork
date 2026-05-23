# SunBWork 機能計画書 — ゴーストユーザー（テストユーザー）機能 第1版
作成日: 2026-05-13

---

## 背景・目的

Coordinator がユーザーとのやり取り（ジョブ割り当て・進行表・完了操作）を実際のフローで
シミュレートできるよう、ゴーストユーザー（テストユーザー）機能を実装する。

**ゴーストユーザーの特徴:**
- 作成した Coordinator にのみ紐づき、正規のユーザーリストを汚さない
- 別ログインページは作らない（Coordinator セッション内でのセッション切り替え方式）
- 14日間で自動削除（DBの関連データも含む）
- 利用可能機能は MyJobBox・JobBox・割当完了のみ

---

## 確定仕様

| 項目 | 仕様 |
|------|------|
| 作成権限 | Coordinator のみ（自分専用、上限 1 アカウント） |
| 有効期間 | 作成から 14 日間（自動削除） |
| 利用可能機能 | MyJobBox・JobBox・割当完了・進行表からの自己割当 |
| 可視性 | 作成した Coordinator にのみ見える |
| ログイン方法 | Coordinator ダッシュボードからワンクリックでセッション切り替え |
| 割り当て UI | Coordinator の担当者選択に `[テスト]` ラベル付きで末尾表示 |
| 不可機能 | 日報・工数入力・カレンダー・チャット・通知・分析 |
| Admin/Leader | ユーザーリストに表示されない（Global Scope で除外） |

---

## DB 設計

### users テーブル追加カラム

| カラム名 | 型 | デフォルト | 説明 |
|---|---|---|---|
| `is_ghost` | boolean | false | ゴーストユーザーフラグ |
| `ghost_owner_id` | unsignedBigInteger (nullable) | null | 作成 Coordinator の user_id（FK→users.id, onDelete: set null） |
| `ghost_expires_at` | timestamp (nullable) | null | 有効期限（作成日 + 14 日） |

---

## フェーズ・タスク詳細

### フェーズ 1：DB・モデル基盤

#### G-01 マイグレーション
**対象ファイル:** `database/migrations/xxxx_add_ghost_columns_to_users_table.php`（新規）

```php
$table->boolean('is_ghost')->default(false)->after('remember_token');
$table->foreignId('ghost_owner_id')->nullable()->constrained('users')->nullOnDelete()->after('is_ghost');
$table->timestamp('ghost_expires_at')->nullable()->after('ghost_owner_id');
```

#### G-02 User モデル更新
**対象ファイル:** `app/Models/User.php`

- **Global Scope 追加:** 全クエリに `where('is_ghost', false)` を適用  
  → Admin・Leader・通知・AI などあらゆる箇所でゴーストが混入しない
- **例外スコープ:** `withGhosts()` を追加（Coordinator 切り替え時のみ使用）
- **リレーション:** `ghostOwner()` / `ghostUsers()`
- **キャスト:** `ghost_expires_at` → `datetime`

```php
protected static function booted(): void
{
    static::addGlobalScope('no_ghost', fn($q) => $q->where('is_ghost', false));
}

public function scopeWithGhosts($query) { return $query->withoutGlobalScope('no_ghost'); }
public function ghostOwner() { return $this->belongsTo(User::class, 'ghost_owner_id'); }
public function ghostUsers() { return $this->hasMany(User::class, 'ghost_owner_id'); }
```

---

### フェーズ 2：ゴーストユーザー作成・管理

#### G-03 GhostUserController（Coordinator 用）
**対象ファイル:** `app/Http/Controllers/Coordinator/GhostUserController.php`（新規）

| メソッド | ルート | 処理 |
|---|---|---|
| `store` | `POST /coordinator/ghost-users` | ゴーストユーザー作成（上限チェック・14日期限付き） |
| `destroy` | `DELETE /coordinator/ghost-users/{ghostUser}` | 手動削除（関連レコードも cascade） |
| `switch` | `POST /coordinator/ghost-users/{ghostUser}/switch` | セッション切り替え開始 |
| `exit` | `POST /coordinator/ghost/exit` | Coordinator セッション復帰 |

**作成時のデータ:**
```php
User::create([
    'name'             => 'テスト_' . auth()->user()->name,
    'email'            => 'ghost_' . Str::random(8) . '@ghost.local',
    'password'         => bcrypt(Str::random(32)),
    'role'             => 'user',
    'is_ghost'         => true,
    'ghost_owner_id'   => auth()->id(),
    'ghost_expires_at' => now()->addDays(14),
]);
```

#### G-04 Coordinator ダッシュボード UI
**対象ファイル:** `resources/js/Pages/Coordinator/GhostUsers/Index.vue`（新規）

- ゴーストユーザー一覧（名前・残り日数・ステータス）
- 「テストユーザーを作成」ボタン（既存アカウントがある場合は無効化）
- 「ゴーストとして操作する」ボタン
- 「削除」ボタン（手動削除）

---

### フェーズ 3：セッション切り替え

#### G-05 セッション切り替えロジック
**対象ファイル:** `GhostUserController@switch` / `GhostUserController@exit`

**切り替え開始:**
```php
session()->put('ghost_return_user_id', auth()->id());
Auth::loginUsingId($ghostUser->id);
return redirect()->route('user.my_job_box.index');
```

**復帰:**
```php
$returnId = session()->pull('ghost_return_user_id');
Auth::loginUsingId($returnId);
return redirect()->route('coordinator.project_jobs.index');
```

#### G-06 GhostUserMiddleware
**対象ファイル:** `app/Http/Middleware/GhostUserMiddleware.php`（新規）

- `is_ghost = true` のユーザーが MyJobBox・JobBox 以外のルートにアクセスした場合は 403
- `routes/web.php` でゴースト許可グループを定義し Middleware を適用

---

### フェーズ 4：フロントエンド

#### G-07 ゴーストモードバナー
**対象ファイル:** `resources/js/layouts/AppLayout.vue`

- `isGhostMode` prop を追加（コントローラーから Inertia で共有）
- ゴーストセッション中は画面最上部に固定バナー表示:  
  `テストモード中（テスト_○○として操作中）　[Coordinator に戻る]`
- 戻るボタンで `POST /coordinator/ghost/exit` を送信

#### G-08 割り当て UI — テストラベル付き表示
**対象ファイル:** ジョブ割り当てのユーザー選択コンポーネント（要調査）

- `is_ghost = true` のユーザーにはリスト末尾で `[テスト] テスト_○○` と表示
- ghost_owner_id が自分の Coordinator ID と一致するもののみ表示（他人のゴーストは見えない）

---

### フェーズ 5：自動削除

#### G-09 自動削除コマンド・スケジューラー
**対象ファイル:**
- `app/Console/Commands/DeleteExpiredGhostUsers.php`（新規）
- `routes/console.php`（スケジュール登録）

**削除手順（トランザクション内）:**
1. `ghost_expires_at < now()` のゴーストユーザーを取得
2. `project_job_assignments` の関連レコード削除
3. `events` の関連レコード削除
4. `users` レコード削除

```php
Schedule::command('ghost:cleanup')->daily();
```

---

### フェーズ 6：ゴースト権限拡張

#### G-10 GhostUserMiddleware 拡張 — 案件・進行表・自己割当アクセス許可
**対象ファイル:** `app/Http/Middleware/GhostUserMiddleware.php`

**背景:**
ゴーストユーザーが案件確認 → 進行表を開く → 自分でジョブを登録する、という
実際のユーザー操作フローをシミュレートできるよう `ALLOWED_ROUTES` を拡張する。

**データ制限（追加実装不要）:**
既存の User 向けコントローラーはすべて `teamMembers`・`user_id`・`sender_id` で
ログインユーザーに紐づくデータのみ返す実装になっているため、ゴーストユーザーは
チームに追加されている案件・進行表のみ自然に参照できる。

**追加する ALLOWED_ROUTES:**

| ルート名 | 説明 |
|---|---|
| `user.project_jobs.index` | 案件一覧 |
| `user.project_jobs.show` | 案件詳細 |
| `user.project_jobs.json` | 案件一覧 JSON（AJAX） |
| `user.project_jobs.progress_sheets_json` | 進行表一覧 JSON（AJAX） |
| `user.progress_sheets.show` | 進行表表示 |
| `user.project_jobs.assignments.create` | 自己割当フォーム（マイジョブ作成） |
| `user.project_jobs.assignments.store` | 自己割当保存 |
| `user.project_jobs.assignments.edit` | 自己割当編集フォーム |
| `user.project_jobs.assignments.update` | 自己割当更新 |
| `user.project_jobs.progress_sheets.link_job` | 進行表セルからのジョブ登録 |
| `user.project_jobs.assignments.schedule` | スケジュール確認 |
| `user.project_jobs.assignments.schedule.store` | スケジュール登録 |

**引き続き禁止（変更なし）:**
日報・工数入力・カレンダー・チャット・通知・分析

---

## 変更ファイル一覧

| ファイル | 変更種別 |
|---|---|
| `database/migrations/xxxx_add_ghost_columns_to_users_table.php` | 新規 |
| `app/Models/User.php` | 変更（Global Scope・リレーション追加） |
| `app/Http/Controllers/Coordinator/GhostUserController.php` | 新規 |
| `app/Http/Middleware/GhostUserMiddleware.php` | 新規 |
| `app/Console/Commands/DeleteExpiredGhostUsers.php` | 新規 |
| `routes/web.php` | 変更（ゴースト関連ルート追加） |
| `routes/console.php` | 変更（スケジュール登録） |
| `resources/js/layouts/AppLayout.vue` | 変更（ゴーストバナー追加） |
| `resources/js/Pages/Coordinator/GhostUsers/Index.vue` | 新規 |
| ジョブ割り当てユーザー選択コンポーネント（要調査） | 変更（テストラベル追加） |

---

## セキュリティ考慮事項

| 項目 | 対策 |
|---|---|
| 認証経路の増加なし | 別ログインページを作らない。既存 `/login` のみ |
| Admin・Leader への混入防止 | User モデル Global Scope で `is_ghost=false` を強制 |
| ゴーストの権限逸脱防止 | Middleware で MyJobBox・JobBox 以外を 403 |
| 外部メール誤送信防止 | メールアドレスを `@ghost.local` にし、Mailable のガード追加 |
| ゴーストセッション乗っ取り防止 | `ghost_return_user_id` はサーバー側セッションに保持（クライアント非公開） |
| データ残留防止 | 14日自動削除 + 手動削除で関連テーブルも cascade |
