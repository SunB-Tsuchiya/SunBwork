# PORTFOLIO_MANAGER1.md — ポートフォリオ 進捗管理

## ステータス凡例
⬜ 未着手 / 🔄 作業中 / ✅ 完了 / ⏸ 保留

## 作業フロー
1. 作業前に `PORTFOLIO_PLAN1.md` を読む（特に §2 確定仕様・§8 セキュリティ）
2. SB 専用。ルートは必ず `deny_general`。サン・ブレーンの既存挙動は変えない
3. Vue/JS 変更後は `npm run build`。ブラウザ確認はユーザーが行う（依頼があるときのみ Claude が操作）
4. `docker exec` は `--user sail`（storage 書き込み系で root 実行しない）
5. 日付を扱う箇所は CLAUDE.md「UTC / JST 混在ルール」を確認
6. 作業ログに追記し、状態を更新する

---

## Phase 0: 事前確認

| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 0-1 | さくら SSH で変換ツール確認 | ✅ | 2026-11-06。Imagick・gs・convert あり / ffmpeg なし / ディスク空き 1.0TB |
| 0-2 | さくら Web 側アップロード上限 | ✅ | 実測せず**分割 2MB 固定**で確定（php.ini は CLI/Web 共通パス `/usr/local/php/8.2/etc/php.ini`、upload 5M/post 8M。.htaccess・.user.ini に上書き設定なし）。2MB 分割なら上限に関係なく通る |
| 0-3 | キューワーカーの有無（さくら） | ✅ | 常駐ワーカーなし（cron は `schedule:run` を毎分のみ）。→ PDF は「N ページずつ同期処理」方式で確定、キューは使わない |
| 0-4 | 動画圧縮ライブラリ選定 | ✅ | **mediabunny**（WebCodecs ベースの変換 API。mp4-muxer の後継で現役保守。1.65.2） |
| 0-5 | ページめくり表現 | ✅ | **自作 CSS 3D**（`page-flip` は 2022 年以降更新なしのため不採用）。スワイプは既存の `@vueuse/core` の `useSwipe`、並べ替えは `sortablejs` を追加 |

## Phase 1: 土台・権限

| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 1-1 | migration（permissions / profiles / collections / items / item_pages / tags / item_tag） | ✅ | |
| 1-2 | モデル（`App\Models\Portfolio\…`） | ✅ | |
| 1-3 | `EnsurePortfolioAccess` ＋ `bootstrap/app.php` エイリアス | ✅ | |
| 1-4 | `HandleInertiaRequests` に `canAccessPortfolio` | ✅ | |
| 1-5 | 権限画面（雇用区分・ロール・部署絞り込み・一括） | ✅ | |
| 1-6 | Admin / SuperAdmin ナビにボタン | ✅ | |
| 1-7 | 初期データ: SuperAdmin のみオン | ✅ | SuperAdmin は常時可のため行は作らない。他は全員オフ |

## Phase 2: 管理画面・画像

| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 2-1 | コレクション CRUD・並べ替え | ✅ | |
| 2-2 | タグ CRUD・絞り込み | ✅ | |
| 2-3 | 画像アップ（縮小・サムネイル） | ✅ | クライアントで長辺2400pxに縮小してから送信（さくらの上限5MB対策）→サーバー（Imagick）で再処理 |
| 2-4 | 認証付きファイル配信（本人のみ・Range・ETag） | ✅ | `response()->file()`。URL はクライアントが Ziggy で組み立て（サーバー側の相対 URL だと /members が落ちるため） |
| 2-5 | 作品の編集・並べ替え・削除（ファイル実体も削除） | ✅ | |
| 2-6 | アクセント色・表示名設定 | ✅ | |

## Phase 3: PDF

| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 3-1 | 分割アップロード（init/chunk/complete） | ✅ | 2MB ずつ追記。offset で順序検証・409 で再開。申告サイズ・%PDF ヘッダー・ページ数（上限60）を complete で検証、失敗は作品ごと破棄 |
| 3-2 | ページ画像化（N ページずつ・進捗） | ✅ | 1回4ページ。150dpi・長辺2000px・JPEG80。完了時にサムネ（1ページ目）・実サイズ確定。管理画面にページ送り・全画面（←→）を追加 |

## Phase 4: 動画

| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 4-1 | ブラウザ圧縮（進捗表示・非対応時フォールバック） | ✅ | mediabunny（WebCodecs）で長辺1280px・H.264 2.5Mbps・AAC 128k・30fps。20MB以下のMP4は再エンコードしない。圧縮不可なら原本300MB以下のみ許可。実機（ブラウザ）での圧縮はユーザー確認待ち |
| 4-2 | ポスター生成・送信 | ✅ | ブラウザで1秒目（短尺は中間）のフレームを JPEG 化→ complete で送信。サーバーが poster（大）と thumb（600px）を作る。`poster_path` カラム追加（migration 2026_11_06_200001） |

## Phase 5: 閲覧ページ

| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 5-1 | 独立レイアウト・表紙一覧（白＋モノトーン＋アクセント） | ✅ | `portfolio.view`（接頭辞なし・AppLayout 不使用）。表紙＝コレクションの表紙作品（無ければ先頭）。2枚以上なら先頭に「すべての作品」、未分類は「その他」。タグ絞り込みあり。管理画面の「客先で見せる ↗」から別タブで開く（閲覧専用モードでは見ているメンバー分） |
| 5-2 | スライドショー（画像・動画・スワイプ/キー・先読み） | ✅ | ←→/Esc・`useSwipe`・全画面ボタン・前後の画像先読み・進行インジケーター・タイトル/説明のキャプション・画像クリックで拡大（ImageLightbox） |
| 5-3 | PDF 見開きページめくり | ✅ | 自作 CSS 3D（`PdfSpread.vue`）。横長=見開き（1ページ目は表紙として右のみ）、縦長/狭い=1ページ。端まで来たら次/前の作品へ。ブラウザでの見え方はユーザー確認待ち |

## Phase 6: 仕上げ

| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 6-1 | 他ロールのナビ | ⏸ | ユーザー判断（2026-11-06）: 今のまま（ユーザータブのみ）。試用の様子を見てから増やす（D-4） |
| 6-2 | PHPUnit（他人の作品に触れない・権限・会社ガード） | ✅ | Phase ごとに追加。`tests/Feature/Portfolio/` 54件 |
| 6-3 | Codex レビュー | ✅ | 2026-11-06 実施。ポートフォリオ関連の指摘2件を修正（下記ログ）。他8件は別作業（年末調整・交通費・売上分析）への指摘でスコープ外 |
| 6-4 | ChangelogSeeder 追記・CONSOLIDATED 更新・3 ファイルを archived/ へ | 🔄 | ChangelogSeeder（portfolio-1）・CONSOLIDATED_01/09 は完了。**archived/ への移動は本番デプロイ後**（Phase 7 で PLAN/PROMPT が必要なため） |

## Phase 7: デプロイ（指示があるまで実施しない）

| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 7-1 | DEPLOY_SAKURA.md の手順（VITE_APP_BASE_PATH 厳守・`migrate --force`） | ⬜ | |

---

## 後回し事項

| ID | 内容 | 理由 |
|---|---|---|
| D-1 | 客先ごとの公開/非公開切り替え | 要望が出たら |
| D-2 | 共有リンク（期限・パスワード付き） | 現状は「ログイン本人のみ」で確定 |
| D-3 | オフライン表示（PWA） | 現状はオンライン前提で確定 |
| D-5 | ✅ 実装済み（2026-11-06）: `portfolio:prune-stale`（毎時・24時間経過の未完了作品を実体ごと削除）。さくらの cron は `schedule:run` が毎分動いているので追加設定不要 | |
| D-6 | さくら本番での PDF 変換の実機確認（CMYK・フォント埋め込み・巨大ページ・実行時間） | デプロイ前（Phase 7）に一時ファイルでの変換テストを SSH で行う（要ユーザー確認）。ローカルの Ghostscript 10 では動作確認済み |
| D-7 | さくら本番での動画配信の実機確認（PHP 経由の Range 配信・CPU時間・同時再生）。重い場合は .htaccess での直接配信などを検討 | Phase 7 前 |
| D-8 | HEVC（iPhone「高効率」設定）の動画の実機確認 | 2026-12 の社用スマホ刷新後に試す。現行の iPhone SE2 は HEVC 撮影に非対応（H.264 のみ）で、詰め替え経路は確認済み。HEVC は再エンコードが必要で、ブラウザの対応に左右される。変換できなければ原本（300MB以下）をそのまま送る。社用 iPhone は「設定→カメラ→フォーマット→互換性優先」を運用で周知すると確実 |
| D-4 | Leader / Coordinator / Clerk / 校正 / Prepress のナビにポートフォリオのボタンを出す | 試用中は Admin（admin エリア）と User ナビのみ。許可オンの人がこれらのロールだと、いまは user エリアのナビ経由になる |

## 作業ログ

| 日付 | 内容 |
|---|---|
| 2026-11-06 | Codex レビュー（gpt-6.1-sol）対応: ①[P1] `new Quality(数値)` は「品質レベル」でビットレートではなかった→`new Quality({ bitrate })` に修正（映像2.5Mbps・音声128kbps）。以前 iPhone MOV が失敗した真因の一つで、再エンコード経路（HEVC・重い動画）が実質壊れていた ②[P2] PDF のページ画像化中に容量上限を再確認（超過したら作品ごと破棄・422）。`PortfolioStorage::quota()` を追加（テストは `filesystems.portfolio_quota` で差し替え）。PHPUnit 55件成功 |
| 2026-11-06 | Phase 6: 定期掃除 `portfolio:prune-stale`（毎時）実装・テスト、ChangelogSeeder（portfolio-1）追記、CONSOLIDATED_01 / 09 更新。他ロールのナビは「今のまま」に決定 |
| 2026-11-06 | Phase 5 完了（閲覧ページ: 表紙一覧・全画面スライドショー・PDF見開きページめくり・動画ポスター再生・スワイプ/キー。`ViewController`・`Viewer/{Slideshow,PdfSpread,VideoStage}.vue`・`Pages/Portfolio/View.vue`。PHPUnit 52件成功・npm run build 済み）。見た目・動きはブラウザでユーザー確認待ち |
| 2026-11-06 | 作品追加モーダルに「動画がアップできないとき」のトグル式メモを追加（互換性優先の設定・形式と上限・注意・エラー時の連絡）。動画でエラーが出たときは自動で開く |
| 2026-11-06 | iPhone SE2 の MOV（H.264）でアップ成功をユーザーが確認。HEVC は刷新後に再確認（D-8） |
| 2026-11-06 | 不具合修正（iPhone MOV で「映像トラックが見つかりません」）: 原因は mediabunny が変換不能トラックを黙って捨て、音声だけの MP4 を「成功」にしていたこと（再エンコード指定でエンコーダが使えず映像が discard）。対策: ①軽い H.264（長辺1280px以下・4Mbps以下）は**再エンコードせず MP4 へ詰め替え**（fastStart 付き・エンコーダ不要）②出力に映像が残ったか必ず検査（`utilizedTracks`/`discardedTracks`）③変換後が読めなければ原本（300MB以下）で再試行 ④ポスター取得は mediabunny → `<video>` 要素へフォールバック ⑤実ファイル IMG_0094.MOV（H.264 320x568 15秒 1.7MB）で詰め替えを Node 検証済み（映像・音声とも保持、moov 先頭） |
| 2026-11-06 | Phase 4 完了（動画: ブラウザ圧縮→分割アップ→MP4/MOV検証→ポスター/サムネ生成→Range配信。PHPUnit 44件成功・npm run build 済み・`poster_path` migrate 済み(ローカル/テストDB)）。ブラウザ側の圧縮はユーザーの実機確認待ち |
| 2026-11-06 | Phase 3 完了（PDF: 分割アップ→検証→数ページずつ画像化→ページ送り表示。PHPUnit 34件成功・npm run build 済み）。`PdfRasterizer` / `PdfUploadController` / `chunkUpload.js` 新設、ImageLightbox を複数ページ対応に |
| 2026-11-06 | 作品詳細モーダルを画像中心の2カラムに変更（左に元解像度の画像、右に入力欄）。画像クリックで全画面ライトボックス（`ImageLightbox`：クリックで等倍、Esc で戻る/閉じる）。ぼかしサムネ→本画像の切り替え |
| 2026-11-06 | 仕様再変更: ①Admin は閲覧専用（自分のポートフォリオも持たない）。作成・編集・削除は本人のみ（`PortfolioAccess::canView/canEdit`・サーバー側で 403）②作品追加を「＋作品を追加」モーダルに変更（①タイトル→②説明→③ファイル→分類）。`UploadZone` 廃止、`ItemCreateModal` 新設 ③閲覧専用モード（`readOnly`・閲覧するメンバー選択）④`addToast` 不具合を修正（`showToast`）。PHPUnit 26件成功
| 2026-11-06 | 仕様変更（上記で一部置換）: ①Admin/SuperAdmin は全メンバーのファイルを管理、ユーザーは自分のみ（`PortfolioAccess`・`owner_id`・管理するメンバー切替）②Admin/SuperAdmin は許可なしで利用可 ③SuperAdmin ナビのタブを削除（admin エリア経由）④ユーザーナビに「ポートフォリオ」追加（`user.portfolio.*`）⑤`admin.portfolio.permissions.*` を登録（SuperAdmin が admin エリアで開いたときの Ziggy エラーを解消）。PHPUnit 25件成功 |
| 2026-11-06 | Phase 2 完了（管理画面・コレクション/タグ/作品CRUD・画像アップ・認証付き配信・アクセント色。PHPUnit 16件成功・npm run build 済み）。`sortablejs` 追加。テストは専用の偽ディスク（`filesystems.portfolio_disk`）を使用 |
| 2026-11-06 | 修正: ①ナビがユーザー用になる不具合→ルートを `superadmin.portfolio.*` / `admin.portfolio.*` に変更（クロージャ方式・`routePrefix` prop）②絞り込みをロール→担当（assignment）に変更 |
| 2026-11-06 | Phase 1 完了（ローカル migrate 済み・PHPUnit 7件成功・npm run build 済み）。ブラウザ確認はユーザー。テスト用に sunbwork_testing も migrate |
| 2026-11-06 | Phase 0 完了（0-1〜0-5）。ライブラリ: mediabunny / 自作 CSS 3D / sortablejs |
| 2026-11-06 | ユーザー確定: 権限画面は案B（管理画面内ボタン）／個人容量 10GB／動画上限 1GB（キーホルダー等の短尺動画用途）。PLAN 反映 |
| 2026-11-06 | 構想ヒアリング（個人別／コレクション＋タグ／PDF 画像化／動画ブラウザ圧縮／白＋モノトーン＋アクセント色選択ほか）。さくら SSH で Imagick・gs あり／ffmpeg なしを確認。PLAN/MANAGER/PROMPT 作成 |
