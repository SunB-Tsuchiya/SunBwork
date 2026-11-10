# 従来の依頼取り込み完了通知 修正設計1

更新: 2026-11-10 JST。状態: 利用者yesで具体設計承認。実装と再現修正済み、最新版統合後の最終検証中。
親REQUEST_JOB_COMPLETION_PLAN1.md/MANAGER1.md。新入力/統合BOXの一般公開とは別の、既存入力・既存完了経路の不具合修正。

## 確認した事実

承認済み読取スクリプトで本番b4f585ba6を確認。project406の9684は自己割当でsupersedes_assignment_id=9683。9683は別人依頼の元割当。両方completed=true/status_id=3、元依頼is_registered=true。担当者roleはUser、依頼主は案件リーダーを兼任、副リーダーなし。進行/管理セル関連なし。関連通知は元9683のnew_job1件のみ、完了通知0件。READ ONLY transaction・rollbackで終了0、更新/再送なし。個人名・本文・ファイル・売上は取得せず、実データをローカルfixturesへ複製しない。

EventController.completeは取り込み後の自己割当をnotifyCompletedへ渡すため早期returnし、その後にsupersedes元を完了にしても元依頼へ通知しない。DBの同時更新状態とこの経路は整合する。実際のHTTP経路をログ等で特定したわけではない。MyProjectJobController/JobBoxControllerの旧完了にも同じ自己割当の通知抑止があり、専用経路を揃える必要がある。SuperAdmin限定のWorkJobMutation接続は一般Userの旧経路には入らないため、それだけでは今回を直せない。

## 修正対象と境界

- 既存の認証・所有者policyを通った、担当本人による『通常依頼を取り込んだ旧形式MyJob』とその旧形式祖先の完了。一般Userの既存カレンダー/旧詳細/JobBoxにも適用する。
- 新フォーム/統合BOX/分析のrole guard・メニューは一切広げない。新式の一般公開ではない。
- 単独自己割当の自己完了通知は引き続きなし。通常直接依頼の既存処理、校正専用処理、代理完了の権限/通知先再設計は今回の対象へ自動追加しない。
- source_assignment_idは続き、supersedes_assignment_idは元依頼として明確に区別。同名検索・同案件全割当完了は禁止。

## 共通処理案

新しいLegacyReceivedJobCompletion相当の入口を作り、試用UI認可から独立した既存所有者の通常依頼完了だけを受ける。UnifiedMyJobBox/WorkJobReaderの公開制限を解除して使い回さない。

1. 担当本人/自己割当/会社部署/案件会社、明示supersedes元の同じ担当者・同案件・Coordinator割当・単一取り込みを検証する。旧NULL所属は既存互換の本人宛同案件だけ許容。旧形式祖先を上限/循環検査つきで認可し、順序を定めてロック・再検証する。
2. 選択+祖先だけを対象に、WorkJobReceivedCompletionの検証、WorkJobLegacyCompletionの既存状態/予定/進行/元依頼通知とoutboxを再利用できる境界を整理する。型付き新旧混在・校正は既存の専用経路を維持し、対応しない親を黙って切り落とさない。対象判定と専用経路の優先を合成で固定する。
3. 元依頼の未完了→完了の遷移時だけ、元依頼IDを通知の参照にする。依頼主completed、他の案件リーダーcompleted_info（進行表由来は既存progress種別を維持）。操作本人を除外し、依頼主兼リーダーは1件。元依頼別×宛先別の一度を保証し、再操作では送らない。
4. 状態・元依頼・イベント接頭辞・関連Progress/Workflowセル・JobAssignmentMessageの既存同期・DB通知・outboxをdefault DBの同じtransactionで確定する。通常通知失敗を握りつぶさずrollback。銀本は既存outboxでcommit後に同期し、未反映時の再試行/削除保護を維持する。
5. カレンダー完了の既存のトースト/redirectを保持する。担当者本人の場合に内部Messageを作らずトーストとする既存抑制も保持し、今回JobNotificationを直すために別系統のメッセージ・メールを増やさない。放送はcommit後、再操作で不要に繰り返さない。
6. 3つの既存完了入口を共通処理へ接続したら、そのケースでは旧自己割当向け通知や元依頼更新を重ねて実行しない。SuperAdminの既存typed混在完了・校正通知と二重にならないことを検証する。

schema追加は予定しない。すでに両方が完了済みの9683/9684を修正後に再完了しても通知を再送しない。今回の未送信分を補うこと、過去一括補正/通知再送は別承認なしに行わない。

## 影響予定

- 新しい旧形式通常受信完了サービス。
- EventController.complete、User/MyProjectJobController.completeAssignment、ProjectJobs/JobBoxController.completeAssignment。
- 必要なWorkJobLegacyCompletion/WorkJobReceivedCompletion/通知・同期サービスの境界調整。試用guardの解除は禁止。
- 合成Integration/対象Unit、必要なVue退行（UI仕様変更は予定しない）。
- 専用ChangelogSeederとChangelogSeeder委譲、CONSOLIDATED_05と現管理資料。versionは30文字以内を確認。

## 検証

実DBではなく明示SQLite :memory:/network none。User担当・別依頼主・依頼主兼リーダーを合成し、元依頼→自己割当→各完了入口の通知0問題を再現して修正を確認する。3入口をまたぐ再操作で通知1件、別リーダー/NULL sender/自己完了/普通の直接依頼/校正・進行・銀本/旧新混在/祖先と兄弟・子/権限/不正所属/不一致・欠落・複数supersedes/通知失敗時全rollback/JobAssignmentMessage同期/銀本commit後再試行/トーストとMessage非増加を検証する。代理完了の挙動を勝手に拡張しない。
既存統合64件790断言と実Vueの影響範囲を退行確認し、PHP構文/route・JS変更があればlint・members build/旧資産保持を検証。Tests\TestCase/RefreshDatabase/migrate:freshを使用しない。

配備前に最新本番SHA/他Claude作業を照合し固定差分を保存。正確なSSH承認後に専用Seederと必要なcache clearのみで反映。一般公開・実通知の試験送信なし。配備成功と利用者の実受信成功を分ける。修正後の実受信確認は業務上完了してよい別の代表例がある場合にのみ依頼し、9683を勝手に再通知しない。

## 段階

A: 読取診断済み、本具体設計を提示して修正承認。B: 再現テスト/実装/検証。C: 固定差分の限定配備を別途SSH承認。D: 他人からの実完了通知1件と重複有無の確認。次の一手はAの承認。
