# 依頼登録の共通引継ぎ 再開1

> 2026-11-10最新優先: 保存前2点（入口別の同一初期値/固定表示とドラッグ日時保持）は利用者『成功』で確認済み、反復不要。次は登録してよい依頼1件の保存後詳細保持の確認。branch feature/myjob-request-checks-20261110、tree /tmp/sunbwork-request-checks-20261110（保存9e4e75646から旧/tmp消失により復元）。一般公開未承認。MANAGER末尾参照。

> 2026-11-09最新優先: 共通引継ぎを本番b4f585ba6へ限定配備しexit0。今回の保存前2点（旧詳細/簡易経由の同一初期値と固定表示、ドラッグ日時保持）は利用者回答待ち。保存後の詳細保持は未確認。MYJOB_REQUEST_HANDOFF_MANAGER1末尾を読む。一般公開未承認、既成功試験は反復しない。

> 2026-11-09最新優先: 具体設計を利用者yesで承認。共通初期値の実装とIntegration64件790断言/実Vue/ナビ/PHP9構文/実Ziggy/members buildが成功。配備・画面確認は未実施。次は本番5ef950b77からの固定差分を保存し正確なSSH承認。MYJOB_REQUEST_HANDOFF_MANAGER1末尾参照。一般公開未承認、既成功試験は反復しない。

更新: 2026-11-09 JST。
AGENTS.md→JOB_EVALUATION_CONTROL1.md→MYJOB_REQUEST_HANDOFF_PLAN1.md/MANAGER1.md→MYJOB_REQUEST_HANDOFF_AUDIT1.mdと親統合資料を読む。

利用者は共通prefill案と修正着手をOK。3資料を作成、具体設計は実装前確認待ち。コード修正/検証/build/配備はまだ。次の一手はPLAN1の具体条件を示して実装前確認を得る。
保存基準45eaece31、branch feature/myjob-request-handoff-audit-20261109、tree /tmp/sunbwork-request-handoff-audit-20261109。旧/tmpは消失し保存5e93から復元済み。最後の配備証拠5ef950b77であり、最新本番は配備時に再照合する。

問題はCreate_Job/Create_userが依頼選択で4項目だけを代入し_client_idとprojectJob等を更新しないこと。旧modalも詳細不足。サーバ認可つき共通prefill→元依頼/案件/候補/固定をまとめて受渡し、ドラッグ日時保持、保存時はロックして認可/登録競合/更新を再検証。元入力を一律上書きせず、supersedes/続き/校正/セル参照を区別。statusは入口別既存挙動を維持。詳細はPLAN。

試用限定・一般公開未承認、共有Claude変更/CLAUDE.md未編集、sales禁止。実DBで試験しない。前回完了連動2点と依頼主通知の実確認は未完。既成功の登録済・候補除外等を再試験しない。

固定配備手順準備済み（未承認・未実行）: /tmp/myjob-request-prefill-deploy-20261109.sh、SHA256 4e001bd97cfe401e2820c3df843feff4e7380b6bd9b82e7c8d883a24674d052a。固定元5ef950b77→固定先b4f585ba6。正確なコマンドと条件はMANAGER末尾。次は利用者のSSH承認。
