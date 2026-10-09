# SALES_ANALYSIS4_PROMPT.md — 引継ぎ・新セッション／Codex分散作業用プロンプト（4回目）

最終更新: 2026-10-08

## 1. これは何か

売上分析機能（Excel取込・月次/年次/期別分析・同月/左右比較・得意先/商品分析・会社別データ分離・
サン・ブレーン受注経路分離）の開発状況の引継ぎ文書。`SALES_ANALYSIS2_PROMPT.md`（Phase0〜19時点、
2026-09-05付）・`SALES_ANALYSIS3_PROMPT.md`（Phase20着手前の引継ぎ、2026-09-06付）は
**Phase 20完了により古くなった**ため、この4回目を正本として使うこと。2/3PROMPT.mdは過去の経緯を
辿りたいとき以外は読まなくてよい。

設計の正本は`SALES_ANALYSIS_PLAN1.md`、進捗・判断ログの正本は`SALES_ANALYSIS_MANAGER1.md`。
このファイルはそれらの要約＋再開用の入口でしかない。**詳細な理由や判断根拠はMANAGER1.mdの
判断ログ・作業ログを見ること（このファイルには要約以上のことは書かない）。**

このファイルはClaude Codeの新セッションだけでなく、**Codex（他のAIエージェント）に作業を
分散させる場合の入口文書としても使える**ように書いている。Codexに読ませる場合は本ファイルの
「6. Codexへの引継ぎ手順」を参照すること。

## 2. 現在の状態（ざっくり）

- **Phase 0〜20: すべて完了・コミット済み（2026-09-16に他機能と合わせてまとめてコミット）。**
  内訳（詳細はPLAN1.md該当Phase節）:
  - Phase 0〜8: DB分離・権限・Excel取込検証・集計・データ登録状況・年次分析・同月比較・
    左右比較・得意先統合・Excel出力（年次分析画面のみ）
  - Phase 11〜18: REVIEW3対応の可視化刷新・月次/年次/同月比較/得意先分析への展開・期別分析
    （4月始まり会計年度）画面・商品分析画面の新設
  - Phase 19: 会社別データ分離（マルチテナント化）。サン・ブレーンに加えサンエー印刷にも対応
  - **Phase 20: サン・ブレーンの受注経路分離（サンエー印刷経由／独自受注）。
    ファイル名（`_独自.xlsx`サフィックス）だけで経路を判定し、月次/年次/期別/同月/左右比較/
    得意先/商品分析・Excel出力・登録状況画面すべてに経路別内訳（standard_amount/direct_amount/
    direct_share/registration）を伝播済み**
- **Phase 20はCodexレビュー済み（2026-09-07実施、`z_instructions/SALES_ANALYSIS_PHASE20_REVIEW.md`
  参照）。指摘2件（ファイル名の月バリデーション欠如、全部署合計時のregistration誤判定）は
  いずれも実バグと判断し修正済み。**同種の不具合がCodex指摘箇所以外の`rangeFigures()`にも
  潜んでいたため横展開で修正済み（自己点検による発見、Codex指摘ではない）**
- **アーキテクチャ（U-4）は解決済み**: SunBWork本体に残す（切り出さない）と確定
- **さくら本番デプロイはPhase0〜17までは実施済み（2026-09-05）。Phase19（会社別データ分離）・
  Phase20（受注経路分離）は本番未デプロイ**。本番デプロイ時の個別の未対応事項はPLAN1.md
  「Phase 19」節末尾・MANAGER1.md「Phase 19」節末尾の「未対応（本番デプロイ時に別途必要）」を参照
- テスト: `tests/Feature/SalesAnalysis`+`tests/Unit/SalesAnalysis`配下**309件・全成功**
  （プロジェクト全体では423件成功・27件skip。実行は必ず`--user sail`で。4節参照）
- Phase20実装時にローカル開発DBへのmigration未適用による500エラーが実機で発生した実績あり
  （「独自受注が未登録だから」ではなく単純な作業手順の抜け）。**新しい環境・ブランチ切替後は
  必ず`php artisan migrate --force`を忘れないこと**

## 3. 作業を始める前に必ず守ること（Codex/Claude共通）

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
  git管理外のローカル専用設定のため、Codexを別環境で動かす場合は同じ保護が無い点に注意。
- **会社別データ分離（Phase 19）以降、`SalesQueryService`/`SalesExportService`/
  `ClientGroupService`のメソッドを呼ぶ前は必ず`->forCompany($companyId)`を呼ぶこと。**
  呼ばずに集計メソッドを実行すると`LogicException`が飛ぶ（意図的な安全装置）。
- **サン・ブレーンの受注経路分離（Phase 20）以降、`SalesOrderChannels::supportsChannelsFor($companyId)`
  がtrueの会社（会社コード`SUNBRAIN`）だけがstandard/direct経路の区別を持つ。** 他社は常に
  `standard`固定。新しい集計・画面を追加する場合、経路内訳を付けるならこの判定を必ず使うこと
  （会社コードをハードコードしない）。
- 部署区分（企画/制作/オンデマンド等）は`SalesDepartments`の静的定数ではなく
  `sales_department_definitions`テーブル参照になっている。
- 新しいSalesAnalysisページを作る場合、Inertiaのpropsに`hasCompanySelected`・
  `supportsOrderChannels`を含め、既存11画面の実装パターン（`RankingPanel.vue`の自動検出等）を
  踏襲すること。
- 大きな設計変更や新画面着手前は、方針をユーザーに示して確認を取ってから実装する。
  不明点は一度に一つだけ質問する（AGENTS.md記載の最重要ルール）。
- Vue/JS変更後は`npm run build`、`routes/web.php`変更後はZiggy再生成
  （`php artisan ziggy:generate resources/js/ziggy.js`）を忘れない。
- **axiosでbooleanをGETクエリへ渡すときは必ず`? 1 : 0`のように数値化すること。**
  Laravelの`'boolean'`バリデーションルールは文字列`"true"`/`"false"`を受け付けず422になる。

## 4. 必読ファイル（着手前に全文、または該当Phase節だけでも必須）

| ファイル | 読む範囲 |
|---|---|
| `AGENTS.md` | 全文（Codex向けルールファイル。機密規則・破壊的DB操作禁止・大規模作業プロトコルを含む） |
| `z_instructions/SALES_ANALYSIS4_PROMPT.md`（本ファイル） | 全文 |
| `z_instructions/SALES_ANALYSIS_PLAN1.md` | 設計正本。特に「Phase 19」「Phase 20」節。新しい作業をする場合は該当Phase節（無ければ新規追記） |
| `z_instructions/SALES_ANALYSIS_MANAGER1.md` | 進捗・判断ログの正本。特に「Phase 19」「Phase 20」節と「5. 判断ログ」「6. 作業ログ」の直近エントリ |
| `z_instructions/SALES_ANALYSIS_PHASE20_REVIEW.md` | Phase20の設計意図・スコープ縮小項目・Codexレビュー結果のまとめ。次の作業がPhase20の続きなら必読 |
| `z_instructions/CONSOLIDATED_01_layout_and_ui.md` | 新規ページ作成前に必須 |
| `z_instructions/DEPLOY_SAKURA.md` | さくら本番作業をする場合 |

（`SALES_ANALYSIS1〜3_PROMPT.md`/`REVIEW2.md`/`REVIEW3.md`/`EXCEL_VALIDATION_REVIEW.md`は
過去の経緯を追いたい場合のみ参照。優先度は低い）

## 5. 特に重要な保留事項

### 5.1 Phase 20で意図的にスコープ外とした項目（次の作業候補、次第で着手してよい）

`SALES_ANALYSIS_PHASE20_REVIEW.md`「5. 意図的にスコープ外とした項目」に詳細あり。要約:

1. APIレベルの`order_channel=all|standard|direct`絞り込みフィルタが無い（常に合算＋内訳表示のみ）
2. 得意先/商品の「新規・取扱終了」パネル（`productYearOverYearComparison`）・同月比較の
   「新規/離脱得意先」「増加額上位/減少額上位」に経路内訳が付いていない（合計値は正しい）
3. 同月比較の「得意先別年次推移」マトリクス表に内訳列が無い
4. 「合計/サンエー印刷経由/独自受注」を明示的に切り替えるUIトグルが無い

### 5.2 サンエー印刷の利用許可付与はユーザー（SuperAdmin）の作業

Claude側・Codex側のどちらも対応不要（ユーザー自身が画面から行うと明言済み）。

### 5.3 Excel出力の他画面拡張は保留

同月比較・左右比較・得意先分析・商品分析へのExcel出力拡張は、経理側の希望フォーマットを
ユーザーが確認してから対応する方針。現状は年次分析・期別分析画面のみ対応。

### 5.4 バックアップ機能（Phase 9）は未着手

ユーザーから明示的な着手指示が出るまで先回りして着手しないこと。

### 5.5 Phase 19・20の本番デプロイ未実施

本番デプロイにはPLAN1.md/MANAGER1.md「Phase 19」節末尾の未対応事項（部署定義の投入、
サンエー印刷ユーザーのcompany_id確認、許可付与）に加えて、通常の`DEPLOY_SAKURA.md`手順＋
`php artisan migrate --force`（Phase19・20分のmigrationを本番へ反映）が必要。
**ユーザーの明示指示が出るまでSSH・本番migrateを行わないこと。**

## 6. Codexへの引継ぎ手順

Codexに売上分析の作業を分散させる場合、以下をそのままCodexへの最初の指示として渡すこと。
`<具体的な依頼内容>`の部分はユーザーが実際に依頼したい作業に書き換える。

```text
売上分析機能の作業を担当してください。
まず AGENTS.md を全文読み、次に z_instructions/SALES_ANALYSIS4_PROMPT.md を全文読んでください。
そこで指定されている必読ファイル（SALES_ANALYSIS_PLAN1.md、SALES_ANALYSIS_MANAGER1.md、
SALES_ANALYSIS_PHASE20_REVIEW.md、CONSOLIDATED_01_layout_and_ui.md）のうち、あなたの作業に
関係する節を読んでから着手してください。

今回の依頼内容: <具体的な依頼内容>

必ず守ること:
- SalesQueryService等の集計メソッドを呼ぶ前は必ずforCompany($companyId)を呼ぶこと
- 受注経路（サンエー印刷経由/独自受注）の区別が必要な処理は、会社コードをハードコードせず
  SalesOrderChannels::supportsChannelsFor($companyId)で判定すること
- 大きな変更は着手前に設計をPLAN1.md/MANAGER1.mdへ追記し、ユーザーに提示して確認を取ること
- 不明点は一度に一つだけ質問すること
- 本番sales DBのレコード内容は閲覧しないこと。docker compose execは必ず--user sailを付けること
- 実装後は必ず `docker compose exec --user sail laravel bash -lc "php artisan test --filter=SalesAnalysis"`
  で全件成功を確認し、Vue/JSを変更した場合は npm run build も実行してから完了報告すること
- 作業完了後、進捗と判断根拠をSALES_ANALYSIS_MANAGER1.mdの該当Phase節・判断ログ・作業ログへ
  必ず記録すること（この文書を読む次の担当者が経緯を追えるようにするため）
- 本番デプロイ・SSHはユーザーの別途明示指示があるまで行わないこと
```

Codexの作業が終わったら、`SALES_ANALYSIS_MANAGER1.md`を読めばClaude Code側で進捗を把握できる
（判断ログ・作業ログのフォーマットは既存のエントリに合わせて追記されている前提）。

## 7. 再開用の短縮プロンプト（Claude Codeで続きをやる場合）

```text
売上分析機能の続きに着手します。
まず z_instructions/SALES_ANALYSIS4_PROMPT.md を全文読み、そこに書かれた必読ファイル
（AGENTS.md、SALES_ANALYSIS_PLAN1.md、SALES_ANALYSIS_MANAGER1.md、
SALES_ANALYSIS_PHASE20_REVIEW.md、CONSOLIDATED_01_layout_and_ui.md）を読んでから、
私からの具体的な依頼内容を待ってください。

Phase 0〜20（サン・ブレーンの受注経路分離含む）はすべて完了・Codexレビュー対応済みです。
確定した未着手タスクは無いので、私が伝える新しい要望から設計を始めてください
（候補は本ファイル5.1節のPhase20スコープ外項目、5.4節のバックアップ、5.5節の本番デプロイ）。

必ず守ること:
- SalesQueryService等の集計メソッドを呼ぶ前は必ずforCompany($companyId)を呼ぶこと
- 受注経路の区別が必要な処理はSalesOrderChannels::supportsChannelsFor($companyId)で判定すること
  （4PROMPT.md 3節参照）
- 大きな変更は着手前に設計をPLAN1.md/MANAGER1.mdへ追記し、私に提示して確認を取ること
- 不明点は一度に一つだけ質問すること
- 本番sales DBのレコード内容は閲覧しないこと。docker compose execは必ず--user sailを付けること
- 実装後は必ずSalesAnalysis配下の全テスト成功を確認してから完了報告すること
```
