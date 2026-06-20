# Codex / Claude 共通引き継ぎ台帳

> 対象リポジトリ: `/home/w229/SunBwork`  
> 作成: 2026-06-19 12:04:53 JST (+0900)  
> 用途: CodexとClaudeが、互いの直前作業を安全に引き継ぐための共通記録

---

## 1. このファイルを使う合図

### 作業終了時

ユーザーから次の趣旨の指示があった場合、作業を終えるAIが本ファイルへ引き継ぎ記録を追加する。

- 「Claudeに引き継ぎ」
- 「Codexに引き継ぎ」
- 「引き継ぎを書いて」
- 「引き継ぎファイルを更新して」

記録後、追加した日時と概要をユーザーへ報告する。

### 作業開始時

ユーザーから次の趣旨の指示があった場合、作業を始めるAIはコード変更前に本ファイルを読む。

- 「引き継ぎファイルを見て」
- 「Claudeの作業を引き継いで」
- 「Codexの続きから」

最新記録だけで判断せず、関連する過去記録、設計書、管理ファイル、実際のコード、`git status` を照合してから作業する。

---

## 2. 共通運用ルール

1. 日時は必ず `Asia/Tokyo` のJSTで秒まで記録する。
2. 新しい引き継ぎ記録は「4. 引き継ぎ履歴」の一番上へ追加する。
3. 過去の記録は削除・書き換えない。訂正は新しい記録として追加する。
4. 作業者は `Codex` または `Claude`、引き継ぎ先も明記する。
5. 推測と確認済み事実を分ける。未確認事項は「未確認」と書く。
6. 完了、未完了、次に行うこと、変更ファイル、検証結果を省略しない。
7. エラー、暫定対応、既知のリスク、ユーザー判断待ちを必ず残す。
8. 秘密情報、パスワード、APIキー、Cookie、個人情報は記載しない。
9. ユーザーや他AIの未コミット変更を自分の成果として記載しない。
10. 引き継ぎを読んだ側も、実ファイルと現在のDB・ルート・ビルド状態を再確認する。

### AI別の優先ルール

- Codexは最初に `/home/w229/SunBwork/AGENTS.md` を読む。
- Claudeは最初に `/home/w229/SunBwork/CLAUDE.md` を読む。
- UI作業では `z_instructions/CONSOLIDATED_01_layout_and_ui.md` を読む。
- NSystem作業では `z_instructions/NSYSTEM_GUIDE.md` を読む。
- 本ファイルは作業履歴であり、上記ルールや最新コードより優先される仕様書ではない。

---

## 3. 追記テンプレート

以下をコピーし、「4. 引き継ぎ履歴」の先頭へ追加する。

```markdown
### YYYY-MM-DD HH:MM:SS JST - Codex → Claude

**依頼・目的**

- 

**完了した作業**

- 

**未完了・次に行う作業**

- なし / 具体的な内容

**変更ファイル**

- `path/to/file`: 変更内容

**DB・設定・コマンド実行**

- なし / 実施内容

**検証結果**

- テスト:
- Lint:
- Build:
- 手動確認:

**注意点・判断事項**

- 

**関連資料**

- `z_instructions/...`
```

---

## 4. 引き継ぎ履歴

<!-- 新しい記録をこのコメントの直後へ追加する -->

### 2026-06-19 18:53:57 JST - Codex → Claude

**依頼・目的**

- `/n-demo` の学校カードを年度別Mコード順で表示したい。
- Mコードは年度ごとの中核データとしてDBへ保持し、年度ボタンで表示年度を切り替えたい。
- 同年度のNコード重複はエラー扱いとしつつ、2025年度以降の `M109` だけ `4551 / 開智中学校` と `4751 / 開智所沢中等教育学校` の正式共有例外として扱いたい。
- `2026 M106 4331 → 4335` は現状運用に合わせて `4331` 扱いにしたい。

**完了した作業**

- `n_publication_entries` を「1掲載行 = 1校 = 1試験」に寄せる追補migrationを追加し、`n_publication_entry_exams` を廃止した。
- `NPublicationCatalogImportService` を新規実装し、`z_NDBSystem/Nコードリスト2022.xlsx` ～ `2026.xlsx` をヘッダー名で解決して取り込むようにした。
- `2025/2026 M109` は `4551` と `4751` の2掲載行へ分割して保存するよう実装した。
- `2026 M106 4331 → 4335` は監査注記を残しつつ、登録値は `4331` を採用するよう実装した。
- `n-system:import` を拡張し、既存の問題JSON取込に加えて5年度Mコード取込も同時に実行するようにした。
- `/n-demo` 一覧を、選択年度の `n_publication_entries.mikuni_code` 昇順で表示するよう変更した。
- `/n-demo` に年度ボタンを追加し、問題文書が存在する年度だけ表示するようにした。現状のローカルDBでは `2024` のみ表示される。
- `NDBSCHEMA_PLAN1.md`、`NDBSCHEMA_MANAGER1.md`、`NDBSCHEMA1_PROMPT.md`、`NSYSTEM_GUIDE.md`、`ChangelogSeeder.php` を更新した。
- `nsystem-schema-2` を `ChangelogSeeder` に追加し、ローカルDBへseedした。

**未完了・次に行う作業**

- 年度・入試系列フィルターのUI追加は未着手。
- `z_instructions/CODEX_CLAUDE_HANDOVER.md` 以外の設計3ファイルは更新済みだが、全体完了ではないため `archived/` へは移していない。
- さくら本番デプロイ、Gitコミット、PR化は未実施。

**変更ファイル**

- `database/migrations/2026_06_19_183000_make_n_publication_entries_one_school_one_exam.php`: `n_publication_entries` へ `school_id` / `exam_id` を追加し、旧pivotを廃止。
- `app/Services/NSystem/NPublicationCatalogImportService.php`: 5年度ExcelのMコード・学校監査取込、M109例外、2026 M106の4331採用。
- `app/Console/Commands/NSystem/NSystemImport.php`: 問題JSON取込後に5年度Mコード取込も行うよう拡張。
- `app/Http/Controllers/NSystem/NdemoController.php`: `/n-demo` 一覧の年度選択とMコード順表示。
- `resources/views/n_system/demo/index.blade.php`: 年度ボタン追加、Mコード表示追加。
- `app/Models/NSystem/NPublicationEntry.php`: `publicationEdition` / `school` / `exam` リレーション追加。
- `app/Models/NSystem/NPublicationEdition.php`: `publicationEntries` リレーション追加。
- `app/Models/NSystem/NExam.php`: `publicationEntries` リレーション追加。
- `app/Models/NSystem/NSchool.php`: `publicationEntries` リレーション追加。
- `tests/Feature/NSystem/NPublicationCatalogImportTest.php`: 5年度Mコード取込と例外処理のテスト。
- `tests/Feature/NSystem/NQuestionSearchTest.php`: `/n-demo` の年度ボタンとMコード順表示のテスト追加。
- `z_instructions/NDBSCHEMA_PLAN1.md`: 2026 M106の扱いと実装済み状態を反映。
- `z_instructions/NDBSCHEMA_MANAGER1.md`: 実装完了状況、ローカル反映、テスト結果を追記。
- `z_instructions/NDBSCHEMA1_PROMPT.md`: 再開時の最新状態を更新。
- `z_instructions/NSYSTEM_GUIDE.md`: Mコード取込、年度表示、件数を更新。
- `database/seeders/ChangelogSeeder.php`: `nsystem-schema-2` を追加。

**DB・設定・コマンド実行**

- `docker compose exec laravel bash -lc "php artisan migrate --force"` 実行済み。
- `docker compose exec laravel bash -lc "php artisan n-system:import --force"` 実行済み。
- `docker compose exec laravel bash -lc "php artisan db:seed --class=ChangelogSeeder --force"` 実行済み。
- ローカルDB確認:
  - `n_publication_editions` = 5件（2022-2026）
  - `n_publication_entries` = 894件
  - `/n-demo` の表示対象年度 = 2024のみ

**検証結果**

- テスト: `tests/Feature/NSystem/NPublicationCatalogImportTest.php`、`tests/Feature/NSystem/NQuestionSearchTest.php`、`tests/Unit/NSystem/NQuestionSearchServiceTest.php` の合計16件・64 assertions成功。
- Lint: 未実施。
- Build: 未実施。今回の変更はPHP / Blade / migration / docsのみ。
- 手動確認:
  - `n-system:import --force` 実行後、Mコード掲載行が5年度分入ることを確認。
  - `publicationEntries.exam.documents` を基準に、年度ボタンが2024のみになることをtinkerで確認。

**注意点・判断事項**

- `2026 M106 4331 → 4335` は、ユーザー判断により「監査原文は残すが登録値は4331」が正。`4335` へ切り替えないこと。
- `M109` 共有は 2025年度以降の `4551 / 4751` だけを許可する正式例外。その他の同年度Mコード重複はエラー扱い。
- `n-system:import --force` の結果、問題は `2422` 件、解答は `2376` 件、スキップは `132` ファイルだった。既存JSONの不足により5年度問題文書が揃っているわけではない。
- `/n-demo` の年度ボタンは「掲載履歴がある年度」ではなく「問題文書がある年度」だけ表示する実装。
- worktree には今回以前から untracked / modified が混在している。特に `public/build` の既存差分は戻さないこと。

**関連資料**

- `z_instructions/NDBSCHEMA_PLAN1.md`
- `z_instructions/NDBSCHEMA_MANAGER1.md`
- `z_instructions/NDBSCHEMA1_PROMPT.md`
- `z_instructions/NSYSTEM_GUIDE.md`

### 2026-06-19 12:04:53 JST - Codex → Claude

**依頼・目的**

- クライアント提案用中学入試問題DBデモ `/n-demo` の全文検索で、入力語を含まない問題までヒットする問題を修正。
- Vue/Inertiaを使ったリアルタイム検索、絞り込み、一致箇所表示、対象大問への遷移とハイライトを追加。

**完了した作業**

- MySQL ngram FULLTEXTを候補抽出に限定し、リテラルLIKEで実際の本文一致を保証。
- `exact`（そのまま含む）、`all`（すべての語）、`any`（いずれかの語）の3モードを実装。
- 検索結果を20件ページングし、科目・学校・カテゴリ絞り込みを追加。
- 検索画面をNSystem専用レイアウトのInertia/Vueページへ移行。
- 300ms debounce、日本語IME対応、古いHTTP通信のキャンセル、検索条件のURL保持を実装。
- 一致箇所を中心に前後約100文字の安全なスニペットを表示。
- 検索窓をヘッダーから「検索方法」カードの直前へ移動。
- 結果リンクへ検索語を引き継ぎ、対象大問へ移動後に本文内の検索語を黄色でハイライト。
- 複数語とHTML要素をまたぐ一致を、対象大問内のテキストノード単位で強調。
- NSystemガイド、UI例外規則、ChangelogSeederを更新。
- NSEARCH設計・管理・再開プロンプトを `z_instructions/archived/` へ移動。

**未完了・次に行う作業**

- 機能実装上の未完了はなし。
- GUIブラウザが実行環境にないため、自動ブラウザによるPC・モバイルのスクリーンショット確認は未実施。ユーザーがローカル画面で表示を確認済みで「完璧」と評価。

**変更ファイル**

- `app/Http/Requests/NSystem/NQuestionSearchRequest.php`: 検索条件の正規化と検証。
- `app/Services/NSystem/NQuestionSearchService.php`: 厳密検索、絞り込み、関連度、ページング、スニペット、ハイライト付き遷移URL。
- `app/Http/Controllers/NSystem/NdemoController.php`: Inertia検索画面、JSON API、学校ページへのハイライト語受け渡し。
- `routes/nsystem.php`: `n-demo.search.results` JSONルート追加。
- `resources/js/layouts/NSystemDemoLayout.vue`: 外部クライアント向け専用レイアウト。
- `resources/js/Pages/NSystem/Search.vue`: リアルタイム検索画面と検索窓配置。
- `resources/js/Components/NSystem/`: 検索条件、結果カード、ページング。
- `resources/views/n_system/demo/school.blade.php`: 大問アンカー、対象大問強調、検索語ハイライト。
- `tests/Feature/NSystem/NQuestionSearchTest.php`: 検索API、Inertia画面、モード、特殊文字、絞り込み、ページング、遷移語のテスト。
- `tests/Unit/NSystem/NQuestionSearchServiceTest.php`: 語分割、LIKEエスケープ、マルチバイトスニペットのテスト。
- `z_instructions/NSYSTEM_GUIDE.md`: 新検索仕様と削除手順を更新。
- `z_instructions/CONSOLIDATED_01_layout_and_ui.md`: 外部クライアント向け独立デモのAppLayout例外を追記。
- `database/seeders/ChangelogSeeder.php`: `nsystem-search-1` を追加。

**DB・設定・コマンド実行**

- DBマイグレーション追加なし。
- `php artisan db:seed --class=ChangelogSeeder --force` をローカルDBで実行済み。
- 実MySQLで `exact`、`all`、`any`、記号を含む検索を確認済み。
- `npm run build` を実行し、`public/build` を更新済み。

**検証結果**

- テスト: NSystem Unit/Feature 12件、46アサーション成功。
- Lint: NSystem関連Vue 5ファイルのESLint成功。
- Build: `npm run build` 成功。
- 実DB: 「平安時代」exact検索68件。結果URLに `highlight[]` と `#daimon-*` が付くことを確認。
- Build警告: 既存コードのVite glob `as: 'url'` 非推奨警告が2件あるが、今回の変更由来ではない。

**注意点・判断事項**

- NSystemは社内管理画面ではなく外部クライアント向けデモ。社内用 `AppLayout` は使わず、`NSystemDemoLayout.vue` を使用する。
- 社内ナビゲーション、通知、Echo購読、社内ロール構造をゲストへ表示しない。
- ハイライト語は最大10件、各100文字に制限し、JSONとして安全にBladeへ渡している。
- 問題本文のDB値と `body_html` はハイライト処理で変更しない。ブラウザ上の対象大問DOMだけを加工する。
- 作業開始前からworktreeには多数の未コミット変更とbuild生成物が存在する。無関係な差分を戻さないこと。

**関連資料**

- `z_instructions/NSYSTEM_GUIDE.md`
- `z_instructions/CONSOLIDATED_01_layout_and_ui.md`
- `z_instructions/archived/NSEARCH_PLAN1.md`
- `z_instructions/archived/NSEARCH_MANAGER1.md`
- `z_instructions/archived/NSEARCH1_PROMPT.md`
