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
再取り込みが必要な場合は `php artisan n-system:import --force` を実行する（後述）。

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
  NSchool.php                  恒久学校マスター
  NSchoolYear.php              年度別学校情報
  NExamSeries.php / NExam.php  入試系列 / 年度別試験
  NExamDocument.php            科目別の問題・解答文書
  NExamDaimon.php              大問本文
  NPublication*.php            年度版書籍・掲載行
  NImportBatch.php             取込監査

# コンフィグ
config/nsystem.php             ゲスト認証情報（.envから読む）

# ルート
routes/nsystem.php             NSystem専用ルート定義

# ビュー（Blade — index / login のみ残留）
resources/views/n_system/
  demo/
    layout.blade.php           共通レイアウト（ヘッダー・ログアウトボタン）※Blade ページ用
    index.blade.php            学校一覧（カテゴリ別グリッド）
    search.blade.php           旧全文検索 Blade 版（未使用・削除候補）
  guest/
    login.blade.php            ゲスト専用ログイン画面

# Vue / Inertia（検索・問題表示）
resources/js/
  layouts/NSystemDemoLayout.vue   NSystem 専用ヘッダー（学校一覧リンク・ログアウト）
  Pages/NSystem/
    Search.vue                 全文検索画面（Inertia ページ）
    School.vue                 問題・解答表示画面（Inertia ページ）★2026-06-20 Blade から移行
  Components/NSystem/
    SearchFilters.vue
    SearchResultCard.vue
    SearchPagination.vue

# ⚠️ school.blade.php は 2026-06-20 に削除済み。School.vue が後継。

# Artisanコマンド
app/Console/Commands/NSystem/NSystemImport.php   n-system:import コマンド
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
| `n_schools` / `n_school_years` | 恒久学校 / 年度別名称・属性 | 148 / 148件 |
| `n_exam_series` / `n_exams` | 入試系列 / 年度・Nコード付き試験 | 158 / 158件 |
| `n_exam_documents` | 試験の科目別問題・解答 | 1,219件 |
| `n_exam_daimons` | 問題・解答の大問本文 | 4,620件 |
| `n_publication_editions` / `n_publication_entries` | 年度版書籍 / Mコード掲載行 | 5 / 894件 |
| `n_import_batches` / `n_source_school_rows` | 取込履歴 / 元行・例外監査 | 実行回数に応じて増加 |
| `n_legacy_*` | 移行前3テーブル。検証完了まで削除禁止 | 旧件数を保持 |

マイグレーションファイル:
- `2026_06_19_100001_create_n_schools_table.php`
- `2026_06_19_100002_create_n_questions_daimon_table.php`
- `2026_06_19_100003_create_n_answers_daimon_table.php`
- `2026_06_19_100004_rebuild_n_daimon_fulltext_with_ngram.php`（MySQL 8.0 ngram全文検索）
- `2026_06_19_130001_normalize_n_system_tables.php`（正規化・2024年度データ移行）
- `2026_06_19_130002_rebuild_n_exam_daimons_fulltext_with_ngram.php`（新大問テーブルのngram索引）

年度の正は `n_exams.admission_year`、Nコード全体は `n_exams.n_code`、学校照合用の先頭3文字は `n_schools.n_code_prefix`。Mコードは年度版ごとに変わるため `n_publication_entries` 以外では学校識別に使用しない。`n_publication_entries` は `school_id` と `exam_id` を直接保持し、1掲載行=1校=1試験として扱う。

2024年の仮コード `464F` は学校リストの `464N` と同一試験にせず、`n_source_school_rows` に未解決として記録した。該当する問題3件は `n_legacy_questions_daimon` に保持している。2025/2026の `M109` は `4551 / 4751` の正式共有例外として2掲載行へ分割し、2026の `4331 → 4335` は監査注記を残したうえで現状運用どおり `4331` として登録する。

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

### NSystem テストの注意点

```bash
docker compose exec laravel bash -lc "php artisan test tests/Unit/NSystem/NQuestionSearchServiceTest.php tests/Feature/NSystem/NQuestionSearchTest.php"
```

#### ① Inertia ページのテストは assertInertia を使う

`school()` などの Inertia レスポンスは Vue がブラウザで描画するため、`assertSee('Nコード A001')` は失敗する（サーバーは JSON props しか返さない）。**Blade から Inertia に移行したページのテストは必ず `assertInertia` に書き換える。**

```php
// NG — Inertia ページでは動かない
->assertSee('Nコード A001')
->assertSee('非公開HTML')

// OK — Inertia props を直接検証する
->assertInertia(fn (Assert $page) => $page
    ->component('NSystem/School', false)
    ->where('school.code', 'A001')
    ->has('daimons', 1, fn (Assert $daimon) => $daimon
        ->where('body_html', fn ($html) => str_contains($html, '非公開HTML'))
        ->etc()
    )
);
```

#### ② ローカル限定テストは markTestSkipped でガード

`z_NDBSystem/Nコードリスト*.xlsx` は git 管理外のため CI（GitHub Actions）には存在しない。  
これらのファイルを必要とするテストは先頭で存在チェックしてスキップする:

```php
if (! is_file(base_path('z_NDBSystem/Nコードリスト2025.xlsx'))) {
    $this->markTestSkipped('z_NDBSystem/Nコードリスト2025.xlsx が存在しないためスキップ');
}
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
docker compose exec laravel bash -lc "php artisan n-system:import"

# 確認スキップ
docker compose exec laravel bash -lc "php artisan n-system:import --force"
```

処理内容:
1. `schools_index.json`から学校、年度別学校情報、試験系列、年度・Nコード付き試験をupsert
2. `storage/app/private/n_import/*.json` を走査（ファイル名パターン: `{code4}{year4}__{Q|A}{Ko|Sa|Sh|Ri}.json`）
3. `body_html` 内の `src="images/` を `src="/n_images/` に置換してDBへ保存
   → **ローカルではそのまま動く。さくら本番では `/n_images/` が `/members/n_images/` と解釈されず壊れる。**
   → **NdemoController::school() がInertiaへ渡す際に `asset('n_images/')` で正しいURLへ書き換えている。**
     DBのデータを直接書き換える必要はない。
4. 2022～2026の `Nコードリスト*.xlsx` をヘッダー名で読み込み、`n_publication_editions` / `n_publication_entries` にupsert
5. `2025/2026 M109` は `4551` と `4751` の2掲載行へ正規化し、`2026 M106 4331 → 4335` は `4331` として採用
6. 実行結果と監査元行を `n_import_batches` / `n_source_school_rows` に記録

### 学校一覧の年度表示

- `/n-demo` のカード順は選択年度の `n_publication_entries.mikuni_code` 昇順
- 年度ボタンは「問題文書が存在する年度」だけを表示
- Mコード履歴自体は2022～2026の5年度分をDBへ保持
- 現状、問題文書があるのは2024年度のみなので年度ボタンは2024のみ表示

---

## 7.5 静的ファイル（画像・CSS）のさくらデプロイ

### 初回または画像が増えたとき

**ローカル → さくら転送（4485枚 PNG + CSS）:**

```bash
# 画像（rsync で差分転送）
rsync -az /home/w229/SunBwork/public/n_images/ \
  silverlamb759@silverlamb759.sakura.ne.jp:~/SunBWork/public/n_images/

# CSS（更新時のみ）
scp /home/w229/SunBwork/public/n_sample.css \
  silverlamb759@silverlamb759.sakura.ne.jp:~/www/members/n_sample.css
```

### さくら側のシンボリックリンク（初回のみ）

さくらの `~/www/members/` は `~/SunBWork/public/` の実体ではなく別ディレクトリ。  
`n_images/` は `public/n_images/` へのシンボリックリンクで提供する:

```bash
# 初回のみ（既に設定済み: 2026-06-20）
ln -s ~/SunBWork/public/n_images ~/www/members/n_images
```

`n_sample.css` はシンボリックリンクにせず `~/www/members/n_sample.css` に実体ファイルを置く（`scp` でコピー）。

### 画像パスの仕組み（変更時の注意）

| 層 | 内容 |
|---|---|
| DB保存値 | `src="/n_images/xxxxx.png"` — import 時に `src="images/` から変換してそのまま保存 |
| コントローラー | `NdemoController::school()` が Inertia に渡す直前に `str_replace` で絶対 URL に書き換え |
| ブラウザ受信 | ローカル: `http://localhost:8000/n_images/...` / さくら: `https://sun-brain.co.jp/members/n_images/...` |

DBのデータを直接変更してはいけない。コントローラー側の `asset()` 変換で環境差を吸収している。

#### ⚠️ asset() の末尾スラッシュ問題

Laravel の `asset($path)` は内部で `trim($path, '/')` を実行するため、**`asset('n_images/')` は末尾スラッシュが取り除かれた `https://.../n_images`（スラッシュなし）を返す**。

これにより `str_replace('src="/n_images/', 'src="' . asset('n_images/'), ...)` を書くと:
- 置換後: `src="https://sun-brain.co.jp/members/n_images` + `11712024QSa03.png"` → スラッシュなしで404

**正しい書き方（現在の実装）:**

```php
// asset() はパスのスラッシュをトリムするため、末尾 / は別途付加する
$assetBase = asset('n_images');  // → https://sun-brain.co.jp/members/n_images
str_replace('src="/n_images/', 'src="' . $assetBase . '/', $d->body_html);
// 結果: src="https://sun-brain.co.jp/members/n_images/11712024QSa03.png" ✓
```

間違えやすい点なので変更時は注意すること。

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
rm -rf app/Console/Commands/NSystem/
```

### Step 3: DBテーブルとマイグレーションを削除

```bash
# 正規化マイグレーションを含めてrollbackする。実行前にmigrate:statusで対象を確認する
docker compose exec laravel bash -lc "php artisan migrate:rollback --step=6 --force"

# マイグレーションファイル削除
rm database/migrations/2026_06_19_100001_create_n_schools_table.php
rm database/migrations/2026_06_19_100002_create_n_questions_daimon_table.php
rm database/migrations/2026_06_19_100003_create_n_answers_daimon_table.php
rm database/migrations/2026_06_19_100004_rebuild_n_daimon_fulltext_with_ngram.php
rm database/migrations/2026_06_19_130001_normalize_n_system_tables.php
rm database/migrations/2026_06_19_130002_rebuild_n_exam_daimons_fulltext_with_ngram.php
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

## 10. さくら本番へのデータデプロイ手順

NSystem のデータ（JSON・Excel）は `.gitignore` 管理外のため **git push では本番に届かない**。  
ローカルでデータを調整した後、以下の手順でさくらに反映する。

### 前提確認

- さくら本番 `.env` に `NSYSTEM_GUEST_EMAIL` / `NSYSTEM_GUEST_PASSWORD` が設定済みであること  
  （未設定の場合は追加して `php artisan config:clear` を実行）
- `php artisan migrate --force` 済みで `n_*` テーブルが存在すること

---

### ステップ 1: JSON ファイルを転送（問題・解答・学校インデックス）

```bash
# ローカルの WSL 上で実行
rsync -az /home/w229/SunBwork/storage/app/private/n_import/ \
  silverlamb759@silverlamb759.sakura.ne.jp:~/SunBWork/storage/app/private/n_import/
```

> ⚠️ パスは必ず `storage/app/private/n_import/`。  
> Laravel 11 で `Storage::disk('local')` のルートが `storage/app/private` に変更されたため、  
> `storage/app/n_import/` に置いても import コマンドから参照されない。

---

### ステップ 2: Excel ファイルを転送（Mコードリスト）

```bash
scp /home/w229/SunBwork/z_NDBSystem/Nコードリスト*.xlsx \
  silverlamb759@silverlamb759.sakura.ne.jp:~/SunBWork/z_NDBSystem/
```

> さくら側に `~/SunBWork/z_NDBSystem/` ディレクトリがない場合は先に作成:  
> `ssh silverlamb759@silverlamb759.sakura.ne.jp "mkdir -p ~/SunBWork/z_NDBSystem"`

---

### ステップ 3: phpspreadsheet の確認とインストール

さくらは PHP 8.2 のため、通常の `composer install` では phpspreadsheet がインストールされない  
（依存する `maennchen/zipstream-php` が PHP 8.3 以上を要求するため）。

```bash
# さくら SSH で実行（phpspreadsheet が vendor に存在するか確認）
ssh silverlamb759@silverlamb759.sakura.ne.jp \
  "cd ~/SunBWork && php -r \"require 'vendor/autoload.php'; new PhpOffice\PhpSpreadsheet\Spreadsheet;\" 2>&1"
```

エラーが出る場合は `--ignore-platform-reqs` でインストール:

```bash
ssh silverlamb759@silverlamb759.sakura.ne.jp \
  "cd ~/SunBWork && composer install --no-interaction --ignore-platform-reqs 2>&1 | tail -5"
```

> `git pull` 後に `composer install` を実行すると phpspreadsheet が外れることがある。  
> import を実行する前に必ず上記確認を行うこと。

---

### ステップ 4: import コマンド実行

```bash
ssh silverlamb759@silverlamb759.sakura.ne.jp \
  "cd ~/SunBWork && php artisan n-system:import --force 2>&1"
```

成功時の出力例:
```
学校: 159件 / 問題: 2455件 / 解答: 2376件
年度版Mコード: 894掲載行 / 監査元行: 892件
スキップ: 132ファイル / エラー: 0ファイル
```

---

### よくあるエラーと対処

| エラー | 原因 | 対処 |
|---|---|---|
| `schools_index.json が見つかりません: .../storage/app/private/n_import/...` | JSON を `storage/app/n_import/` に置いた | ステップ1の正しいパスで再転送 |
| `Class "PhpOffice\PhpSpreadsheet\IOFactory" not found` | phpspreadsheet が未インストール | ステップ3の `--ignore-platform-reqs` でインストール |
| `Table 'n_import_batches' doesn't exist` | migration 130001 が記録のみで未実行 | 下記「テーブル手動作成」を参照 |

#### テーブル手動作成（`n_import_batches` / `n_source_school_rows` が存在しない場合）

migration 130001 をさくらで手動マークした場合など、これら2テーブルが作成されないことがある。  
その場合は MySQL で直接作成する:

```bash
ssh silverlamb759@silverlamb759.sakura.ne.jp 'mysql -h mysql3114.db.sakura.ne.jp \
  -u silverlamb759_sunbwork -p2024sunb11 silverlamb759_sunbwork <<'"'"'SQL'"'"'
CREATE TABLE IF NOT EXISTS `n_import_batches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `import_type` varchar(50) NOT NULL,
  `source_filename` varchar(255) NOT NULL,
  `source_year` smallint unsigned DEFAULT NULL,
  `file_hash` varchar(64) DEFAULT NULL,
  `imported_at` timestamp NULL DEFAULT NULL,
  `status` varchar(20) NOT NULL,
  `summary_json` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `n_source_school_rows` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `import_batch_id` bigint unsigned NOT NULL,
  `source_row_number` smallint unsigned DEFAULT NULL,
  `admission_year` smallint unsigned NOT NULL,
  `raw_mikuni_code` varchar(50) DEFAULT NULL,
  `raw_n_code` varchar(100) DEFAULT NULL,
  `raw_school_name` text,
  `raw_exam_label` varchar(200) DEFAULT NULL,
  `parsed_json` json DEFAULT NULL,
  `resolution_status` varchar(20) NOT NULL,
  `resolution_notes` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `n_source_school_rows_import_batch_id_foreign`
    FOREIGN KEY (`import_batch_id`) REFERENCES `n_import_batches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL
'
```

---

### データ確認

```bash
ssh silverlamb759@silverlamb759.sakura.ne.jp 'mysql -h mysql3114.db.sakura.ne.jp \
  -u silverlamb759_sunbwork -p2024sunb11 silverlamb759_sunbwork -e "
SELECT \"n_schools\" as tbl, COUNT(*) as cnt FROM n_schools
UNION ALL SELECT \"n_exams\", COUNT(*) FROM n_exams
UNION ALL SELECT \"n_exam_daimons\", COUNT(*) FROM n_exam_daimons
UNION ALL SELECT \"n_publication_entries\", COUNT(*) FROM n_publication_entries;" 2>/dev/null'
```
