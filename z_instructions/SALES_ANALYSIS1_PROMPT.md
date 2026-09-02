# SALES_ANALYSIS1_PROMPT.md — Claude Code 実装・Codexレビュー用プロンプト

## 1. 使い方

この文書には、Claude Codeへ渡す実装プロンプトと、実装完了後にCodexへ渡すレビュープロンプトを収録する。

- Claude Codeには「2. Claude Code用」を渡す。
- Claude Codeが全フェーズを一度に進めず、`SALES_ANALYSIS_MANAGER1.md`を更新しながら段階実装する。
- 実装後、Codexには「3. Codex用」を渡す。
- 本番売上DBのレコードは、どちらのAIにも閲覧させない。

---

## 2. Claude Code用プロンプト

以下をそのままClaude Codeへ渡してください。

```text
SunBWorkに売上分析機能を実装します。

最初に必ず次のファイルを全文読んでください。

- AGENTS.md
- z_instructions/SALES_ANALYSIS_PLAN1.md
- z_instructions/SALES_ANALYSIS_MANAGER1.md
- z_instructions/SALES_ANALYSIS1_PROMPT.md
- z_instructions/CONSOLIDATED_01_layout_and_ui.md
- z_instructions/CONSOLIDATED_02_security_and_sessions.md
- z_instructions/CONSOLIDATED_09_domain_rules.md
- z_instructions/DEPLOY_SAKURA.md（本番作業をする段階だけ）

実装の正本はSALES_ANALYSIS_PLAN1.md、進捗の正本はSALES_ANALYSIS_MANAGER1.mdです。

## 最重要の機密規則

- 本番売上データは専用のsales DB connectionへ分離します。
- 本番sales DBのレコードをSSH、SQL、Tinker、DBクライアント、dump、ログ、臨時スクリプトで閲覧してはいけません。
- 本番売上データをローカルへコピーしてはいけません。
- 開発とテストには架空データだけを使用してください。`z_instructions/sanbrain_meisai_sample.xlsx`は、ユーザーが匿名化済みと確認した場合だけ値をfixtureへ利用できます。未確認なら構造確認だけに留め、名称・金額等を転載しないでください。
- sales DB認証情報やbackup内容を出力・コミットしないでください。
- AGENTS.mdへこの規則を追記してください。CLAUDE.mdは編集しないでください。

## 機能要約

- 既存Laravel 12 / Vue 3 / Inertia内へ実装
- 通常DBとは別のsales DBへ売上明細・取込版・会社統合設定・監査を保存
- 初期対象部署は「企画」のみ。将来「制作」「オンデマンド」を追加可能にする
- xlsxは年次一括1シートと月次ファイルの両方に対応
- 月次はExcelタイトル年月、年次はSB下版日から売上月を判定
- 同一受注Noは複数明細。M列の明細金額合計とN列の受注金額合計を検証
- 月間売上は受注金額の合計、税抜
- 同月再取込は新版を自動採用し、旧版を履歴保持
- 年次と月次が混在してもsales_active_monthsで月ごとの有効版を一意にする
- 分析優先順: 前月、前年同月、5年推移、得意先、分類、項目、品名
- 最新月初期表示、得意先上位10、会社統合は既定off
- 得意先統合は候補を人が確定。自動統合は禁止
- 初期版はAIなし
- 画面フィルタを反映したxlsx出力
- 元xlsxは非公開一時保存し、処理後削除
- sales DB全体を取込後と日次に暗号化backup。日次30日、年末長期保持

## 権限

- SuperAdminは常時利用可能で、Admin/Clerkの個人別利用許可を設定
- 候補はAdminとClerkだけ。Leaderを含めない
- 許可済みAdmin/Clerkは閲覧・取込・会社統合・出力の全機能を利用可
- ナビ非表示だけでなく、全ルートを専用middlewareでサーバー側保護

## 作業方法

1. 最初にgit statusと関連実装を確認し、ユーザーや他エージェントの変更を保護してください。
2. SALES_ANALYSIS_MANAGER1.mdの最初の未完了Phaseから始めてください。
3. Phase開始時に対象タスクを🔄へ更新してください。
4. 実装前に、そのPhaseの方針・対象ファイル・検証方法を短くユーザーへ説明してください。
5. 未決事項や要件の曖昧さがあれば、一度に一つだけ質問してください。
6. 各Phaseで自動testを追加し、完了時に実行結果をMANAGERへ記録してください。
7. Phase完了後、タスクとPhaseを✅にし、作業ログと判断ログを更新してください。
8. 大きな設計変更はPLANも同時更新し、理由を判断ログへ残してください。
9. routes/web.php変更後はZiggyを再生成してください。
10. Vue/JS変更後はAGENTS.mdとDEPLOY_SAKURA.mdのVITE_APP_BASE_PATH規則を守ってbuildしてください。
11. 本番SSHコマンドは実行前に正確なコマンドを提示し、ユーザー確認を得てください。
12. migrate/db:seedの本番ワンライナーには必ず--forceを付けてください。

## 実装上の禁止事項

- 本番sales DBを動作確認のために読むこと
- 通常DBへ売上明細を保存すること
- client_nameを推測だけで統合すること
- AIに売上計算をさせること
- 全明細をVueへ渡してブラウザ集計すること
- created_at最大だけで最新版を決めること
- URLのハードコード、api.phpへのSPA route追加
- page側のmain/py-12/max-w-7xl重複、ToastUnified重複
- 元xlsx、export、backupを公開領域へ恒久保存すること
- 得意先名・品名・備考・金額明細・認証情報を通常ログへ出すこと
- CLAUDE.mdを編集すること

## 現在の作業

まずSALES_ANALYSIS_MANAGER1.mdのPhase 0を開始してください。
Phase 0の未決事項は一問ずつユーザーへ確認し、確認なしに仮定して実装を進めないでください。最初にサンプルが匿名化済みか確認してください。
```

---

## 3. Codexレビュー用プロンプト

Claude Codeの実装完了後、以下をCodexへ渡してください。

```text
Claude CodeがSunBWorkの売上分析機能を実装しました。コードレビューと検証をしてください。

最初に必ず次を全文読んでください。

- AGENTS.md
- z_instructions/SALES_ANALYSIS_PLAN1.md
- z_instructions/SALES_ANALYSIS_MANAGER1.md
- z_instructions/SALES_ANALYSIS1_PROMPT.md
- z_instructions/CONSOLIDATED_01_layout_and_ui.md
- z_instructions/CONSOLIDATED_02_security_and_sessions.md
- z_instructions/CONSOLIDATED_09_domain_rules.md

## 絶対条件

- 本番sales DBのレコードをSSH、SQL、Tinker、DBクライアント、dump、ログ、臨時スクリプトで閲覧しないでください。
- 本番売上データをローカルへコピーしないでください。
- sales DB認証情報やbackup内容を出力しないでください。
- 検証はコード、migration、架空fixture、自動testで行ってください。sanbrain_meisai_sample.xlsxの値は、ユーザーが匿名化済みと確認した場合だけ利用してください。
- レビュー依頼なので、最初はコードを変更せず、問題を重大度順に報告してください。ユーザーが修正も依頼した場合だけ修正してください。

## レビュー観点

1. SALES_ANALYSIS_PLAN1.mdとの仕様一致
2. 通常DBとsales DBの接続分離
3. sales Model/migration/queryが必ずsales connectionを使うこと
4. 権限がサーバー側middleware/policyで強制され、Leader等が直URLで入れないこと
5. SuperAdmin許可設定の候補がAdmin/Clerkだけであること
6. xlsxの構造、部署、年月、必須値、負数、日付、M/N金額整合性検証
7. 年次一括と月次ファイルの混在
8. sales_active_monthsによる月別最新版とatomicな切替
9. 再取込失敗時に旧版が維持されること
10. 月間売上、前月、前年同月、年度累計、5年推移のSQL正確性
11. 未取込月と0円を混同しないこと、0分母処理
12. 得意先統合が既定offで、人が確定したグループだけ合算すること
13. 品名を含むフィルタとxlsx出力の一致
14. formula injection、zip/xlsx、temporary file、ログ、exportのセキュリティ
15. 元xlsxが処理後に削除されること
16. backupの暗号化、秘密値非露出、30日prune、年末保持、架空DB復元test
17. AppLayout、Ziggy、route()、/members、responsive規則
18. N+1、全明細のブラウザ送信、index不足等の性能問題
19. testsが重要な失敗系・権限・transactionをカバーしていること
20. ChangelogSeeder、CONSOLIDATED文書、MANAGERが実装と一致すること

## 出力形式

- 最初に重大度順の指摘を、ファイルと行番号付きで示してください。
- 各指摘に、影響、再現条件、修正方針を簡潔に書いてください。
- 次に未確認事項・テスト不足を示してください。
- 最後に、実行したtest/buildと結果を示してください。
- 問題がなければ「重大な問題は見つからなかった」と明記し、残存リスクを示してください。
- SALES_ANALYSIS_MANAGER1.mdのReview R1欄へ結果を反映してください。ただしコード修正はユーザーの依頼があるまで行わないでください。
```

---

## 4. 再開用短縮プロンプト

途中でClaude Codeのセッションを再開する場合:

```text
売上分析機能の実装を再開してください。
AGENTS.md、SALES_ANALYSIS_PLAN1.md、SALES_ANALYSIS_MANAGER1.md、SALES_ANALYSIS1_PROMPT.mdを全文読み、MANAGERで🔄または最初の⬜タスクから続けてください。
本番sales DBのレコードは絶対に閲覧せず、架空データだけで検証してください。不明点は一度に一つだけ質問してください。
```
