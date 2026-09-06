# SALES_ANALYSIS3_PROMPT.md — Phase 20 独自受注対応 引継ぎプロンプト

最終更新: 2026-09-06

## 1. 目的

売上分析機能Phase 20「サン・ブレーンの受注経路分離」をClaude Codeが実装するための入口。
Phase 0〜19は完了・本番反映済みであり、今回の実装は未着手である。

設計の正本:

- `z_instructions/SALES_ANALYSIS_PLAN1.md`の「Phase 20」
- 進捗と判断: `z_instructions/SALES_ANALYSIS_MANAGER1.md`の「Phase 20」

本ファイルだけで実装判断をせず、上記Phase 20節を全文読むこと。

## 2. 最初に読むファイル

1. `AGENTS.md`
2. `z_instructions/SALES_ANALYSIS2_PROMPT.md`（Phase 0〜19の現状）
3. `z_instructions/SALES_ANALYSIS_PLAN1.md`（特にPhase 19・20）
4. `z_instructions/SALES_ANALYSIS_MANAGER1.md`（特にPhase 19・20と事故記録）
5. `z_instructions/CONSOLIDATED_01_layout_and_ui.md`
6. 関連するmigration、SalesImportService、SalesImportValidator、SalesQueryService、SalesExportService、
   SalesAnalysis配下のcontroller/Vue/test

## 3. 確定仕様の要約

- サン・ブレーンの売上を「サンエー印刷経由」と「独自受注」に分け、合計も表示する。
- DB内部は新規`order_channel`: `standard`/`direct`。既存`source_type`を流用しない。
- 既存データはすべて`standard`へ後方補完する。
- ファイル名末尾が正確に`_独自.xlsx`なら`direct`、通常の規定名なら`standard`。
- ファイル名はサーバー側で厳格解析し、規則外を手入力で救済しない。
- 1ファイル1経路。企画・制作・オンデマンドすべて同じ規則。
- active pointer、版管理、diff、重複検証は経路別。direct再取込でstandardを切り替えない。
- 経路をまたぐ同一受注Noは許可。同一経路内の既存重複検証は維持する。
- 得意先は経路をまたいで合算し、合計・両経路金額・独自比率を示す。
- サン・ブレーンの月別登録完了は両経路がそろった場合。片方だけはpartialで、0円扱いしない。
- 0円専用ファイル対応は今回の対象外。現在のExcel検証規則を変更しない。
- 月次・年次・期別・同月・左右比較・得意先・商品・分類・項目・検索へ内訳を伝播する。
- 年次・期別の既存Excel出力へ内訳を追加する。新しい出力画面は作らない。
- 対象会社は会社コード`SUNBRAIN`だけ。サンエー印刷会社側の既存挙動は変えない。

## 4. 実装時の重要注意

- `SalesQueryService`/`SalesExportService`/`ClientGroupService`は、呼出前に必ず
  `forCompany($companyId)`を実行する。
- 会社IDをハードコードせず`SUNBRAIN`の会社コードで機能可否を一か所に集約する。
- `sales_orders`へ経路を重複保存せず、import/active monthから取得する。
- `[company_id, file_sha256]`uniqueは維持する。
- partial期間を0円や正式な増減として表示しない。
- 負額があるため、構成比が意味を持たない場合は`direct_share=null`とする。
- migrationのdownでdirect行を削除しない。旧uniqueへ戻せない場合は明示的に停止する。
- 本番sales DBのレコードをSSH、SQL、Tinker、dump、ログ、臨時スクリプトで見ない。
- DBテストは架空fixtureだけを使用し、dockerコマンドには必ず`--user sail`を付ける。
- 既存のユーザー変更、`public/build`、storage等の無関係なdirty差分へ触れない。
- 実装だけを行い、本番SSH・デプロイはユーザーの別途明示指示まで行わない。

## 5. 実装手順

`SALES_ANALYSIS_MANAGER1.md`の20-1から20-12を順に進め、完了ごとに状態と証跡を更新する。
大きな判断変更が必要なら勝手に拡張せず、一度に一問だけユーザーへ確認する。

最低限の検証:

```bash
docker compose exec --user sail laravel bash -lc "php artisan test --filter=SalesAnalysis"
npm run build
```

関連変更のリスクに応じてプロジェクト全体テストも実施する。routesを変更した場合だけZiggyを再生成する。
実装完了時はChangelogSeederと関連CONSOLIDATED文書を更新し、テスト件数・build結果・未対応事項を
MANAGER1へ記録する。

## 6. Claude Codeへ渡す短縮指示

```text
売上分析Phase 20「サン・ブレーンの独自受注対応」を実装してください。
最初に AGENTS.md と z_instructions/SALES_ANALYSIS3_PROMPT.md を全文読み、そこから指定される
SALES_ANALYSIS_PLAN1.md Phase 19・20、SALES_ANALYSIS_MANAGER1.md Phase 19・20、UI規則、関連実装を
確認してください。設計正本はPLAN1のPhase 20です。

本番sales DBのレコードは絶対に閲覧せず、架空fixtureだけを使ってください。
実装進捗はMANAGER1の20-1〜20-12へ記録してください。本番デプロイは行わないでください。
```
