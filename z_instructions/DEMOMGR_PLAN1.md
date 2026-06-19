# DEMOMGR_PLAN1.md — デモページ管理機能 設計書

> 作成: 2026-06-19
> 対象: SuperAdmin タブメニューへの「デモページ管理」追加

---

## 1. 概要

SuperAdmin がブラウザからデモページのアクセス制御を管理できるようにする。
既存の `.env` ベースの NSystem ゲスト認証を DB ベースに置き換え、
将来複数のデモページを追加できる汎用設計にする。

---

## 2. DBスキーマ

```php
// demo_pages
Schema::create('demo_pages', function (Blueprint $table) {
    $table->id();
    $table->string('name', 200);           // 管理用名前
    $table->string('slug', 100)->unique(); // URLキー（例: n-demo）
    $table->text('description')->nullable();
    $table->string('password', 255);       // bcrypt ハッシュ
    $table->timestamp('expires_at')->nullable(); // 公開期限（null=無期限）
    $table->boolean('is_active')->default(true);
    $table->foreignId('created_by')->constrained('users');
    $table->timestamps();
});

// demo_page_emails
Schema::create('demo_page_emails', function (Blueprint $table) {
    $table->id();
    $table->foreignId('demo_page_id')->constrained('demo_pages')->cascadeOnDelete();
    $table->string('email', 200);
    $table->string('label', 100)->nullable(); // 担当者メモ
    $table->timestamps();
    $table->unique(['demo_page_id', 'email']);
});
```

---

## 3. モデル

- `app/Models/DemoPage.php`（namespace: `App\Models`）
  - `emails()` hasMany → DemoPageEmail
  - `isAccessible()` メソッド: `is_active && (expires_at is null || expires_at->isFuture())`
- `app/Models/DemoPageEmail.php`（namespace: `App\Models`）
  - `demoPage()` belongsTo → DemoPage

---

## 4. SuperAdmin UI

### ルート（web.php の superadmin グループに追加）

```
GET    /superadmin/demo-pages                   superadmin.demo_pages.index
GET    /superadmin/demo-pages/{demoPage}        superadmin.demo_pages.show
PATCH  /superadmin/demo-pages/{demoPage}        superadmin.demo_pages.update      基本情報
PATCH  /superadmin/demo-pages/{demoPage}/password  superadmin.demo_pages.update_password
POST   /superadmin/demo-pages/{demoPage}/emails    superadmin.demo_pages.emails.store
DELETE /superadmin/demo-pages/{demoPage}/emails/{email}  superadmin.demo_pages.emails.destroy
```

### コントローラー

`app/Http/Controllers/SuperAdmin/DemoPagesController.php`

### Vueページ

```
resources/js/Pages/SuperAdmin/DemoPages/
  Index.vue   一覧（name / slug / is_active / expires_at / メール数）
  Show.vue    詳細（3セクション: 基本情報 / パスワード / メールアドレス）
```

---

## 5. メール通知

### Mailable

`app/Mail/DemoPageUpdated.php`
`resources/views/emails/demo_page_updated.blade.php`

### 送信タイミング

以下の操作後に SuperAdmin ロール全ユーザーへ送信:
- パスワード変更
- メールアドレス追加
- メールアドレス削除
- is_active 変更
- expires_at 変更

### フラッシュメッセージ

```
「設定を保存しました。SuperAdmin にメールを送信しました。」
```

ローカル環境では `MAIL_MAILER=log` のためログに記録される。
本番では `.env` の MAIL_MAILER を適切に設定すること。

---

## 6. ゲスト認証の変更

### GuestAuth ミドルウェア（app/Http/Middleware/NSystem/GuestAuth.php）

変更前: `.env` の固定値と比較
変更後: `DemoPage::where('slug', $slug)` でDB参照

middleware パラメータでスラッグを受け取る:
```php
public function handle(Request $request, Closure $next, string $slug = 'n-demo'): Response
```

ルート側:
```php
->middleware([GuestAuth::class . ':n-demo'])
```

### セッションキー変更

変更前: `nsystem_guest_auth`（bool）
変更後: `nsystem_demo_auth.{slug}`（bool）

将来複数ページに対応:
```php
session(['nsystem_demo_auth.n-demo' => true]);
session(['nsystem_demo_auth.another-demo' => true]);
```

### GuestAuthController

- `login()`: DemoPage::where('slug', $for) でページ取得 → emails チェック → password チェック
- `showLogin()`: `?for=n-demo` クエリパラメータで slug を受け取る
- `logout()`: `nsystem_demo_auth.{slug}` を forget

### ログイン画面

`resources/views/n_system/guest/login.blade.php`
- hidden: `<input type="hidden" name="for" value="{{ request('for', 'n-demo') }}">`

---

## 7. Seeder（移行用）

`database/seeders/DemoPageSeeder.php`
- `n-demo` スラッグで DemoPage を登録（.env の認証情報を使って初期パスワードをセット）
- SuperAdmin ユーザー（id=1 または role=superadmin の最初のユーザー）を created_by に設定
- 実行: `php artisan db:seed --class=DemoPageSeeder --force`

---

## 8. フェーズ別タスク

| # | タスク | ファイル |
|---|---|---|
| 1 | Migration 2本 | `2026_06_19_200001_*` / `2026_06_19_200002_*` |
| 2 | Model 2本 | `DemoPage.php` / `DemoPageEmail.php` |
| 3 | Mailable + view | `DemoPageUpdated.php` / `emails/demo_page_updated.blade.php` |
| 4 | DemoPagesController | `SuperAdmin/DemoPagesController.php` |
| 5 | Vue Index + Show | `SuperAdmin/DemoPages/*.vue` |
| 6 | タブ追加 | `SuperAdminNavigationTabs.vue` |
| 7 | ルート追加 | `routes/web.php` |
| 8 | GuestAuth 更新 | middleware / controller / view / nsystem.php |
| 9 | Seeder 実行 | DemoPageSeeder |
| 10 | build + 動作確認 | - |

---

## 9. 変更ファイル一覧

### 新規（12本）

```
database/migrations/2026_06_19_200001_create_demo_pages_table.php
database/migrations/2026_06_19_200002_create_demo_page_emails_table.php
app/Models/DemoPage.php
app/Models/DemoPageEmail.php
app/Http/Controllers/SuperAdmin/DemoPagesController.php
resources/js/Pages/SuperAdmin/DemoPages/Index.vue
resources/js/Pages/SuperAdmin/DemoPages/Show.vue
app/Mail/DemoPageUpdated.php
resources/views/emails/demo_page_updated.blade.php
database/seeders/DemoPageSeeder.php
```

### 変更（6本）

```
app/Http/Middleware/NSystem/GuestAuth.php           DB参照 + slug パラメータ
app/Http/Controllers/NSystem/GuestAuthController.php  DB参照
resources/views/n_system/guest/login.blade.php       hidden[for] 追加
routes/nsystem.php                                   :n-demo パラメータ追加
resources/js/Components/Tabs/SuperAdminNavigationTabs.vue  タブ追加
routes/web.php                                       demo_pages ルート追加
```

---

## 10. 注意事項

- `NSYSTEM_GUEST_EMAIL` / `NSYSTEM_GUEST_PASSWORD` は Seeder 実行後に `.env` から削除する
- DemoPageSeeder の初期パスワードは `config('nsystem.guest.password')` から読む
- SuperAdmin へのメール送信は `User::where('user_role', 'superadmin')->get()` で全 SuperAdmin に送る
- ローカルでは `MAIL_MAILER=log` なので `/storage/logs/laravel.log` で確認できる
