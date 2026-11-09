# 依頼登録の共通引継ぎ 再開1

更新: 2026-11-09 JST。
AGENTS.md→JOB_EVALUATION_CONTROL1.md→MYJOB_REQUEST_HANDOFF_PLAN1.md/MANAGER1.md→MYJOB_REQUEST_HANDOFF_AUDIT1.mdと親統合資料を読む。

利用者は共通prefill案と修正着手をOK。3資料を作成、具体設計は実装前確認待ち。コード修正/検証/build/配備はまだ。次の一手はPLAN1の具体条件を示して実装前確認を得る。
保存基準45eaece31、branch feature/myjob-request-handoff-audit-20261109、tree /tmp/sunbwork-request-handoff-audit-20261109。旧/tmpは消失し保存5e93から復元済み。最後の配備証拠5ef950b77であり、最新本番は配備時に再照合する。

問題はCreate_Job/Create_userが依頼選択で4項目だけを代入し_client_idとprojectJob等を更新しないこと。旧modalも詳細不足。サーバ認可つき共通prefill→元依頼/案件/候補/固定をまとめて受渡し、ドラッグ日時保持、保存時はロックして認可/登録競合/更新を再検証。元入力を一律上書きせず、supersedes/続き/校正/セル参照を区別。statusは入口別既存挙動を維持。詳細はPLAN。

試用限定・一般公開未承認、共有Claude変更/CLAUDE.md未編集、sales禁止。実DBで試験しない。前回完了連動2点と依頼主通知の実確認は未完。既成功の登録済・候補除外等を再試験しない。
