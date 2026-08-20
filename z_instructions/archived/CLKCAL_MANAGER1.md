# CLKCAL_MANAGER1 — Clerk カレンダー機能 移設 進捗管理

対応する設計書: `CLKCAL_PLAN1.md`

## 作業フロー

1. マイグレーション作成・実行（`clerk_events`, `clerk_week_posts`）
2. モデル作成（`ClerkEvent`, `ClerkWeekPost`）
3. コントローラ作成（`ClerkCalendarController`, `ClerkEventController`, `ClerkWeekPostController`）
4. ルート追加（`routes/web.php`）
5. フロントエンド作成（`Pages/Clerk/Calendar/Index.vue`, `Components/Clerk/ClerkScheduleCalendar.vue`, `Components/Clerk/ClerkWeekPlanner.vue`）
6. `ClerkNavigationTabs.vue` にタブ追加
7. `npm run build`
8. 動作確認（ユーザー依頼時のみブラウザ確認）
9. ChangelogSeeder 追記・CONSOLIDATED更新・ファイルをarchivedへ移動

## 進捗一覧

| # | タスク | 状態 |
|---|---|---|
| 1 | マイグレーション作成・migrate実行 | 完了 |
| 2 | ClerkEvent / ClerkWeekPost モデル作成 | 完了 |
| 3 | ClerkCalendarController 作成 | 完了 |
| 4 | ClerkEventController 作成 | 完了 |
| 5 | ClerkWeekPostController 作成 | 完了 |
| 6 | routes/web.php にルート追加 | 完了 |
| 7 | Pages/Clerk/Calendar/Index.vue 作成 | 完了 |
| 8 | Components/Clerk/ClerkScheduleCalendar.vue 作成 | 完了 |
| 9 | Components/Clerk/ClerkWeekPlanner.vue 作成 | 完了 |
| 10 | ClerkNavigationTabs.vue にタブ追加 | 完了 |
| 11 | npm run build | 完了 |
| 12 | ChangelogSeeder 追記 + db:seed | 完了 |
| 13 | CONSOLIDATED_09 更新 | 完了（CONSOLIDATED_01は該当セクションなしのため変更なし） |
| 14 | z_instructions/archived へ移動 | 完了 |

## 作業ログ

- 2026-08-20: PLAN/MANAGER/PROMPT 作成。データ共有範囲は会社単位（company_id）と決定（ユーザー確認済み）。
  ユーザーからの補足: Clerk はチームではなく `leader > clerk > coordinator > user` の権限階層の1つ。
- 2026-08-20: ユーザーより「Clerkは2〜3人で予定を全員で共同管理したい」との追加要望を受け、予定・週の掲示板の
  編集/削除権限から所有者チェックを外す方針に変更（PLAN1へ反映）。
- 2026-08-20: 実装完了。migrate・npm run build・ChangelogSeeder（`clerk-calendar-1`）・CONSOLIDATED_09 更新まで完了。
  ブラウザでの動作確認はユーザー依頼待ち。
