# PORTFOLIO_PLAN1.md — デザイナー用ポートフォリオ 設計書 v1

作成: 2026-11-06 / 対象: サン・ブレーン（SB）専用 / 状態: 設計合意待ち

---

## 1. 目的

制作担当者（デザイナー）が**客先で自分の作品を見せる**ためのポートフォリオ機能。
PDF・JPG・動画をアップし、整理・削除・追加ができる。見せる画面は通常のヘッダーを持たない全画面ページ
（銀本進行 `LedgerIndex.vue` / 売上分析 `SalesAnalysisShell.vue` と同じ「別タブ・独立レイアウト」方式）。

## 2. 確定仕様（2026-11-06 ユーザー確定）

| 項目 | 内容 |
|---|---|
| 対象会社 | **SB 専用**。サンエー印刷（`company_type = 'general'`）には出さない。`deny_general` を使う（SunA ディレクトリには置かない） |
| 持ち方 | **個人ごとに専用のポートフォリオ**。管理（作成・閲覧・更新・削除）の範囲は下記「権限」のとおり |
| 公開範囲 | ログインした人のみ。**共有リンクは作らない** |
| 通信 | オンライン前提。PWA・オフライン対応はしない |
| 整理 | コレクション（フォルダ）＋タグ。追加・削除・並べ替え |
| 作品情報 | タイトル（必須）＋説明テキスト |
| ファイル種別 | JPG/PNG（画像）・PDF・動画（mp4 等） |
| 軽量化 | 画像＝サーバー側で縮小 / PDF＝サーバー側でページ画像化 / 動画＝**アップ前にブラウザ側で圧縮** |
| 管理画面 | 通常ヘッダー付き（AppLayout）。アップ・整理・削除・アクセント色選択 |
| 閲覧ページ | 別タブ・ヘッダーなし全画面。トップ＝コレクション表紙一覧 → タップで全画面スライドショー |
| 演出 | スワイプ・キー操作で送る / PDF は見開きでページめくりアニメ |
| デザイン | 白ベース＋モノトーン＋**デザイナーが選ぶワンポイントカラー**（印刷物＝パンフ・チラシが主役） |
| 権限（2026-11-07 再々変更） | **作成・編集・削除は本人のみ**。利用許可（利用メンバー設定のスイッチ）が ON の人。**Admin は、スイッチが未設定なら自動で ON**で自分のポートフォリオを作れる（客先へ成果物を見せる Admin がいるため）。スイッチを OFF にした Admin だけが閲覧専用。試用中の SuperAdmin は、スイッチに関わらず自分の分だけ作れる。**SuperAdmin / SB の Admin は SB メンバーのポートフォリオを閲覧できる**（Admin は同じ会社のみ・SuperAdmin は SB 全員。管理画面の「閲覧するメンバー」で切り替え）。問題のある作品は**本人に修正・削除を依頼**する運用。サンエー印刷は誰も使えない。判定は `PortfolioAccess`（`canView` / `canEdit`）に集約。サーバー側で拒否する（画面の非表示に頼らない） |
| 入口（2026-11-06 変更） | **Admin ナビ**に「ポートフォリオ管理」（SB の Admin のみ表示・閲覧専用画面）。**ユーザーナビ**に「ポートフォリオ」（許可オンの人のみ・編集画面）。**SuperAdmin ナビには置かない**（SuperAdmin は admin エリアで閲覧、user エリアで自分の分を編集）。他ロール（leader/coordinator/clerk 等）のナビは後回し（MANAGER D-4）。ルートは `admin.portfolio.*` と `user.portfolio.*` |
| 作品の追加（2026-11-06 変更） | 常時表示のドロップ枠はやめ、**「＋ 作品を追加」ボタン → モーダル**。入力順は **① タイトル（必須）→ ② 説明 → ③ ファイル（必須）→ 分類（任意: コレクション・タグ）**。1 作品 = 1 ファイル。空の状態にも追加ボタンを出して次の行動を示す |
| 権限 | メンバーごとオン/オフ。売上分析の権限画面を踏襲し、**雇用区分（正社員等）・ロール・部署で絞り込み**。初期は **SuperAdmin のみオン（試用）** |

## 3. 既存実装の調査結果（実装の土台）

| 参考にするもの | 場所 | 流用の仕方 |
|---|---|---|
| 個人許可テーブル | `sales_analysis_permissions`（`user_id, enabled, granted_by, granted_at`）/ `App\Models\SalesAnalysisPermission` | 同じ形で `portfolio_permissions` を作る |
| 許可判定 Middleware | `EnsureSalesAnalysisAccess`（`bootstrap/app.php:55` でエイリアス登録） | `EnsurePortfolioAccess` を新設。SuperAdmin 常時可＋許可オン |
| Inertia 共有プロパティ | `HandleInertiaRequests.php:220` `canAccessSalesAnalysis` | `canAccessPortfolio` を追加 |
| 許可設定画面 | `SuperAdmin/SalesAnalysisPermissionController` + `Pages/SuperAdmin/SalesAnalysisPermissions/Index.vue` | 構造を踏襲。ただし対象は Admin/Clerk 限定ではなく**全ロール**（SB 会社のユーザー） |
| 絞り込み UI | `Pages/Iruka/BoardSettings.vue`（`EMPLOYMENT_LABELS`: regular=正社員 / contract=契約社員 / dispatch=派遣社員 / outsource=業務委託。部署フィルター）、`User.employment_type` | ラベル・絞り込みの作りを踏襲 |
| 別タブ・独立レイアウト | `SalesAnalysisShell.vue`（AppLayout 不使用）、`SuperAdminNavigationTabs.vue:213` の `<a target="_blank" rel="noopener noreferrer">` | 閲覧ページは AppLayout を使わず `Head` + 独自全画面。ナビからは `<a target="_blank">` で開く |
| 会社ガード | `deny_general`（`DenyGeneralCompany`） | 全ルートに付ける。SuperAdmin・会社未設定は通過 |

> 注意: 売上分析の許可は Admin/Clerk 限定だが、ポートフォリオは「デザイナー」＝一般ユーザー（User/Coordinator 等）が使う想定のため**ロール限定をしない**。

## 4. データ設計（すべて SB 専用・接頭辞 `portfolio_`）

SunA（`suna_`）ではなく SB 側の機能なので接頭辞は `portfolio_`。個人専用のため `user_id` で絞る
（会社の絞り込みは不要。`deny_general` で会社ごと入口を閉じる）。

```
portfolio_permissions        個人許可
  id, user_id (unique, FK users), enabled bool default false,
  granted_by (FK users nullable), granted_at datetime nullable, timestamps

portfolio_profiles           個人設定
  id, user_id (unique), accent_color string(7) default '#2563eb',
  display_name string nullable   -- 閲覧ページの見出し（既定は users.name）
  headline string nullable       -- 肩書き・一言
  timestamps

portfolio_collections        コレクション（フォルダ）
  id, user_id (index), name, description nullable,
  cover_item_id nullable (FK portfolio_items, nullOnDelete)  -- 表紙に使う作品（未設定なら先頭作品）
  sort_order int, timestamps, softDeletes

portfolio_items              作品
  id, user_id (index), collection_id nullable (FK, nullOnDelete),
  type enum('image','pdf','video'),
  title, description nullable text,
  original_name, mime, size_bytes bigint,
  file_path            -- 表示用（画像=縮小後 / PDF=原本 / 動画=圧縮後mp4）
  thumb_path nullable  -- 一覧用サムネイル
  width, height nullable int, duration_sec nullable int, page_count nullable int,
  status enum('uploading','processing','ready','failed') default 'uploading',
  sort_order int, timestamps, softDeletes

portfolio_item_pages         PDF のページ画像
  id, item_id (FK cascade), page_no int, path, width, height   -- unique(item_id, page_no)

portfolio_tags               タグ（個人ごと）
  id, user_id, name   -- unique(user_id, name)
portfolio_item_tag           作品×タグ
  item_id, tag_id (primary key 複合)
```

### 保存先とアクセス
- ファイルは **`storage/app/portfolio/{user_id}/…`（公開ディレクトリ外・`local` ディスク）**
- 配信は **認証付きコントローラーで本人のみストリーム**（`BinaryFileResponse`。Range 対応＝動画のシーク・ETag/Cache-Control 付与）
- 他社制作物・個人情報を含む可能性があるため、公開 URL・`storage:link` は使わない（さくらの symlink 問題も回避）
- 削除（作品・コレクション）は**ファイル実体も削除**。物理削除は softDelete 後のバッチではなく、作品削除時にファイルを即削除（ゴミ箱は作らない）

## 5. 処理パイプライン

### 5.1 画像（JPG/PNG）
アップ → サーバー（Imagick）で**長辺 2400px・JPEG 品質 82 に縮小**（EXIF 向き補正・メタ情報除去）→ サムネイル（長辺 600px）生成。
原本は保存しない（容量節約）。

### 5.2 PDF（さくらで Imagick + Ghostscript が使えることを 2026-11-06 に SSH 確認済み）
1. 原本 PDF を保存（`status=processing`）
2. **ページ画像化は「N ページずつ」呼び出す方式**（Web の実行時間上限対策。1 回 5 ページ程度 × 画面側で繰り返し呼び、進捗バーを出す）
3. 各ページ: 150dpi 相当・長辺 2000px・JPEG 品質 80 → `portfolio_item_pages`
4. 1 ページ目をサムネイル化。上限ページ数 **60**（超えたら拒否）
5. 全ページ完了で `status=ready`

### 5.3 動画（さくらに ffmpeg なし → ブラウザ側で圧縮）
1. ブラウザ（WebCodecs ＋ MP4 muxer ライブラリ）で **H.264/AAC・長辺 1280px・約 2.5Mbps** に再エンコード（進捗バー表示）
2. ポスター画像（サムネイル）もブラウザの canvas で作って同時送信
3. WebCodecs 非対応ブラウザは「圧縮できない」旨を表示し、サイズ上限（例 150MB）以内の原本をそのままアップ可（原本の場合は上限を別途 300MB 程度に設定）
4. 上限: 圧縮後 **1 本 1GB**（商品の紹介動画など短尺が中心の想定。長尺でも通せる余裕を持たせる。Phase 0 の結果で調整）

### 5.4 大きいファイルの送信（分割アップロード）
さくらの Web 経由の `upload_max_filesize` / `post_max_size` が小さい可能性があるため、
**2MB 程度の分割で送る方式**（init → chunk × N → complete）を動画・PDF 共通で用意する。画像は小さいので通常アップ。
Phase 0 で Web 実効値を確認し、余裕があれば分割サイズを調整。

## 6. 画面設計

### 6.0 admin エリアの管理画面（2026-11-08 変更）
Admin / SuperAdmin は、チームルーム型の**タブ付きの管理画面**（`CONSOLIDATED_01`「タブ付きの管理画面」）を使う。
- タブ1「**メンバーのポートフォリオ**」（`admin.portfolio.manage`）: 登録者のカードを縦に並べる（名前・部署・役職・作品数の内訳・最終更新・サムネイル最大 4 点・「客先で見せる」・問題のある作品の赤い印）。カード／サムネイルのクリックで、その人のページ（`admin.portfolio.members.show`・閲覧専用）へ。作品のある人が上、まだ無い人は下に名前だけ。部署のチップ・氏名検索つき。自分のカードには「編集する」（user エリアの編集画面へ）
- タブ2「**利用メンバー設定**」（`admin.portfolio.permissions.index`・SuperAdmin のみ）: 申請者管理と同じ標準形（§6.3）
- 一般メンバーの自分の編集画面（`user.portfolio.manage`）は、下記 §6.1 のまま。スイッチ OFF の Admin がここを開くと、タブ1へ案内する

### 6.1 自分のポートフォリオの編集画面（`AppLayout` + ヘッダーあり）`user.portfolio.manage`
- 左: コレクション一覧（追加・名前変更・並べ替え・削除）＋「すべて」「未分類」
- 右: 作品カード一覧（サムネイル）。ドラッグで並べ替え、タイトル/説明/タグ/コレクション編集、削除
- 上部: ドラッグ&ドロップのアップ領域（複数同時、進捗バー、動画は圧縮進捗）
- タグでの絞り込み
- 設定: アクセント色（9 色程度のパレット）、表示名、肩書き
- 「**客先で見せる**」ボタン → `<a target="_blank">` で閲覧ページを開く
- SuperAdmin（試用中）のみ「**利用メンバー設定**」ボタン（権限画面へ。§6.3）

### 6.2 閲覧ページ（独立レイアウト・別タブ）`/portfolio/view`
- `AppLayout` 不使用。`Head` + 全画面。操作用の細いバーのみ（戻る不要＝タブを閉じる）
- **トップ**: 白背景。名前・肩書き。コレクションの表紙カード（アクセント色のワンポイント、角は控えめ、余白多め）。タグでの絞り込み
- **スライドショー**（コレクションをタップ）: 全画面。画像=フィット表示、**PDF=見開き＋ページめくりアニメ**、動画=ポスター→再生
  - 操作: **スワイプ（タッチ）／矢印キー／Esc で閉じる**。画面下に進行インジケーター、作品のタイトル・説明をキャプション表示
  - 次のスライドの画像を先読み
- 管理用のボタン（削除・編集）は一切出さない（客先での誤操作防止）

### 6.3 利用メンバー設定画面（SuperAdmin / 後に Admin）`/portfolio/permissions`

> **2026-11-07 変更**: 画面と並び順は、申請者管理のメンバー設定と同じ標準形に統一した（`CONSOLIDATED_01`「ユーザー選択（権限付与）画面の標準形」）。部署・雇用区分・担当のチップで絞り込み、スイッチを動かしてまとめて保存する。並びは 雇用区分 → 役職（設定順）→ 氏名。以下の記述のうち「一括オン/オフ」「個別トグル保存」は、この形に置き換わっている。
- `Pages/SuperAdmin/SalesAnalysisPermissions/Index.vue` を踏襲
- 一覧: SB 会社のユーザー全員。オン/オフトグル
- 絞り込み: **雇用区分（正社員 / 契約社員 / 派遣社員 / 業務委託）・担当（制作・営業など。`users.assignment_id`）・部署・許可済みのみ・氏名検索**
- 一括オン/オフ（絞り込み結果に対して）
- 初期状態: 全員オフ（SuperAdmin は常時可）

### 6.4 ナビのボタン
- Admin ・SuperAdmin のタブに「**ポートフォリオ管理**」（`canAccessPortfolio` が真のとき。管理画面へ `Link`）
- 許可オンのメンバーには自分のロールのナビにも「ポートフォリオ」を出す（Phase 6。試用期間中は SuperAdmin のみで足りる）
- 閲覧ページは管理画面の「客先で見せる」から別タブ。各ナビに直接の「見せる」リンクを置くかは Phase 6 で相談

## 7. ルート（`routes/portfolio.php` はクロージャを返し、`routes/web.php` の各ロールのグループ内で呼ぶ）

> ⚠️ **AppLayout はルート名の接頭辞でロール別ナビを決める**（`AppLayout.vue` の `currentRouteContext`）。
> `portfolio.manage` のように接頭辞なしだと全員が「ユーザー」のナビになる（2026-11-06 に実際に発生）。
> 売上分析の `$registerSalesAnalysisRoutes` と同じく、**`superadmin.portfolio.*` / `admin.portfolio.*` のようにロールごとに登録**する。
> 画面内のリンクはコントローラーが渡す `routePrefix` で組み立てる。他ロール対応（Phase 6）は同じクロージャを各グループで呼ぶだけ。
> 画面なしの API・ファイル配信・閲覧ページ（ヘッダーなし）は接頭辞不要のため `portfolio.*` でよい。

以下は初期の想定（名前は `{ロール}.portfolio.*` に読み替える）：

```
Route::middleware(['auth:sanctum', jetstream.auth_session, 'verified', 'deny_general', 'portfolio'])
  prefix('portfolio')->name('portfolio.')
    GET  manage                      portfolio.manage              管理画面
    GET  view                        portfolio.view                閲覧ページ
    GET  api/data                    portfolio.api.data            閲覧用の全データ（コレクション/作品/ページ/タグ）
    POST/PUT/DELETE collections…     コレクション CRUD・並べ替え
    POST/PUT/DELETE items…           作品の登録・編集・削除・並べ替え
    POST upload/init|chunk|complete  分割アップロード
    POST items/{item}/process        PDF ページ画像化（N ページずつ）
    GET  files/{item}/{kind}         認証付きファイル配信（file / thumb / page/{n}）
    PUT  profile                     アクセント色等

SuperAdmin のみ:
    GET  permissions / PUT permissions/{user} / PUT permissions/bulk
```

- 画面遷移は必ず `route()`（ハードコード禁止。`/members` ベースパス対策）
- CSRF は meta タグ方式（`XSRF-TOKEN` クッキーに頼らない）
- axios で boolean を GET クエリに載せない（`? 1 : 0`。メモリ参照）

## 8. セキュリティ

- すべての取得・更新・配信で **`PortfolioAccess::canView`（閲覧）/ `canEdit`（書き込み）** を検証（他人の作品 ID を指定しても 404 または 403）
- MIME は拡張子ではなく `finfo` で判定。許可は image/jpeg・png・webp / application/pdf / video/mp4・quicktime のみ
- 個人あたり総容量の上限（**10GB**。利用者は 5 人程度でさくらの空き 1TB に十分収まる）。超過時はアップ拒否
- 他メンバーのファイルを**閲覧**できるのは SuperAdmin / 同じ会社の Admin のみ。**書き込みは本人のみ**。判定は `PortfolioAccess::canView / canEdit` に集約。見えない作品の ID 指定は 404、見えるが編集不可は 403、`owner_id` の不正指定は 403
- 利用メンバー設定（許可のオン/オフ）は SuperAdmin のみ。一覧には Admin（部署未設定でも）を出してスイッチで決める（Admin は行がなければ自動 ON）。SuperAdmin だけは常に使えるため ON 固定

## 9. 変更・新規ファイル一覧（予定）

**新規**
- `database/migrations/…_create_portfolio_tables.php`
- `app/Models/Portfolio/{PortfolioPermission, PortfolioProfile, PortfolioCollection, PortfolioItem, PortfolioItemPage, PortfolioTag}.php`
- `app/Http/Middleware/EnsurePortfolioAccess.php`
- `app/Http/Controllers/Portfolio/{PortfolioController, CollectionController, ItemController, UploadController, FileController, ProfileController}.php`
- `app/Http/Controllers/SuperAdmin/PortfolioPermissionController.php`
- `app/Services/Portfolio/{ImageProcessor, PdfRasterizer, PortfolioStorage}.php`
- `routes/portfolio.php`
- `resources/js/Pages/Portfolio/{Manage.vue, View.vue}`
- `resources/js/Pages/SuperAdmin/PortfolioPermissions/Index.vue`
- `resources/js/Components/Portfolio/{CollectionSidebar, ItemGrid, UploadZone, ItemEditModal, AccentPicker, CoverCard, Slideshow, PdfSpread, VideoStage}.vue`
- `resources/js/lib/portfolio/{videoCompress.js, chunkUpload.js}`
- `tests/Feature/Portfolio/…`

**変更**
- `bootstrap/app.php`（`portfolio` ミドルウェアエイリアス）
- `routes/web.php`（`routes/portfolio.php` の読み込み）
- `app/Http/Middleware/HandleInertiaRequests.php`（`canAccessPortfolio`）
- `resources/js/Components/Tabs/AdminNavigationTabs.vue` / `SuperAdminNavigationTabs.vue`（ボタン）
- `database/seeders/ChangelogSeeder.php`（作業完了後）
- `package.json`（動画 muxer ライブラリ、ページめくり用ライブラリを採用する場合）

## 10. フェーズ

| Phase | 内容 |
|---|---|
| 0 | 事前確認: さくら Web 実効のアップロード上限・実行時間、キューワーカー有無、ページめくり/動画ライブラリ選定 |
| 1 | DB・モデル・ミドルウェア・共有プロップ・権限画面（雇用区分絞り込み）・Admin/SuperAdmin ナビのボタン |
| 2 | 管理画面（コレクション/タグ/作品 CRUD）＋画像アップ（縮小・サムネイル）＋認証付き配信 |
| 3 | PDF 取り込み（ページ画像化・分割アップロード・進捗） |
| 4 | 動画取り込み（ブラウザ圧縮・ポスター・分割アップロード） |
| 5 | 閲覧ページ（表紙一覧・スライドショー・PDF 見開きめくり・スワイプ/キー） |
| 6 | 他ロールのナビ、仕上げ、Codex レビュー、テスト、ChangelogSeeder・CONSOLIDATED 更新、アーカイブ |
| 7 | （指示があるまで実施しない）さくらデプロイ。`migrate --force` 忘れずに |

## 11. 未確定・後で相談

1. （確定 2026-11-06）権限画面の置き場所＝**案B：ポートフォリオ管理画面内の「利用メンバー設定」ボタン**
2. （確定）個人容量上限＝10GB／動画の圧縮後上限＝1GB
3. 動画の圧縮画質は Phase 4 で実機の見え方を見て調整（商品を撮った短い動画が主用途）
4. 閲覧ページ内で「客先名を隠す」等は不要（作品情報はタイトル・説明のみ）と確定済み
5. 作品を「公開/非公開（客先ごと）」に切り替えたい要望が出たら別フェーズ
