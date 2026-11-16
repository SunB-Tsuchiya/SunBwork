# 個人月カレンダー・TODO 再開

AGENTS.md/JOB_EVALUATION_CONTROL1、PERSONAL_MONTH_PLAN1/MANAGER1を読む。branch feature/personal-month-calendar-research-20261116、worktree /tmp/sunbwork-myjob-time-breakdown-header-20261116、基準ce1fe2756。共有mainのClaude作業を保護。本番直近確認a16fd1e9a。
確定回答: 月はジョブ初期非表示・切替、新しい個人TODO一覧と連動。週日は今のジョブ入力維持。Clerkの色/期間/完了を参考とするが会社共有DBを個人閲覧に開かない。
提案: 個人専用calendar itemsを月+TODO共用、種別event/deadline/todo、期限未定は一覧、時間評価へ非混入、専用APIの所有者/会社境界、再送/競合対策。第1版は月+TODO、通知/繰返し/MyJob連携は後段、INFO SuperAdmin確認から、という範囲は承認待ち。
アプリ/DB変更なし。次は具体設計の利用者確認後に実装。一般公開は別承認。SSHは正確なコマンドを提示して確認、過去成功画面を反復しない。

2026-11-16再開・実装中: 利用者が第1版の範囲（本人専用月+新TODO、INFO SuperAdmin確認、一般公開別承認、通知/繰返し/MyJob連携後段）を承認。現在の本番経路はCalendarController→Pages/Calendar.vue→Components/Calendar/UserCalendar.vue→独自Schedule/MonthView.vueであると実装前調査で判明。旧Calendar.vue/FullCalendarを編集する候補を撤回し、この現行経路へ限定して設計を具体化。専用table/API/Service、版・再送、月/一覧/モーダルと月だけのジョブ切替を実装中。週日イベント配列は維持。実装/コード検証/配備/画面確認は別状態。次は合成境界テスト。

2026-11-16実装・合成確認: PHP142件1499断言成功（既存mixed-series警告1件）。新規6ケースで本人/他人/別会社/INFO以外/一般User拒否、再送/409/削除410、無期限TODOと過去期限の月非依存一覧/完了/取消/ページング、JST締切/期間/検証を確認。関連旧新MyJob/時間配分も失敗なし。実Vueで月のjob切替/保存二重送信・CSRF/通信不明再送制限/409入力保持/期間表示・選択/旧月のdrag非介入/一覧と月同一ID/古い取得レスポンス排除/文字列escapeを確認。5ルートを一時生成しZiggyの追加定義だけ反映、他不変。ChangelogSeederコード追加、Seeder未実行。次は/members最終buildと本番版の照合。配備/画面確認は未実施。

2026-11-16配備準備完了: 最終/members build成功、manifest全参照欠落0・本番baseを確認。ChangelogSeeder構文成功、Seederは実行しない。コードはSuperAdminかつSunBrain INFO実所属・会社context一致だけ有効で、一般User APIは403。専用migration以外を適用せず、既存データのコピー/変換はしない。次は直近確認本番a16fd1e9aを固定元とする差分検査付き手順への承認。版や作業中ファイルが違えば変更前に停止。今回の本番画面確認はまだ未実施。

2026-11-17再開時に配備記録を復元: 前日の承認済み固定手順SHA256 ce12d2df0159c172642ad71c28d16f75fc9611183d424a50ab92b56b78a1ee96の会話内実行結果で、本番a16fd1e9a→96070007d・exit0を確認。今回migration 2026_11_16_180001のみ70.89msで成功、API5件、manifest495/参照526/欠落0・/members、追跡変更なし。一般公開/Seeder実行/既存データ移行なし。配備後の資料保存コマンドが中断され、保存branchとorigin双方96070007dで配備記録未保存だった。一時worktreeも消失していたため、96070007dからfeature/personal-month-resume-20261117を隔離復元。共有mainのClaude変更には触れず、今回再配備/SSH/DB読取/成功済み画面の再試験はしない。実装・コード検証・限定配備は完了、利用者画面はまだ未確認。次はINFO SuperAdminの①月/ジョブ切替と週日維持、②無期限TODO保存→期日設定→月と一覧の反映→完了、を確認する。通知/繰返し/MyJob連携と一般公開は後続判断。
