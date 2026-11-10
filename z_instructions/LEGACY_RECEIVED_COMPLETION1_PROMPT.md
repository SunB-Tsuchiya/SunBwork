# 従来の依頼取り込み完了通知 再開1

> 2026-11-10最新優先: 利用者yesで既存一般User完了通知の修正を承認。ac363の別作業を保持した統合版で実装/Integration81件932断言/実Vue/ナビ/PHP11構文が成功。資産・JS・route等はac363から差分0、manifest481/512資産。次は固定PHP差分のSSH承認。配備・実通知確認は未実施。新式一般公開と過去9683通知再送は行わない。LEGACY_RECEIVED_COMPLETION_MANAGER1末尾参照。

更新: 2026-11-10 JST。
AGENTS.md→JOB_EVALUATION_CONTROL1.md→LEGACY_RECEIVED_COMPLETION_PLAN1.md/MANAGER1.md→親REQUEST_JOB_COMPLETION資料を読む。

現在は修正の具体設計を作成し承認待ち、コード変更/再現試験/配備なし。次の一手はPLANの既存一般User完了経路の通知修正を承認してもらう。
承認済みREAD ONLY診断でproject406/元9683/自己割当9684は両方完了、担当User・依頼主兼案件リーダー、完了通知0/new_job1件。既存EventController.completeが自己割当で通知判定→抑止、その後に元依頼を完了にする構造と一致。HTTP経路自体は未確定。
新式一般公開はしない。既存owner policyの旧カレンダー/旧詳細/JobBoxだけを共通処理へつなぎ、元依頼の遷移で宛先ごと1回通知する案。試用guard解除は禁止。自己完了抑止、校正/進行/銀本/既存Messageを増やさない担当本人Toast、元メッセージ状態同期を保護。全詳細はPLAN。
今回の過去未送信を勝手に送らない。既完了の再操作で送る修正もしない。実DB試験なし・sales禁止・共有Claude変更未編集。
branch feature/myjob-request-checks-20261110、tree /tmp/sunbwork-request-checks-20261110。最後の本番証拠b4f585ba6。/tmp消失時は保存版から復元し、origin/mainを最新本番扱いしない。

配備手順準備済み（未承認・未実行）: /tmp/legacy-received-completion-deploy-20261110.sh、SHA256 50bd68b393234c9b880f11d5ee6bec756cff656334723197b370bbd699ab7896。固定元ac363→固定先6ef5280fa。MANAGER末尾の正確なコマンドへの承認を得て実行する。
