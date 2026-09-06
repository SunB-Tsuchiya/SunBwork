# SALES_ANALYSIS2_PROMPT.md — 引継ぎ・新セッション再開用プロンプト（2回目）

最終更新: 2026-09-05

## 1. これは何か

売上分析機能（Excel取込・月次/年次/期別分析・同月/左右比較・得意先/商品分析・会社別データ分離）の
開発状況の引継ぎ文書。`SALES_ANALYSIS1_PROMPT.md`（Phase 0〜8時点、2026-09-04付）は完全に古くなった
ため、この2回目を正本として使うこと。1PROMPT.mdは過去の経緯を辿りたいとき以外は読まなくてよい。

設計の正本は`SALES_ANALYSIS_PLAN1.md`、進捗・判断ログの正本は`SALES_ANALYSIS_MANAGER1.md`。
このファイルはそれらの要約＋再開用の入口でしかない。**詳細な理由や判断根拠はMANAGER1.mdの
判断ログ・作業ログを見ること（このファイルには要約以上のことは書かない）。**

## 2. 現在の状態（ざっくり）

- **Phase 0〜19: すべて完了・さくら本番へデプロイ済み。** 内訳（詳細はPLAN1.md該当Phase節）:
  - Phase 0〜8: DB分離・権限・Excel取込検証・集計・データ登録状況・年次分析・同月比較・
    左右比較・得意先統合・Excel出力（年次分析画面のみ）
  - Phase 11〜17: REVIEW3対応の可視化刷新（PeriodNavigator/RankingPanel共通化）・
    月次/年次/同月比較/得意先分析への展開・期別分析（4月始まり会計年度）画面の新設
  - Phase 18: 商品分析画面の新規追加（新規/取扱終了商品パネル、ProductNameNormalizer）
  - **Phase 19: 会社別データ分離（マルチテナント化）。サン・ブレーンに加えサンエー印刷にも対応**
- **アーキテクチャ（U-4）は解決済み**: SunBWork本体に残す（切り出さない）と確定
- **さくら本番デプロイは既に2回実施済み**（2026-09-05）。実データ移行（mysqldump）・
  会社別データ分離の両方とも本番反映済み。次に何かをデプロイする際は通常の
  `DEPLOY_SAKURA.md`6ステップ手順のみでよい（`SALES_DB_*`設定・DB作成は完了済み、
  再作成不要）
- テスト: `tests/Feature/SalesAnalysis`+`tests/Unit/SalesAnalysis`配下**275件・全成功**
  （実行は必ず`--user sail`で。3節参照）
- Codexへ全体レビューを1回実施済み（2026-09-05、Phase1〜18の差分に対して）。
  指摘3件はすべて対応済み（未追跡ファイルの誤検知1件、離脱得意先消失バグ・増減額上位の
  符号フィルタ漏れの実バグ2件）

## 3. 作業を始める前に必ず守ること

- **`docker compose exec`は必ず`--user sail`を付ける。** 既定のroot実行だとstorage配下の
  所有権事故や、複数DB接続を跨ぐ`migrate:fresh`等での事故を招く。
- 本番sales DBのレコード内容（得意先名・品名・金額等）は、SSH・SQL・Tinker・DBクライアント・
  dump・ログいずれでも閲覧しない。件数比較等の集計値の確認は可（過去に実施済み）。
  ローカル開発・テストは架空データのみ使用する。
- **`php artisan tinker`で書き込み系操作（`updateOrCreate`等）を安易に実行しない。**
  既存データのactive pointerを誤って上書きする事故が過去に発生している。動作確認は
  `RefreshDatabase`が効くPHPUnitテスト内で行うか、既存データと衝突しない架空の年月・部署を使うこと。
- **破壊的DB操作（`dropAllTables`・`migrate:fresh`・`DROP TABLE`等）を含むBashコマンドは、
  ローカルフック（`.claude/hooks/block_destructive_db.py`）で機械的にブロックされる。**
  git管理外のローカル専用設定。
- **会社別データ分離（Phase 19）以降、`SalesQueryService`/`SalesExportService`/
  `ClientGroupService`のメソッドを呼ぶ前は必ず`->forCompany($companyId)`を呼ぶこと。**
  呼ばずに集計メソッドを実行すると`LogicException`が飛ぶ（意図的な安全装置）。
  コントローラー側の`$companyId`は`ResolvesSalesAnalysisCompany`トレイトの
  `salesAnalysisCompanyId()`（nullable、SuperAdminの会社切替が未選択ならnull）または
  `requireSalesAnalysisCompanyId()`（API用、null時422で止める）で解決する。
  テストは`Tests\Concerns\RefreshesSalesDatabase`の`setUp()`がテスト用会社を自動作成し
  セッションへ自動設定するので、通常は個別対応不要（`$this->salesTestCompanyId()`が使える）。
- 部署区分（企画/制作/オンデマンド等）は`SalesDepartments`の静的定数ではなく
  `sales_department_definitions`テーブル参照になっている。新しい会社を追加する場合は
  そのテーブルへの投入（`SalesDepartmentDefinitionSeeder`参照）が必要。
- 新しいSalesAnalysisページを作る場合、Inertiaのpropsに`hasCompanySelected`を含め、
  SuperAdminが会社未選択のときに「会社が選択されていません。画面右上の会社切替から
  対象の会社を選択してください。」という案内を表示すること（既存11画面の実装を参照）。
  自動fetchするページは`hasCompanySelected`がfalseなら要求を出さないようガードする
  （422を予防するため）。
- 大きな設計変更や新画面着手前は、方針をユーザーに示して確認を取ってから実装する。
  不明点は一度に一つだけ質問する（CLAUDE.md記載の最重要ルール）。
- Vue/JS変更後は`npm run build`、`routes/web.php`変更後はZiggy再生成
  （`php artisan ziggy:generate resources/js/ziggy.js`）を忘れない。
- **axiosでbooleanをGETクエリへ渡すときは必ず`? 1 : 0`のように数値化すること。**
  Laravelの`'boolean'`バリデーションルールは文字列`"true"`/`"false"`を受け付けず422になる。

## 4. 必読ファイル（着手前に全文）

- `AGENTS.md`（機密規則・破壊的DB操作の禁止事項を含む）
- `z_instructions/SALES_ANALYSIS_PLAN1.md`（設計正本。**Phase 19節が会社別データ分離の詳細設計**）
- `z_instructions/SALES_ANALYSIS_MANAGER1.md`（進捗・判断ログの正本。全経緯はここ）
- `z_instructions/CONSOLIDATED_01_layout_and_ui.md`（新規ページ作成前に必須）
- さくら本番作業をする場合は`z_instructions/DEPLOY_SAKURA.md`

（`SALES_ANALYSIS_REVIEW2.md`/`REVIEW3.md`/`SALES_ANALYSIS_EXCEL_VALIDATION_REVIEW.md`は
Phase 0〜18時点の過去レビュー記録。優先度は低いが、当時の設計判断の経緯を追いたい場合に参照）

## 5. 特に重要な保留事項

### 5.1 サンエー印刷の利用許可付与はユーザー（SuperAdmin）の作業（Claude側は対応不要）

会社別データ分離のデプロイ後、サンエー印刷所属のAdmin（竹内様・小島様）が実際に売上分析機能を
使うには「売上分析 利用許可設定」画面からの許可付与が必要。**この付与作業はユーザー自身が
行うと明言済み（2026-09-05）。Claude側から特定ユーザーへ許可を付与する対応は不要。**

### 5.2 Excel出力の他画面拡張は保留

同月比較・左右比較・得意先分析・商品分析へのExcel出力拡張は、経理側の希望フォーマット
（会社規定の書類形式等）をユーザーが確認してから対応する方針。現状は年次分析・期別分析画面のみ対応。

### 5.3 バックアップ機能（Phase 9）は未着手

- U-1（暗号化backupの外部保存先）・U-3（年末backup保持年数）は未決のまま
- アーキテクチャ判断（U-4）は解決済みなので、着手条件は満たしている。ただしユーザーから
  明示的な着手指示が出るまで先回りして着手しないこと（過去に「まだやらないでいい」と
  明示されたPhaseのため）

### 5.4 サンエー印刷は現状「全社」単一の部署区分のみ

将来サンエー印刷が複数の部署区分（例: 印刷ライン別）を必要とする場合は、
`sales_department_definitions`へ行を追加するだけで対応できる設計になっている
（コード変更は不要、`php artisan tinker`かSeederで投入）。

## 6. 次にやること

**現時点で確定した「次のタスク」は無い。** 売上分析機能はPhase 0〜19まで完了し、さくら本番へ
デプロイ済みの安定状態にある。次にこの機能へ着手する場合は、ユーザーからの新しい要望
（追加画面・追加会社・バグ報告等）を起点にすること。着手前に必ず：

1. `SALES_ANALYSIS_PLAN1.md`/`MANAGER1.md`の該当Phase節（無ければ新規Phaseとして）に
   設計を先に追記し、ユーザーへ提示して確認を取る
2. 5件以上のファイルにまたがる大規模な変更になりそうな場合は、着手前にその旨をユーザーに伝える
3. 実装後は必ず`docker compose exec --user sail laravel bash -lc "php artisan test --filter=SalesAnalysis"`
   で全件成功を確認してから完了報告する

## 7. 再開用の短縮プロンプト

新しいセッションでそのまま貼り付けて使える形。

```text
売上分析機能の続きに着手します。
まず z_instructions/SALES_ANALYSIS2_PROMPT.md を全文読み、そこに書かれた必読ファイル
（AGENTS.md、SALES_ANALYSIS_PLAN1.md、SALES_ANALYSIS_MANAGER1.md、
CONSOLIDATED_01_layout_and_ui.md）を読んでから、私からの具体的な依頼内容を待ってください。

Phase 0〜19（会社別データ分離含む）はすべて完了し、さくら本番へデプロイ済みです。
確定した未着手タスクは無いので、私が伝える新しい要望から設計を始めてください。

必ず守ること:
- SalesQueryService等の集計メソッドを呼ぶ前は必ずforCompany($companyId)を呼ぶこと
  （2PROMPT.md 3節参照）
- 大きな変更は着手前に設計をPLAN1.md/MANAGER1.mdへ追記し、私に提示して確認を取ること
- 不明点は一度に一つだけ質問すること
- 本番sales DBのレコード内容は閲覧しないこと。docker compose execは必ず--user sailを付けること
- 実装後は必ずSalesAnalysis配下の全テスト成功を確認してから完了報告すること
```
