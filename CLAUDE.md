# CLAUDE.md - SunBWork プロジェクト ルール

このファイルはすべての会話の開始時に必ず参照すること。

---

## Windows パス → WSL2 パス 自動変換ルール

ユーザーが `C:\...` 形式の Windows パスを貼り付けた場合、Claude は自動的に WSL2 パスに変換して読み取ること。

| Windows パス例 | WSL2 パス |
|---|---|
| `C:\Users\foo\Pictures\screenshot.png` | `/mnt/c/Users/foo/Pictures/screenshot.png` |
| `C:\temp\image.jpg` | `/mnt/c/temp/image.jpg` |

**変換ルール:** `C:\` → `/mnt/c/`、以降の `\` はすべて `/` に置換する。D ドライブなら `D:\` → `/mnt/d/`。

---

## 作業ルール（最重要）

1. **作業前に必ず関連コードを読む。** 既存の実装を確認してから変更・追加する
2. **設計（方針・影響範囲・ファイル一覧）を先に示し、確認を取ってから実装する**
3. **不明点・仕様の曖昧さがあれば必ず質問してから作業を開始する。質問は必ず1つずつ行うこと（複数同時に質問しない）**
4. Vue / JS ファイルを変更したら必ず最後に `npm run build` を実行（許可済み）
5. `npm run build` はプロジェクトルート（`/home/tchirosb/SunBWork`）で実行
6. Artisan は必ずコンテナ内: `docker compose exec laravel bash -lc "php artisan ..."`
7. 設定変更後: `php artisan config:clear && php artisan cache:clear`
8. EACCES エラー時: `sudo chown -R $USER:$USER public/build/ && sudo chmod -R 755 public/build/assets`
9. **新規ページ・コンポーネントを作成する前に `z_instructions/CONSOLIDATED_01_layout_and_ui.md` を必ず確認する。** AppLayout の使い方・スロット・戻るボタン配置・NG パターンを守ること
10. **⚠️ 日付・時刻・カレンダー周りの実装は必ず「UTC / JST 混在ルール」セクションを確認してから着手すること。** このプロジェクトは JST（Asia/Tokyo）環境で稼働しており、UTC との変換ミスが繰り返し発生している。

**「git pull」「環境を合わせて」「最新にして」を求められたとき（ローカルをリモートに同期する場合）:**
以下の順番を必ず守ること。`git status` の「up to date」表示は fetch 前の古い情報なので信用しない。

```
1. git fetch origin
2. git log --oneline HEAD..origin/main   # コミット差を必ず確認してからユーザーに報告
3. git pull origin main
4. docker compose exec laravel bash -lc "composer install --no-interaction"
5. docker compose exec laravel bash -lc "php artisan migrate --force"
6. docker compose exec laravel bash -lc "php artisan config:clear && php artisan cache:clear && php artisan route:clear && php artisan view:clear"
7. npm install   # package.json が変わっている可能性があるので必ず実行
8. npm run build
```

⚠️ **pull 前に `npm run build` を実行してはいけない。** 古いソースでビルドした後に push すると、さくらの最新ビルドを上書きして機能が消える危険がある。

**「git にアップ」「さくらにデプロイ」を求められたとき:**
→ `z_instructions/DEPLOY_SAKURA.md` の手順に従う。VITE_APP_BASE_PATH の切り替えを必ず行うこと。

**さくら SSH 接続について:**
- SSH 接続先情報は `z_instructions/DEPLOY_SAKURA.md` に記載されている。
- ユーザーが「SSH で〜」「さくらで〜実行して」と言ったとき → DEPLOY_SAKURA.md から接続情報を取得し、Bash ツールで `ssh silverlamb759@silverlamb759.sakura.ne.jp "..."` を実行してよい。
- 状況的に SSH 実行が必要と判断した場合（例: migrate 忘れの可能性、本番キャッシュクリアが必要など）→ ユーザーに確認を取れば実行してよい。
- SSH 経由で実行するコマンドは必ず事前にユーザーに提示し、確認を取ってから実行すること。
- **⚠️ SSH ワンライナーで `php artisan migrate` を実行する場合は必ず `--force` を付ける。** 付けないと本番環境保護の対話確認が発生してワンライナーが止まる。`php artisan db:seed` も同様に `--force` を付けること。

**大規模な新機能・修繕作業に入るとき（複数フェーズ・5ファイル以上の変更を伴う場合）:**
→ 実装前に必ず以下の3ファイルを `z_instructions/` に作成し、ユーザーの確認を取ること:

| ファイル名の例 | 役割 |
|---|---|
| `{PREFIX}_PLAN{N}.md` | 詳細仕様・DB設計・フェーズ別タスク・変更ファイル一覧 |
| `{PREFIX}_MANAGER{N}.md` | 進捗管理・作業フロー・進捗一覧テーブル・作業ログ |
| `{PREFIX}{N}_PROMPT.md` | 新セッション開始用のプロンプト・設計サマリー |

命名例: `GHOST_PLAN1.md` / `GHOST_MANAGER1.md` / `GHOST1_PROMPT.md`  
既存の例: `REPAIR_PLAN4.md` / `REPAIR_MANAGER4.md` / `REPAIR4_PROMPT.md`

**作業完了後（3ファイルを作成した規模の作業が終わったとき）は以下を必ず行うこと:**
1. **`ChangelogSeeder` に修正ログを追記する** — `updateOrCreate(['version' => ...], $entry)` 形式で追加し、`php artisan db:seed --class=ChangelogSeeder` を実行して反映する
2. **関連する `CONSOLIDATED_*.md` を更新する** — 新機能・変更仕様を該当ファイルに追記・修正する（特に UI 変更は CONSOLIDATED_01、カレンダー系は CONSOLIDATED_05、ドメインルール追加は CONSOLIDATED_09）
3. 完了した PLAN/MANAGER/PROMPT ファイルは `z_instructions/archived/` に移動する

---

## プロジェクト基本情報

**業種・目的:** 印刷・組版会社向けの社内メンバー管理サイト。案件（ProjectJob）の割り当て・進行管理・日報・工数分析が主な機能。

**ロール別の役割:**
- SuperAdmin / Admin — システム・ユーザー管理
- Coordinator — 案件オーナー、メンバーへのジョブ割り当て担当
- Leader — 部署リーダー、案件の読み取り・部署ユーザー管理
- Clerk — 事務・経理（Coordinator 相当の権限）
- User — 実作業者（ジョブ受信・日報入力・工数登録）

- **スタック:** Laravel 11 / Vue 3 / Inertia.js / Vite / Tailwind CSS（SPA構成）
- **本番環境:** さくらレンタルサーバー（`https://sun-brain.co.jp/members`）
- **APP_NAME:** `SB` / **DB:** MySQL / Sanctum + Cookie SPA 認証 / Laravel Echo (WebSocket)

**主要フォルダ:**
```
app/Http/Controllers/   Admin/ Coordinator/ Leader/ ProjectJobs/ User/ Bot/ Chat/ Diaries/
app/Models/
resources/js/
  Pages/                Inertia ページ（ロール別サブディレクトリ）
  Components/           大文字始まり = プロジェクト固有
  components/ui/        小文字始まり = shadcn/ui 系
  layouts/AppLayout.vue メインレイアウト（全ページ共通）
routes/web.php          SPA ルート（api.php には置かない）
z_instructions/         詳細ドキュメント（backups/ は読み飛ばす）
```

---

## UI / レイアウト クイックリファレンス

AppLayout は `py-12 > max-w-7xl` を内部に提供済み。ページ側はデフォルトスロットに直接カードを置く:

```vue
<AppLayout title="ページタイトル">
  <template #header><h2>見出し</h2></template>
  <div class="rounded bg-white p-6 shadow">
    <!-- コンテンツ -->
  </div>
</AppLayout>
```

**NG:** `<main>` タグ、`py-2/py-12` の重複ラップ、`mx-auto max-w-7xl` の重複ラップ

**AppLayout スロット:** `#header` / `#headerExtras` / `#tabs` / デフォルト

**AppLayout が provide する値:** `authUser`（ログインユーザー）/ `user`（ページの user prop）

**ToastUnified は AppLayout 内にグローバル配置済み。各ページで重複させない。**

**ロール別カラー:** SuperAdmin=黄 / Admin=赤 / Leader=オレンジ / Coordinator=緑 / Clerk=紫 / User=青

**Ziggy の route() 使用時はパラメータ名をオブジェクトで渡す:**
```js
route('coordinator.project_jobs.show', { projectJob: job.id });
```

---

## さくら本番 必須ルール ⚠️

**本番 .env:** `APP_URL` / `ASSET_URL` = `https://sun-brain.co.jp/members` / `VITE_APP_BASE_PATH=/members`

**ローカル .env:** `APP_URL=http://localhost:8000` / `VITE_APP_BASE_PATH=`（空）

① **ナビゲーションは必ず `route()` を使う** — パスをハードコードすると `/members` ベースパスで 404
```js
// NG: window.location.href = `/events/${id}`;
// OK: router.get(route('events.show', { event: id }));
```

② **CSRF トークンは meta tag から取得する** — さくらでは `XSRF-TOKEN` クッキーが発行されず 419
```js
// NG: document.cookie.match(/XSRF-TOKEN=([^;]+)/)
// OK: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
```

③ **`php artisan migrate` を忘れると本番が壊れる** — ローカルは正常でも無音で壊れる実績あり

④ **さくら上の `sed -i` は BSD版のため `-i ''` が必要**

---

## UTC / JST 混在ルール ⚠️

### 大前提：このプロジェクトは JST（Asia/Tokyo）で稼働している

```
config/app.php: 'timezone' => env('APP_TIMEZONE', 'Asia/Tokyo')
```

**日付・時刻を扱う実装はすべてこの前提を念頭に置くこと。** UTC との 9 時間差で繰り返しバグが発生している。

---

### ① Eloquent モデルの date キャスト — シリアライズ時の UTC 変換に注意

`'date'` キャストは Inertia/JSON へのシリアライズ時に `toJSON()` でUTC変換される:

| キャスト | JSON シリアライズ結果 | Vue で slice(0,10) すると |
|---|---|---|
| `'date'` | `"2026-06-03T15:00:00.000000Z"` (UTC) | `"2026-06-03"` ← **1日ずれる！** |
| `'date:Y-m-d'` | `"2026-06-04"` | `"2026-06-04"` ✓ |

**ルール: date のみを扱うカラムのキャストは必ず `'date:Y-m-d'` を使う**

```php
// NG — JST環境でUTC変換により1日前の日付がVueに渡される
protected function casts(): array
{
    return ['held_at' => 'date'];
}

// OK
protected function casts(): array
{
    return ['held_at' => 'date:Y-m-d'];
}
```

---

### ② Vue 側の日付表示 — `new Date()` の UTC 問題

`new Date().toISOString()` は UTC 時刻を返すため、JST の日付と1日ずれることがある:

```js
// NG — UTCで "2026-06-03" になることがある（JST 00:00〜08:59 の間）
const today = new Date().toISOString().slice(0, 10);

// OK — ローカル日付を使う
const today = new Date().toLocaleDateString('sv-SE'); // "2026-06-04" (YYYY-MM-DD形式)
```

---

### ③ events テーブルの保存形式混在（既存の注意事項）

`events.starts_at / ends_at` の保存形式が2種類ある:

| イベント種別 | 保存形式 | 読み出し時の注意 |
|---|---|---|
| 通常イベント（社内予定・外出等） | **JST 文字列**をそのまま保存 | `Carbon::parse($v)` で JST として扱える |
| 校正ジョブイベント（`job_type='proof'`） | **UTC 文字列**で保存 | そのまま parse すると 9 時間ずれる |

**必ずこのルールに従うこと:**
- `Carbon::parse($event->starts_at)` を直接使わない — proof イベントで 9 時間ずれる
- **`CalculatesEventTime` トレイトの `resolveJstCarbon($event, 'starts_at')` を使う**
  → `app/Http/Controllers/Concerns/CalculatesEventTime.php` に実装済み
- トレイトを使えるコントローラーでは `use CalculatesEventTime;` を宣言し、`projectJobAssignment:id,job_type` を eager load してから呼ぶ

```php
// NG
$start = Carbon::parse($event->starts_at); // proof なら 9時間ずれる

// OK
$event->load('projectJobAssignment:id,job_type');
$start = $this->resolveJstCarbon($event, 'starts_at'); // JST Carbon を返す
```

昼休憩計算も同トレイトの `computeLunchMinutes($evStart, $evEnd, $userId, $cache)` を使うこと（UserMonthlyBreak → UserSetting → デフォルト 12:00–13:00 の優先順）。

---

## データ設計 クイックリファレンス

**`project_job_assignments`** が JobBox・MyJobBox 両方の唯一のテーブル:
- `sender_id = user_id` → 自己割当（MyJobBox）
- `sender_id ≠ user_id` or NULL → Coordinator 割当（JobBox）
- `desired_start_date` は存在しない。期間フィルターは `desired_end_date` を使うこと

**続きジョブ FK:** `source_assignment_id`（チェーン）/ `supersedes_assignment_id`（依頼ジョブ置き換え）— 混同しないこと

**ProjectJob 完了ルート名:** 必ず `coordinator.project_jobs.complete`（`project_jobs.complete` では 404）

**`project_jobs.schedule` カラムはさくら本番に存在しない** → `update()` 前に `Arr::pull($data, 'schedule')` 必須

---

## CSV インポート ルール

**すべての CSV インポートは Shift-JIS + CRLF + BOM に対応すること。**

`NormalizesCsvEncoding` トレイトを使う:
- ファイルパス: `app/Http/Controllers/Concerns/NormalizesCsvEncoding.php`
- 各コントローラーで `use NormalizesCsvEncoding;` を宣言して使う

| メソッド | 用途 |
|---------|------|
| `$this->normalizeCsvStoredFile($storagePath)` | `$file->store(...)` で保存済みのファイルをその場で正規化（上書き） |
| `$this->normalizeCsvToTemp($file)` | UploadedFile を正規化した一時ファイルパスを返す（使用後 `@unlink` 必須）|
| `$this->normalizeCsvContent($raw)` | バイト列を正規化した UTF-8 文字列を返す |

```php
// store() 方式のコントローラー（ClientController, Admin/UserController 等）
$path = $file->store('temp_csv', 'local');
$this->normalizeCsvStoredFile($path);   // ← ここを追加するだけ
$handle = fopen(Storage::path($path), 'r');

// getRealPath() 方式のコントローラー（ProjectSchedulesController 等）
$tmpPath = $this->normalizeCsvToTemp($file);
$handle  = fopen($tmpPath, 'r');
// ... 処理後 ...
fclose($handle);
@unlink($tmpPath);
```

---

## 詳細ドキュメント参照先

| ファイル | 内容 |
|---------|------|
| `z_instructions/CONSOLIDATED_01_layout_and_ui.md` | UI ルール詳細 |
| `z_instructions/CONSOLIDATED_02_security_and_sessions.md` | セキュリティ・セッション |
| `z_instructions/CONSOLIDATED_03_auth_and_cors.md` | 認証・CORS |
| `z_instructions/CONSOLIDATED_04_ai_and_chat.md` | AI・チャット |
| `z_instructions/CONSOLIDATED_05_calendar_and_jobbox.md` | カレンダー・JobBox |
| `z_instructions/CONSOLIDATED_06_messages_and_files.md` | メッセージ・ファイル |
| `z_instructions/CONSOLIDATED_07_workload_and_handover.md` | ワークロード解析 |
| `z_instructions/CONSOLIDATED_08_attachment.md` | 添付ファイル詳細 |
| `z_instructions/CONSOLIDATED_09_domain_rules.md` | ドメインルール詳細（権限・JobBox・進行表・通知等） |
| `z_instructions/DEPLOY_SAKURA.md` | さくらデプロイ手順 |
