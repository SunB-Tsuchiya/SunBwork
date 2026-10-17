# 再開用: 依頼ジョブ新規登録画面の整理 1

更新: 2026-10-17。最初に `AGENTS.md`、`JOB_EVALUATION_CONTROL1.md`、`REQUEST_JOB_FORM_PLAN1.md`、`REQUEST_JOB_FORM_MANAGER1.md` を読み、`JOB_INPUT_REVIEW_MANAGER1.md` と最新の利用者発言を照合する。

利用者は案件372の通常新規登録画面を保存せず撮影し、全項目を常時表示したまま、並びと説明を整理する方針を選んだ。概要・ファイル・作業詳細を開閉式へ移す案は不採用。さらに「割当ユーザー: 未指定」で送信すると本人へ自動補完される現行動作を改め、未指定なら送信できず担当者の選択を求めると確定。本人宛て依頼は本人を明示的に選べば可能。これは横断台帳 D13。

画面構成案は、案件の固定表示 → ジョブ名・割当先・締め切り → 概要・ファイル → 作業種別・ステージ・サイズ・数量・難易度・見積時間 → 追加・送信。全欄を開閉せず残す。情報出版 Super Admin の新規依頼だけで先に試し、他の利用者・既存編集・マイジョブ・進行表の保存関係を守る。`AssignmentForm.vue` の共用範囲、`ProjectJobAssignmentsController::create()/store()`、`JobAssign/Edit.vue` を確認済み。コード・ビルド・デプロイ・利用者による修正後画面確認は未実施。`REQUEST_JOB_FORM_MANAGER1.md` を更新する。

`AGENTS.md` の大規模作業規則により、PLAN/MANAGER/PROMPT を先に作成した。具体的な計画への利用者確認が済んでから実装する。さくら SSH には毎回正確なコマンド提示と事前確認が必要。本番 sales DB は参照しない。隔離ツリー `/tmp/sunbwork-myjob-only-20261016` がなければ最新の `origin/main` と作業ツリーを照合し、他者の変更を保護する。別人発信依頼のマイジョブ転換の実画面保存と別アカウントの旧導線確認は引き続き保留。

**2026-10-17 更新:** 利用者は計画を `yes` と承認し、Super Admin 限定を念押し。コードはコントローラ、`JobAssign/Edit.vue`、共通 `AssignmentForm.vue` の3ファイルを修正。情報出版 Super Admin の新規依頼だけ全欄の並びを変え、送信時の割当先未指定を画面・サーバの両方で拒否する。対象 ESLint エラー0、PHP構文、`/members` 用 Vite ビルド、manifest 428件の参照先検査、`git diff --check` は成功。旧追跡済みアセットは残した。DB試験・実画面・GitHub・さくら反映は未実施。上の古い「確認後に実装する」より本行と `REQUEST_JOB_FORM_MANAGER1.md` を優先する。
