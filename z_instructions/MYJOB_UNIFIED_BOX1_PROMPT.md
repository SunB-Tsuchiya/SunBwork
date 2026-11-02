# マイジョブ入力・BOX統合 再開用

更新: 2026-11-02 JST。実装とコード検証、配備、利用者画面確認を分ける。一般公開は未承認。

## 最初に読む

AGENTS.md → JOB_EVALUATION_CONTROL1.md → MYJOB_UNIFIED_BOX_PLAN1.md / MANAGER1.md。親計画はUSER_WORK_HUB_*、既確認画面の履歴もそこを参照。共有Claude変更を触らない。

## 現在地

- 作業: feature/unified-box-preview-20261102、/tmp/sunbwork-unified-box-20261102。旧/tmpは消失したため保存ce396f6bdから復元した。現在のGit版を必ず確認する。
- 本番: 7f2868a06e6f4b55ce6e6c406c5414886d2df4ab。初回読取BOXは配備済み、利用者「成功」で新ボタン/一覧を開く/全期間の作業名検索・クリアの3点を確認済み。これらの再試験不要。
- 最新の利用者yes: SA試用BOXから「マイジョブ作成」で既存簡易入力を開き、保存した同じ作業をBOX/カレンダーへ表示。旧詳細/依頼/添付経路を保持する段階を承認。
- 作成接続: コード検証済み、限定配備/作成の利用者画面確認は未実施。次は固定差分のSSHコマンドを準備し承認を得る。ビルド/manifestの最終結果はMANAGERを参照。

## 実装と境界

UnifiedMyJobBoxのGETと新POST user.myjobbox.unified_storeは実所属INFO・非一般会社のSuperAdmin本人だけ。別会社コンテキスト拒否。分類管理権限でCoordinator/Leaderへ広げない。一般Userの通常BOX/props/フォーム/カレンダーは維持。

新POSTはguard後DepartmentWorkController::storeへ委譲し、既存検証と本人固定保存を再利用。新DB/複製なし。QuickWorkEntryModalのentryPoint=myjobだけ新規保存先/タイトル/説明を変更。通常カレンダーのPOST/PUT、日報/評価計算は変更しない。JST今日/時刻空欄を初期値にし、成功時だけ閉じる。保存日+IDから作業月・検索なし・完了除外なしの本人行ページへ移動し「今回登録」を表示。created_entryは本人一覧で一致するキーだけ使う。失敗時は内容を保持。

Q1未予定保持、Q2従来1割当1行複数日、Q3簡易otherを含めるは確定済み。通常会議/外出とsource_event_id付き補足は独立行にしない。自己割当は本人の旧未予定も残す。予定ある受信/proof/表作業も本人だけ。supersedesの一意な同案件本人置換だけ対応し、続きsource_assignment_id/校正coordinator_assignment_idを混同しない。同名結合禁止。時刻はCalculatesEventTimeのJST/校正UTC、予定月はJST実作業月、未予定は締切/登録月。

## 検証と残り

合成関連29件311断言+新Integration3件44断言、計32件355断言成功。DBは明示SQLiteメモリのみ、Tests/TestCase/RefreshDatabaseは使わない。実Vue script/SSRのtests/js/unifiedMyJobInput.test.cjs成功、対象ESLint/PHP6構文/実Ziggy生成成功。membersビルド・既存削除資産保持・manifestの最終証拠はMANAGERで確認する。

本番SSH前に正確なコマンドを示し利用者承認。配備は固定元7f2868a06と今回固定版、許可ソース一覧一致、削除/依存/migration変更なし、ff-only。独立UnifiedMyJobBoxChangelogSeederだけ--database=mysql --force。既存未追跡5件保持。sales接続禁止。他Claude作業を取り込むpull/main同期をしない。

未確認: 作成画面/実保存/BOXとカレンダーの往復、未予定/複数日の実例、別アカウント実画面、上長レビュー。一般公開・通常入口差替え・カレンダー入口再編・編集削除完了の統合は未承認。既確認の分類/星/機密/休止は繰り返さない。
