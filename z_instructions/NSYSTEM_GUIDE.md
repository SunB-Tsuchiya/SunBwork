# NSYSTEM_GUIDE.md — NSystem デモ機能 ガイド

> 作成: 2026-06-19  
> 対象: Claude（AIアシスタント）向けリファレンス

---

## 1. NSystem とは

SunBwork に追加した**クライアント向け入試データデモ機能**。  
既存の SunBwork 業務管理システム（案件・日報・工数等）とは**完全に独立**した構成になっている。

**目的:** 中学入試問題をDB化した状態でどう見えるか・全文検索できるかをクライアントに見せるデモ。  
**将来:** 不要になったら丸ごと削除する前提で設計されている。削除手順は本ファイルの末尾にある。

---

## 2. データの出所

Windows ワークスペース `C:\Users\W229\Desktop\N_DBSystem\` で生成した JSON/PNG を SunBwork に取り込んでいる。

| 元ファイル | 取り込み先 |
|---|---|
| `output/*.json`（1352件） | `storage/app/private/n_import/*.json` |
| `output/schools_index.json` | `storage/app/private/n_import/schools_index.json` |
| `output/images/*.png`（4485枚） | `public/n_images/*.png` |
| `output/css/style.css` | `public/n_sample.css` |

これらのファイルは `.gitignore` に登録済みで git 管理外。  
再取り込みが必要な場合は `php artisan n:import --force` を実行する（後述）。

---

## 3. ファイル構成

NSystem に属するファイルは以下のディレクトリに**集約**されている。削除時はこの単位で消す。

```
# コントローラー
app/Http/Controllers/NSystem/
  NdemoController.php          デモページ（学校一覧・問題表示・検索）
  GuestAuthController.php      ゲストログイン/ログアウト

# 検索入力検証・サービス
app/Http/Requests/NSystem/
  NQuestionSearchRequest.php   検索条件の正規化・バリデーション
app/Services/NSystem/
  NQuestionSearchService.php   厳密検索・絞り込み・スニペット・ページング

# ミドルウェア
app/Http/Middleware/NSystem/
  GuestAuth.php                認証チェック（ゲストセッション or Sanctum認証）

# モデル
app/Models/NSystem/
  NSchool.php                  n_schools テーブル
  NQuestionsDaimon.php         n_questions_daimon テーブル
  NAnswersDaimon.php           n_answers_daimon テーブル

# コンフィグ
config/nsystem.php             ゲスト認証情報（.envから読む）

# ルート
routes/nsystem.php             NSystem専用ルート定義

# ビュー
resources/views/n_system/
  demo/
    layout.blade.php           共通レイアウト（ヘッダー・ログアウトボタン）
    index.blade.php            学校一覧（カテゴリ別グリッド）
    school.blade.php           科目タブ + 大問body_html描画
    search.blade.php           旧全文検索画面（現在は未使用・移行確認後に削除可）
  guest/
    login.blade.php            ゲスト専用ログイン画面

# Vue / Inertia（検索画面）
resources/js/
  layouts/NSystemDemoLayout.vue
  Pages/NSystem/Search.vue
  Components/NSystem/
    SearchFilters.vue
    SearchResultCard.vue
    SearchPagination.vue

# Artisanコマンド
app/Console/Commands/NImport.php   n:import コマンド
```

### SunBwork 本体側への変更点（最小限）

NSystem を追加するにあたり SunBwork 本体で変更したファイルは **2箇所のみ**:

| ファイル | 変更内容 |
|---|---|
| `routes/web.php` | `require __DIR__ . '/nsystem.php';` を1行追加 |
| `.env` | `NSYSTEM_GUEST_EMAIL` / `NSYSTEM_GUEST_PASSWORD` を追加 |

---

## 4. データベース

`n_` プレフィックスで既存テーブルと名前衝突しないようにしている。

| テーブル | 内容 | 件数（2024年度） |
|---|---|---|
| `n_schools` | 学校マスタ（code, year, name, category） | 159件 |
| `n_questions_daimon` | 問題の大問単位HTML・テキスト | 2,247件 |
| `n_answers_daimon` | 解答の大問単位HTML・テキスト | 2,376件 |

マイグレーションファイル:
- `2026_06_19_100001_create_n_schools_table.php`
- `2026_06_19_100002_create_n_questions_daimon_table.php`
- `2026_06_19_100003_create_n_answers_daimon_table.php`
- `2026_06_19_100004_rebuild_n_daimon_fulltext_with_ngram.php`（MySQL 8.0 ngram全文検索）

---

## 5. ルート一覧

```
GET  /guest/login    ゲスト専用ログイン画面（認証不要）
POST /guest/login    ゲストログイン処理（認証不要）
POST /guest/logout   ゲストログアウト（認証不要）

GET  /n-demo                   学校一覧         ← GuestAuth必須
GET  /n-demo/school/{id}       問題・解答表示   ← GuestAuth必須
GET  /n-demo/search?q=...      全文検索         ← GuestAuth必須
GET  /n-demo/search/results     リアルタイム検索JSON API ← GuestAuth必須
```

`/n-demo/*` は**未認証でアクセスすると `/guest/login` にリダイレクト**される。

---

## 5.1 全文検索の仕様

検索画面はNSystem専用レイアウトを使うInertia/Vueページ。SunBWork社内用の `AppLayout` は、社内ナビゲーションや通知機能を含むため使用しない。

### 検索モード

| モード | 動作 |
|---|---|
| そのまま含む（既定） | 入力文字列が連続して問題本文に存在するものだけを返す |
| すべての語 | 空白区切りした全キーワードが存在するものを返す |
| いずれかの語 | 空白区切りしたキーワードを1つ以上含むものを返す |

MySQL ngram FULLTEXTは候補抽出に使用するが、最終的なヒット判定はエスケープ済みのリテラルLIKE条件で行う。このため、「平安時代」で「大正時代」が返るようなngramの一部一致を防止できる。

### 画面機能

- 入力後300msのリアルタイム検索
- 日本語IME変換中の不要な検索を抑止
- 新しい検索時に古いHTTPリクエストをキャンセル
- 科目・学校・カテゴリ絞り込み
- 20件ページング
- 検索条件をURLに保持
- 一致箇所を中心に前後約100文字を表示
- 結果から対象の学校・科目・大問へ直接移動
- 検索窓は検索方法カードの直前に表示
- 対象大問へ移動した際、引き継いだ検索語を問題本文内でハイライト

JSON APIは検索結果に `body_html` を含めず、表示に必要な学校情報、科目、大問番号、安全なスニペットだけを返す。

### 検索テスト

```bash
docker compose exec laravel bash -lc "php artisan test tests/Unit/NSystem/NQuestionSearchServiceTest.php tests/Feature/NSystem/NQuestionSearchTest.php"
```

---

## 6. ゲスト認証の仕組み

### 認証フロー

```
クライアント → /n-demo
  ↓ GuestAuth ミドルウェアがチェック
  ├─ Sanctum認証済みスタッフ → そのまま通す
  ├─ session('nsystem_guest_auth') === true → そのまま通す
  └─ どちらでもない → /guest/login にリダイレクト

/guest/login でメール+パスワードを入力
  ↓ GuestAuthController@login
  → 認証成功: session に nsystem_guest_auth=true をセット → /n-demo へ
  → 認証失敗: エラー表示（ブルートフォース対策: 1分5回まで）
```

### 認証情報の管理

```
.env:
  NSYSTEM_GUEST_EMAIL=guest@n-demo.local
  NSYSTEM_GUEST_PASSWORD=<環境ごとに安全な値を設定>

config/nsystem.php:
  config('nsystem.guest.email')    → .envから読む
  config('nsystem.guest.password') → .envから読む
```

**パスワードを変更する場合:** `.env` の `NSYSTEM_GUEST_PASSWORD` を書き換えて  
`php artisan config:clear` を実行するだけでよい（DB変更不要）。

### ゲストのアクセス制限

- ゲストセッションは `/n-demo/*` にしか効果がない
- ゲストが `/dashboard` など通常ページに行っても Sanctum の `auth:sanctum` ミドルウェアが弾く
- ゲストは SunBwork の業務データに一切アクセスできない

---

## 7. import コマンド

```bash
# 通常実行（確認プロンプトあり）
docker compose exec laravel bash -lc "php artisan n:import"

# 確認スキップ
docker compose exec laravel bash -lc "php artisan n:import --force"
```

処理内容:
1. `storage/app/private/n_import/schools_index.json` → `n_schools` に upsert
2. `storage/app/private/n_import/*.json` を走査（ファイル名パターン: `{code4}{year4}__{Q|A}{Ko|Sa|Sh|Ri}.json`）
3. `body_html` 内の `src="images/` を `src="/n_images/` に置換
4. `n_questions_daimon` または `n_answers_daimon` に upsert

---

## 8. NSystem を削除する手順

⚠️ **削除は不可逆。実行前にユーザーに確認すること。**

### Step 1: routes/web.php から1行削除

```php
// この行を削除する
require __DIR__ . '/nsystem.php';
```

### Step 2: NSystem ファイル群を削除

```bash
rm -rf app/Http/Controllers/NSystem/
rm -rf app/Http/Requests/NSystem/
rm -rf app/Http/Middleware/NSystem/
rm -rf app/Models/NSystem/
rm -rf app/Services/NSystem/
rm -rf resources/views/n_system/
rm -rf resources/js/Components/NSystem/
rm -rf resources/js/Pages/NSystem/
rm     resources/js/layouts/NSystemDemoLayout.vue
rm -rf tests/Feature/NSystem/
rm -rf tests/Unit/NSystem/
rm     config/nsystem.php
rm     routes/nsystem.php
rm     app/Console/Commands/NImport.php
```

### Step 3: DBテーブルとマイグレーションを削除

```bash
# マイグレーション rollback（n_ テーブル4本が落ちる）
docker compose exec laravel bash -lc "php artisan migrate:rollback --step=4 --force"

# マイグレーションファイル削除
rm database/migrations/2026_06_19_100001_create_n_schools_table.php
rm database/migrations/2026_06_19_100002_create_n_questions_daimon_table.php
rm database/migrations/2026_06_19_100003_create_n_answers_daimon_table.php
rm database/migrations/2026_06_19_100004_rebuild_n_daimon_fulltext_with_ngram.php
```

### Step 4: 大量ファイルを削除

```bash
rm -rf public/n_images/
rm     public/n_sample.css
rm -rf storage/app/private/n_import/
```

### Step 5: .env から認証情報を削除

`.env` から以下の3行を削除する:
```
# NSystem デモゲスト認証
NSYSTEM_GUEST_EMAIL=guest@n-demo.local
NSYSTEM_GUEST_PASSWORD=<設定済みの値>
```

### Step 6: .gitignore のエントリを削除

`.gitignore` から以下を削除する:
```
# NDBSAMPLE デモ用大量ファイル（git管理外）
/storage/app/private/n_import/
/public/n_images/
/public/n_sample.css
```

### Step 7: キャッシュクリア・ビルド

```bash
docker compose exec laravel bash -lc "php artisan config:clear && php artisan cache:clear && php artisan route:clear && php artisan view:clear"
npm run build
```

### Step 8: z_instructions からこのファイルを削除

```bash
rm z_instructions/NSYSTEM_GUIDE.md
```

---

## 9. 将来のデモ・ゲストページ追加について

新しいデモやゲスト向けページを追加する場合のパターン:

### ゲスト認証を流用する

`GuestAuth` ミドルウェアは「`nsystem_guest_auth` セッションがある OR Sanctum認証済み」でパスを通す。  
新しいデモルートを `routes/nsystem.php` に追加して同じミドルウェアを当てるだけで  
既存のゲストアカウントでアクセスできるようになる。

```php
// routes/nsystem.php に追加するだけ
Route::prefix('another-demo')->name('another-demo.')->middleware(GuestAuth::class)->group(function () {
    Route::get('/', [AnotherDemoController::class, 'index'])->name('index');
});
```

### 別のゲストアカウントが必要な場合

`.env` に別の環境変数を追加し、`config/nsystem.php` に項目を追加する。  
`GuestAuthController` を拡張するか、新しいコントローラーを `NSystem/` 内に作成する。

---

## 10. さくら本番への注意

NSystem は現在**ローカル環境のみ**に存在する。  
さくら本番にデプロイする場合は:

1. 本番 `.env` に `NSYSTEM_GUEST_EMAIL` / `NSYSTEM_GUEST_PASSWORD` を追加
2. `n_images/`（4485枚）と `n_import/`（1352件）を本番サーバーにアップロード  
   → 容量が大きいため rsync か scp を使う
3. `php artisan migrate --force` で `n_*` テーブルを作成
4. `php artisan n:import --force` でデータ投入

**⚠️ 現時点ではクライアントへのデモURLを共有する前に上記作業が必要。**
