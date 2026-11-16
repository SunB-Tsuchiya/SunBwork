# 個人月カレンダー・TODO 再開

AGENTS.md/JOB_EVALUATION_CONTROL1、PERSONAL_MONTH_PLAN1/MANAGER1を読む。branch feature/personal-month-calendar-research-20261116、worktree /tmp/sunbwork-myjob-time-breakdown-header-20261116、基準ce1fe2756。共有mainのClaude作業を保護。本番直近確認a16fd1e9a。
確定回答: 月はジョブ初期非表示・切替、新しい個人TODO一覧と連動。週日は今のジョブ入力維持。Clerkの色/期間/完了を参考とするが会社共有DBを個人閲覧に開かない。
提案: 個人専用calendar itemsを月+TODO共用、種別event/deadline/todo、期限未定は一覧、時間評価へ非混入、専用APIの所有者/会社境界、再送/競合対策。第1版は月+TODO、通知/繰返し/MyJob連携は後段、INFO SuperAdmin確認から、という範囲は承認待ち。
アプリ/DB変更なし。次は具体設計の利用者確認後に実装。一般公開は別承認。SSHは正確なコマンドを提示して確認、過去成功画面を反復しない。

2026-11-16再開・実装中: 利用者が第1版の範囲（本人専用月+新TODO、INFO SuperAdmin確認、一般公開別承認、通知/繰返し/MyJob連携後段）を承認。現在の本番経路はCalendarController→Pages/Calendar.vue→Components/Calendar/UserCalendar.vue→独自Schedule/MonthView.vueであると実装前調査で判明。旧Calendar.vue/FullCalendarを編集する候補を撤回し、この現行経路へ限定して設計を具体化。専用table/API/Service、版・再送、月/一覧/モーダルと月だけのジョブ切替を実装中。週日イベント配列は維持。実装/コード検証/配備/画面確認は別状態。次は合成境界テスト。

2026-11-16実装・合成確認: PHP142件1499断言成功（既存mixed-series警告1件）。新規6ケースで本人/他人/別会社/INFO以外/一般User拒否、再送/409/削除410、無期限TODOと過去期限の月非依存一覧/完了/取消/ページング、JST締切/期間/検証を確認。関連旧新MyJob/時間配分も失敗なし。実Vueで月のjob切替/保存二重送信・CSRF/通信不明再送制限/409入力保持/期間表示・選択/旧月のdrag非介入/一覧と月同一ID/古い取得レスポンス排除/文字列escapeを確認。5ルートを一時生成しZiggyの追加定義だけ反映、他不変。ChangelogSeederコード追加、Seeder未実行。次は/members最終buildと本番版の照合。配備/画面確認は未実施。

2026-11-16配備準備完了: 最終/members build成功、manifest全参照欠落0・本番baseを確認。ChangelogSeeder構文成功、Seederは実行しない。コードはSuperAdminかつSunBrain INFO実所属・会社context一致だけ有効で、一般User APIは403。専用migration以外を適用せず、既存データのコピー/変換はしない。次は直近確認本番a16fd1e9aを固定元とする差分検査付き手順への承認。版や作業中ファイルが違えば変更前に停止。今回の本番画面確認はまだ未実施。
