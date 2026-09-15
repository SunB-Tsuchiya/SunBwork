# Phase 20 レビュー依頼文書 — サン・ブレーンの受注経路分離（サンエー印刷経由／独自受注）

最終更新: 2026-09-07

Codexレビュー担当者向けの文書。差分（`--uncommitted`）だけでは読み取れない設計意図・スコープ判断・
既知の未対応事項をここにまとめる。詳細設計は`z_instructions/SALES_ANALYSIS_PLAN1.md`の
「Phase 20」節、進捗・判断ログは`z_instructions/SALES_ANALYSIS_MANAGER1.md`の同節を参照。

## 1. 目的・背景

サン・ブレーンの売上には「サンエー印刷から依頼される売上（サンエー印刷経由）」と
「サン・ブレーンが独自に受注する売上（独自受注）」の2種類があるが、これまでは区別せず
1本のデータとして扱っていた。本Phaseでは、この2経路を`order_channel`という新しい軸で分離し、
既存の会社別データ分離（Phase19、company_id軸）とは独立した第2の軸として追加した。

対象は会社コード`SUNBRAIN`（サン・ブレーン）のみ。サンエー印刷側の画面・運用・データは
一切変更していない。

## 2. 確定済み仕様の要点

- 内部値は`standard`（サンエー印刷経由）/`direct`（独自受注）の2種類。既存データはすべて`standard`。
- 受注経路の判定は**ファイル名だけ**で行う。basenameの末尾が正確に`_独自.xlsx`なら`direct`、
  それ以外の規定命名なら`standard`。規則外のファイル名は手入力による救済をせず拒否する。
- 1ファイル1経路。企画・制作・オンデマンドの3部署すべてに両経路が存在し得る。
- 同一受注Noが両経路に存在することを許可する（経路をまたいだ重複は正常）。同一経路内での
  他月重複検出は従来どおり維持する。
- 版管理・active pointer切替は経路ごとに完全に独立（standard/directが同じ会社・部署・年月に
  同時にactiveとして存在できる）。
- 月・部署の「登録完了」は両経路がそろって初めて成立する。片方だけなら「一部未登録」とし、
  0円と誤表示しない。
- 得意先・商品名は経路をまたいで合算し、経路別内訳（standard_amount/direct_amount/direct_share）
  を付加情報として表示する。

## 3. 実装済みの重要な設計判断（差分だけでは読み取れない点）

### 3.1 「サーバー側でファイル名を必須検証する」への実装方針

設計書（PLAN 20.3）は「サーバー側でファイル名を厳格検証する」としていたが、既存実装は
department_key/source_type/source_year/source_month等をすべて**ユーザーがフォームで選択した
値を正**として扱い、Excelタイトル行との照合のみを行う設計だった（フロント側のファイル名解析は
あくまで自動入力というUX上の補助）。

PLAN 20.3が「より安全で単純なら、サーバー解析値だけを正としてフォーム値を上書きしてよい」と
実装裁量を認めていたため、**サン・ブレーンに限り、サーバー側でファイル名を唯一の正本として
再解析し、フォーム送信値を完全に無視・上書きする**方式を採用した（`SalesOrderChannels::parseFilename()`、
`ImportController::preview()`）。他社（サンエー印刷等）は従来どおりフォーム値ベースのまま変更していない。
この方針転換はユーザーへ提示し承認を得たうえで実装している。

### 3.2 「合計金額」は既存コードを変更せずに自動的に正しくなる

`SalesQueryService::activeOrdersQuery()`は`sales_orders`と`sales_active_months`を
`sales_import_id`で結合し、`department_key`のみでフィルタする設計（`order_channel`では絞らない）。
standard/direct各1件のactive_month行が同一会社・部署・年月に共存しても、各`sales_order`は
自分の`sales_import_id`が指す片方の行としか結合しないため、**両経路の受注が自然に合算される**。
このためPhase20の実装コストは「合計を正しくする」ことではなく、「合計に加えて経路別内訳
（standard_amount/direct_amount/direct_share/registration）を追加する」ことに絞られている。
このロジックの正しさ（二重カウントが起きないこと）は重点的にレビューしてほしい。

### 3.3 内訳の実装パターン（3つの共通ヘルパー）

- `channelAmounts($rows)`: Eloquentコレクションを`order_channel`列で仕分けてstandard/direct合計を返す
- `registrationState($activeMonthRows)`: 0〜2件のactive_month行から`no_data`/`partial`/`complete`を判定。
  経路概念を持たない会社（サンエー印刷等）は`supportsChannelsFor()`がfalseを返すため、
  「1件でもあればcomplete」という**既存挙動と完全互換**になる（この後方互換性は要確認）
- `detailBreakdownQuery()`: `sales_order_details`→`sales_orders`→`sales_active_months`の
  JOINを新設し、明細レベル（分類・項目）の経路内訳を1クエリで取得する共通クエリ起点

この3つを軸に、`SalesQueryService`内の以下のメソッド群へ機械的に伝播させた:
monthlyTotal/cumulativeTotal/monthSeries/rangeFigures/clientRanking/detailBreakdown系
（detailBreakdown/periodDetailBreakdown/detailBreakdownMap/mergeDetailBreakdownForRange）/
得意先・商品ランキング系（rangeClientAggregates/rangeOrdersGroupedByClient/periodOrdersGroupedByClient/
rangeProductAggregates とそれぞれのmerge系）/annualSummary/fiscalYearSummary/sameMonthComparison/
sideBySideComparison/registrationStatusByDepartment/periodOrders/fiscalYearOrders。

`RankingPanel.vue`（月次/年次/期別/得意先/商品分析で共用するVueコンポーネント）は、
APIから返る行データに`standard_amount`があるかどうかで内訳列表示を自動判定する設計にしたため、
1箇所の修正で5画面のランキング表示すべてに反映されている。

## 4. 変更ファイル一覧

### 4.1 DB
- `database/migrations/2026_09_06_100001_add_order_channel_to_sales_imports_table.php`（新規）
- `database/migrations/2026_09_06_100002_add_order_channel_to_sales_active_months_table.php`（新規）
  - いずれも`default('standard')`で列追加と同時に既存行を後方補完（別途の後方補完migrationは無い）
  - `down()`はdirect行が残っている場合に例外を投げて停止し、データを自動削除しない

### 4.2 Backend（新規）
- `app/Services/SalesAnalysis/SalesOrderChannels.php`

### 4.3 Backend（変更）
- `app/Models/Sales/SalesImport.php` / `SalesActiveMonth.php`（fillable追加のみ）
- `app/Services/SalesAnalysis/SalesImportValidator.php`
- `app/Services/SalesAnalysis/SalesImportService.php`
- `app/Services/SalesAnalysis/SalesQueryService.php`（最大の変更、上記3ヘルパー＋伝播）
- `app/Services/SalesAnalysis/SalesExportService.php`（Excel出力への経路列追加）
- `app/Http/Controllers/SalesAnalysis/ImportController.php`（ファイル名解析の適用箇所）
- `app/Http/Controllers/SalesAnalysis/RegistrationStatusController.php`
- `app/Http/Controllers/SalesAnalysis/ImportHistoryController.php`
- `app/Http/Controllers/SalesAnalysis/MonthlyAnalysisController.php`
- `app/Http/Controllers/SalesAnalysis/AnnualAnalysisController.php`
- `app/Http/Controllers/SalesAnalysis/FiscalYearAnalysisController.php`
- `app/Http/Controllers/SalesAnalysis/SameMonthComparisonController.php`
- `app/Http/Controllers/SalesAnalysis/SideBySideComparisonController.php`
- `app/Http/Controllers/SalesAnalysis/ClientAnalysisController.php`
- `app/Http/Controllers/SalesAnalysis/ProductAnalysisController.php`
  （↑9コントローラーはいずれも`supportsOrderChannels`ページpropの追加のみ）

### 4.4 Frontend（変更）
- `resources/js/Components/SalesAnalysis/RankingPanel.vue`（内訳列の自動表示ロジック）
- `resources/js/Composables/useSalesChart.js`（`channelColors`定数追加）
- `resources/js/Pages/SalesAnalysis/Import.vue`
- `resources/js/Pages/SalesAnalysis/RegistrationStatus.vue`
- `resources/js/Pages/SalesAnalysis/ImportHistory.vue`
- `resources/js/Pages/SalesAnalysis/MonthlyAnalysis.vue`
- `resources/js/Pages/SalesAnalysis/AnnualAnalysis.vue`
- `resources/js/Pages/SalesAnalysis/FiscalYearAnalysis.vue`
- `resources/js/Pages/SalesAnalysis/SameMonthComparison.vue`
- `resources/js/Pages/SalesAnalysis/SideBySideComparison.vue`
- `resources/js/Pages/SalesAnalysis/ClientAnalysis.vue`
- `resources/js/Pages/SalesAnalysis/ProductAnalysis.vue`

### 4.5 テスト（新規）
- `tests/Unit/SalesAnalysis/SalesOrderChannelsTest.php`（12件）
- `tests/Feature/SalesAnalysis/SalesOrderChannelImportTest.php`（6件）
- `tests/Feature/SalesAnalysis/SalesQueryServiceChannelTest.php`（5件）
- `tests/Feature/SalesAnalysis/SalesExportServiceChannelTest.php`（3件）

### 4.6 その他
- `database/seeders/ChangelogSeeder.php`（`sales-analysis-order-channel-1`エントリ追加）
- `z_instructions/SALES_ANALYSIS_MANAGER1.md`（進捗・判断ログ更新）

## 5. 意図的にスコープ外とした項目（バグではなく既知の未対応）

- APIレベルの`order_channel=all|standard|direct`絞り込みフィルタ（PLAN20.6設計）は未実装。
  常に両経路合算＋内訳同時表示のみで、「独自受注だけに絞る」操作はできない。
- 得意先/商品の「新規・取扱終了」パネル（`productYearOverYearComparison`）、
  同月比較の「新規/離脱得意先」「増加額上位/減少額上位」リストには経路内訳を追加していない
  （合計値・判定ロジック自体は正しく機能する）。
- 同月比較の「得意先別年次推移」マトリクス表には内訳列を追加していない。
- 「合計/サンエー印刷経由/独自受注」を明示的に切り替えるUIトグルは無い（常時合算＋内訳表示のみ）。

## 6. 動作確認・既知の運用上の注意

- ローカル開発DBでmigration未適用のまま画面を開き、`Unknown column 'sales_active_months.order_channel'`
  による500エラーが実機で発生・`php artisan migrate --force`で解消済み（コードのバグではなく
  作業手順の抜け）。**本番デプロイ時も同様にmigrateを忘れないこと**（DEPLOY_SAKURA.mdの手順に
  migrateステップは含まれているため、手順通りに進めれば問題ない）。
- サン・ブレーンの既存データはすべて`standard`のみのため、独自受注ファイルを一度も取り込んで
  いない期間はすべて「一部未登録」と表示される。これは意図した仕様（ユーザー確認済み、
  2026-09-07）であり、対応不要。

## 7. テスト結果

- SalesAnalysis配下: 301件成功（既存275件＋Phase20新規26件）
- プロジェクト全体: 415件成功・27件skip（既存の無関係な設定ルート未登録によるもの、Phase20と無関係）
- `npm run build`: 成功

## 8. レビューで特に見てほしい観点

1. `channelAmounts()`/`registrationState()`/`detailBreakdownQuery()`の3ヘルパーが、
   経路概念を持たない会社（サンエー印刷等）に対して既存挙動と完全互換であること
   （`standard_amount=amount`・`direct_amount=0`・`registration`は実質無視される設計）
2. `activeOrdersQuery()`のJOIN条件がorder_channelで絞っていないことによる合計金額の
   二重カウント/漏れが無いこと（3.2節参照）
3. `SalesImportValidator::checkCrossMonthDuplicates()`の経路スコープが正しいこと
   （経路間の同一受注Noを許可しつつ、経路内の他月重複だけを検出できているか）
4. `SalesImportService::persistImport()`のactive pointer切替が経路ごとに独立しており、
   direct再取込でstandardのpointerを誤って変更しないこと
5. migrationの`down()`がdirect行を自動削除しない設計になっていること
6. `channelCaseSql()`で組み立てているSQL文字列断片（`'standard'`/`'direct'`固定値の埋め込み）に
   SQLインジェクションの余地が無いこと（ユーザー入力を含まない内部定数のみであることの確認）
7. `ImportController::preview()`でサン・ブレーンのファイル名解析結果がフォーム値を上書きする
   設計が、既存のUploadSalesWorkbookRequestバリデーション（department_key等のRule::in）と
   矛盾・迂回を生んでいないこと

## 9. レビュー結果と対応（2026-09-07実施）

`codex exec review`（PROMPT引数で本文書を読ませたうえで非コミット差分をレビュー）を実施。
指摘2件（いずれも[P2]）はいずれも実バグと判断し対応済み。

1. **[P2] ファイル名から解析した月の範囲検証が無い**
   （`SalesOrderChannels.php:91`）— `\d{1,2}`が1〜99を受理してしまい、
   `企画_2026年01-99月.xlsx`のような不正なファイル名がフォーム送信値を上書きすると
   `targetMonths()`で13〜99月分のactive pointerが作られ得る、という指摘。
   → `parseFilename()`に月の範囲チェック（1〜12かつ開始≦終了、外れる場合はnullを返し
   規則外ファイル名として拒否）を追加。回帰テスト5件追加（Unit）。
2. **[P2] 全部署合計時のregistration判定が部署単位になっていない**
   （`SalesQueryService.php:1763-1766`）— `department_key='all'`のとき、
   複数部署のactive行をまとめて`registrationState()`に渡していたため、
   「どこかの部署がstandard・別のどこかの部署がdirectを持っていればcomplete」と
   誤判定していた、という指摘。
   → 新設`registrationStateAcrossCells()`（部署×年月のセルごとに`registrationState()`を
   判定し、1セルでもpartialなら全体をpartialとする）を`monthlyFiguresForYear()`に適用。
   **横展開**: Codexが直接指摘したのは`monthlyFiguresForYear()`だけだったが、同じ集計
   パターン（'all'部署・複数月レンジをまとめて`registrationState()`に渡す）を持つ
   `rangeFigures()`（同月比較・左右比較のyear型期間で使用）にも同種の不具合があることを
   自己点検で発見し、同様に修正した。回帰テスト3件追加（Feature）。

対応後、SalesAnalysis配下テスト計309件成功（既存301件＋今回8件）。
