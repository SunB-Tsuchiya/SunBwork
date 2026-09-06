# SALES_ANALYSIS1_PROMPT.md — 引継ぎ・新セッション再開用プロンプト

最終更新: 2026-09-04

## 1. これは何か

売上分析機能（企画・制作・オンデマンド3部署のExcel取込・分析）の開発状況の引継ぎ文書。
新しいセッションでこの機能の続きに着手するときは、まずこのファイルを読み、次に
「4. 必読ファイル」を読んでから作業を始める。

設計の正本は`SALES_ANALYSIS_PLAN1.md`、進捗・判断ログの正本は`SALES_ANALYSIS_MANAGER1.md`。
このファイルはそれらの要約＋再開用の入口でしかない。**詳細な理由や判断根拠はMANAGER1.mdの
判断ログ・作業ログを見ること（このファイルには要約以上のことは書かない）。**

## 2. 現在の状態（ざっくり）

- Phase 0〜8: **完了**（DB分離、権限、Excel取込・検証、集計、データ登録状況、年次分析、
  同月比較、左右比較、得意先統合（設定画面＋得意先分析画面）、Excel出力（年次分析画面のみ））
- Phase 9（バックアップ）: **保留**。ユーザーが売上分析機能をSunBWork本体に残すか、
  別のLaravelアプリへ切り出すか検討中のため、さくらへのSSH調査を含め着手していない
  （詳細は「5. 特に重要な保留事項」参照）
- Phase 10（総合検証・文書・リリース準備）: 未着手。10-1〜10-4（テスト総まとめ・build・
  通常機能回帰・セキュリティチェックリスト）は本体に残すか切り出すかに関わらず着手可能
- **Codexレビューは3回実施済み**（1回目: Excel検証設計、2回目: 実機検証まとめ＋新画面設計、
  3回目: Phase 6C〜8実装＋実機バグ2件の設計妥当性確認）。**3回目レビューで大規模な追加改修案が
  提示され、まだ未対応（次にやること参照）**
- テスト: `tests/Feature/SalesAnalysis`+`tests/Unit/SalesAnalysis`配下203件・全成功
  （実行は必ず`--user sail`で。3節参照）。プロジェクト全体317件成功
- 実機での動作確認はユーザーが実施し、都度フィードバックを受けて修正するサイクルで進めてきた

## 3. 作業を始める前に必ず守ること

- **`docker compose exec`は必ず`--user sail`を付ける。** 既定のroot実行だとstorage配下の
  所有権事故や、複数DB接続を跨ぐ`migrate:fresh`等での事故を招く（2026-09-03に実際に発生）。
  詳細はAGENTS.md「Destructive Database Operations」節。
- 本番sales DBのレコードは、SSH・SQL・Tinker・DBクライアント・dump・ログいずれでも閲覧しない。
  ローカル開発・テストは架空データのみ使用する。
- **`php artisan tinker`で書き込み系操作（`updateOrCreate`等）を安易に実行しない。**
  2026-09-04に、動作確認のため`tinker`で作成したダミーデータが、既存のローカル開発DB
  （sunbwork_sales、ユーザーが実際に取込済みのデータ）のactive pointerを誤って上書きする
  事故が発生した（即座に発見・復旧済み、本番には影響なし）。動作確認は`RefreshDatabase`が
  効くPHPUnitテスト（`sunbwork_sales_testing`）内で行うか、既存データと衝突しない架空の
  年月・部署を使うこと。
- **破壊的DB操作（`dropAllTables`・`migrate:fresh`・`DROP TABLE`等）を含むBashコマンドは、
  このリポジトリのローカルフック（`.claude/hooks/block_destructive_db.py`、
  `.claude/settings.local.json`のPreToolUse）で機械的にブロックされる。** これはgit管理外の
  ローカル専用設定なので、他のマシン・別プロジェクトには自動的に付いてこない。
- 大きな設計変更や新画面着手前は、方針をユーザーに示して確認を取ってから実装する。
  不明点は一度に一つだけ質問する（CLAUDE.md記載の最重要ルール）。
- Vue/JS変更後は`npm run build`、`routes/web.php`変更後はZiggy再生成
  （`php artisan ziggy:generate resources/js/ziggy.js`）を忘れない。
- **axiosでbooleanをGETクエリへ渡すときは必ず`? 1 : 0`のように数値化すること。**
  Laravelの`'boolean'`バリデーションルールは文字列`"true"`/`"false"`を受け付けず422になる
  （2026-09-04に同月比較・左右比較で実際に発生。詳細はMANAGER1.md該当ログ、または
  Claude Codeの永続メモリ`feedback_axios_boolean_query_params.md`）。

## 4. 必読ファイル（着手前に全文）

- `AGENTS.md`（機密規則・破壊的DB操作の禁止事項を含む）
- `z_instructions/SALES_ANALYSIS_PLAN1.md`（設計正本。Phase 6B〜8詳細設計・ワイヤーフレーム・
  JSON例あり）
- `z_instructions/SALES_ANALYSIS_MANAGER1.md`（進捗・判断ログの正本。全経緯はここ）
- **`z_instructions/SALES_ANALYSIS_REVIEW3.md`（★最新・最重要。3回目Codexレビューの全文。
  11〜17章に指摘一覧・設計判断の妥当性確認・大規模UI/API改修案・実装依頼順が入っている）**
- `z_instructions/SALES_ANALYSIS_REVIEW2.md`（2回目レビュー。9〜13章の新画面設計の経緯として参照可）
- `z_instructions/CONSOLIDATED_01_layout_and_ui.md`（新規ページ作成前に必須）
- 本番作業をする段階になったら`z_instructions/DEPLOY_SAKURA.md`（ただし5節の保留事項を先に確認）

（`SALES_ANALYSIS_EXCEL_VALIDATION_REVIEW.md`は1回目レビューの記録。優先度は低い）

## 5. 特に重要な保留事項

### 5.1 アーキテクチャ未決定（U-4）

ユーザーは、売上分析機能をこのままSunBWork本体に残すか、別のLaravelアプリとして組み上げ直すかを
まだ決めていない（2026-09-04申告）。現状は`sales`専用DB接続で通常DBとは分離済みだが、
認証・ロール・`AppLayout`・ナビゲーション（`EnsureSalesAnalysisAccess`ミドルウェア、
`ResolvesSalesAnalysisRoutePrefix`、SunBWorkのUser権限モデル）とは密結合している。

**この判断が付くまで、さくら本番へのデプロイ・SSHでの本番調査には着手しないこと。**
ローカルでのバックエンド改修（次節のREVIEW3対応等）は本体非依存なので継続してよい。

### 5.2 Excel出力の他画面拡張は保留

同月比較・左右比較・得意先分析へのExcel出力拡張は、経理側の希望フォーマット（会社規定の
書類形式等）をユーザーが確認してから対応する方針。現状は年次分析画面のみ対応。

## 6. 次にやること（優先順）

**`SALES_ANALYSIS_REVIEW3.md`の17章「Claude Codeへの実装依頼順」に従うこと。** 以下は要約。

1. 11.2節のHigh 3件を修正し、16.1節の対応回帰テストを追加する
   - 得意先詳細（`ClientAnalysisController::detail()`/`SalesQueryService::clientDetail()`）が
     開始月・終了月を無視し年単位で集計してしまう問題
   - 年次分析`annualSummary()`の`months_registered`が「最後に登録された月」であり、
     欠落月があっても連続登録済みに見えてしまう問題
   - 「全部署合計」で一部部署の月だけ未登録でも、完全登録に見えてしまう問題
2. `SALES_ANALYSIS_PLAN1.md`と`SALES_ANALYSIS_MANAGER1.md`へ、REVIEW3の改修内容を
   次Phaseとして追記し、実装範囲を確定する
3. 月次・年次・同月比較・得意先分析のワイヤーフレームを文書化し、実装前にユーザー確認を取る
   （細かな色・余白・部品形状はClaude Codeの裁量でよいとREVIEW3に明記あり）
4. 共通期間ナビゲーター、Top10/20+全件詳細ドロワーの仕組みを先に作る（14章Priority A）
5. 月次分析をPriority A完成見本として改修し、同じ共通部品・API形状を他画面へ展開する
6. Priority B（14章）を追加。Priority Cは実機利用後に必要性を判断する
7. 最大規模の合成データでAPI応答時間・ブラウザ描画・Excelメモリを確認する
8. 売上分析テスト・プロジェクト全体テスト・`npm run build`を実行する

Medium 9件・Low 1件（REVIEW3 11.2節）、N列マイナス許容時の負数表示仕様（11.3節）、
得意先統合候補の2区分化（11.4節、既存グループへの追加候補を出す）も、この改修の中で対応する。

実装中に画面上の二者択一が必要になった場合のみ、ユーザーへ一度に一問ずつ確認すること
（REVIEW3 17章に明記済みの指示）。

## 7. 再開用の短縮プロンプト

新しいセッションでそのまま貼り付けて使える形。

```text
売上分析機能の続きに着手します。
まず z_instructions/SALES_ANALYSIS1_PROMPT.md を全文読み、そこに書かれた必読ファイル
（AGENTS.md、SALES_ANALYSIS_PLAN1.md、SALES_ANALYSIS_MANAGER1.md、
SALES_ANALYSIS_REVIEW3.md、CONSOLIDATED_01_layout_and_ui.md）を読んでから、
SALES_ANALYSIS_REVIEW3.md 17章「Claude Codeへの実装依頼順」の1番目
（High 3件の修正・回帰テスト追加）から着手してください。

着手前に、SALES_ANALYSIS_PLAN1.md / SALES_ANALYSIS_MANAGER1.md へ本改修を次Phaseとして
追記し、ワイヤーフレームをユーザーへ提示して確認を取ってからコードに着手すること。
不明点は一度に一つだけ質問してください。

さくら本番へのデプロイ・SSHでの本番調査は、ユーザーがアーキテクチャ（SunBWork本体に残すか
別Laravelアプリへ切り出すか）を決めるまで行わないこと（1PROMPT.md 5.1節参照）。
本番sales DBのレコードは閲覧しない。docker compose execは必ず--user sailを付けること。
tinkerでの書き込み系動作確認は既存データと衝突しない範囲でのみ行うこと。
```
