# CLKCAL1_PROMPT — 新セッション開始用プロンプト

以下をそのままAIに渡せば作業を再開できます。

---

`z_instructions/CLKCAL_PLAN1.md` と `z_instructions/CLKCAL_MANAGER1.md` を読んで、
Clerk カレンダー機能の移設作業を進めてください。

## 設計サマリー

- team-rooms のスケジュールタブ（`TeamScheduleCalendar.vue` + `TeamWeekPlanner.vue`、
  `TeamEventController` + `TeamWeekPostController`）を Clerk 領域に移設する。
- Clerk はチームに属さないため、`team_id` の代わりに `company_id` でデータをスコープする
  （新規テーブル `clerk_events` / `clerk_week_posts`）。同じ会社の clerk / admin / superadmin /
  部署リーダーが同じカレンダーを共有する（`ClerkMiddleware` のアクセス許可ロールと同一）。
- 会社IDの決定は `Clerk\AnnouncementController` と同じパターン:
  `$user->isSuperAdmin() ? session('superadmin_context.company_id') : $user->company_id`
- 予定の編集・削除権限は移設元（作成者本人 or SuperAdmin のみ）から変更する。Clerk は2〜3人で予定を
  共同管理したいとのことなので、カレンダーにアクセスできる人（clerk / admin / superadmin / 部署リーダー）
  なら誰でも編集・削除可能にする（所有者チェックを行わない）。
- ルートは既存の `clerk.` prefix グループ内に追加し、`clerk.calendar` を新設。
  `ClerkNavigationTabs.vue` に「カレンダー」タブを追加する。

`CLKCAL_MANAGER1.md` の進捗一覧テーブルを都度更新しながら実装を進めてください。
Vue/JSファイルを変更したら最後に `npm run build` を実行してください。
