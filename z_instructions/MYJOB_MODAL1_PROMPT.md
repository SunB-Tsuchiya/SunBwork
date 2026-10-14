# 再開用: マイジョブ通常新規登録モーダル 1

更新: 2026-10-14

最初に `AGENTS.md`、`JOB_EVALUATION_CONTROL1.md`、`JOB_INPUT_REVIEW_PLAN1.md` / `MANAGER1.md`、`MYJOB_MODAL_PLAN1.md` / `MANAGER1.md` を読む。利用者は「別ページのまま」という最初の回答を撤回し、気軽に入れられるモーダルを選んだ。初期表示は案件・ジョブ名・作業日と開始/終了・任意数量、概要・ファイル・種類等は開閉式詳細で承認済み。本番は情報出版 Super Admin 試用者だけで、一般利用者は現行画面を使う。

通常入口は MyJobBox 一覧と個人カレンダーから `events.create_job` → `Events/Create_Job.vue`。依頼引継ぎ等は `MyJobBox/Create_user.vue` を使うため混同しない。現在の実装・反映・画面確認は `MYJOB_MODAL_MANAGER1.md` を確認。既存の依頼転換、進行表、過去流用、編集、ファイル、日報・分析のデータ経路を保護する。大まかな案件と ProjectJob の紐づけは後工程。

root 作業ツリーは他者の変更があり分岐している。隔離作業ツリーで作業し、GitHub main と照合してから push。さくら SSH は正確なコマンドを提示して利用者の事前確認を得る。本番 sales DB は参照しない。
