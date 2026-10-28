# CALSYNC_MANAGER1.md — 進捗管理（チームルーム／案件カレンダーを Clerk 相当に）

作成日: 2026-10-28 ／ 詳細設計: CALSYNC_PLAN1.md ／ 継続プロンプト: CALSYNC1_PROMPT.md

## 現在のステータス
| フェーズ | 状態 | 開始日 | 完了日 |
|---|---|---|---|
| 設計（仕様確認 5 問） | ✅ 完了 | 2026-10-28 | 2026-10-28 |
| 設計書の確認（ユーザー） | 🔲 待ち | | |
| P1 共通 | 🔲 未着手 | | |
| P2 チームルーム | 🔲 未着手 | | |
| P3 案件 | 🔲 未着手 | | |
| P4 仕上げ・デプロイ | 🔲 未着手 | | |

## タスク
### P1 共通
| # | タスク | 状態 |
|---|---|---|
| 1-1 | migration（色設定 2 表、`team_events` に color_key・completed_at、`project_schedules` に color_key） | 🔲 |
| 1-2 | `TeamCalendarColor`・`ProjectCalendarColor` モデル | 🔲 |
| 1-3 | 祝日の共通処理（`ClerkCalendarHolidays` へ。Clerk の `data` はそれを使う） | 🔲 |
| 1-4 | `useClerkCalendarStrip.js` の祝日の取得先を引数化（既定は Clerk の経路） | 🔲 |
| 1-5 | 汎用の色設定の窓 `ScheduleColorPanel.vue` | 🔲 |
### P2 チームルーム
| # | タスク | 状態 |
|---|---|---|
| 2-1 | 色設定 API（メンバー全員）・祝日 API（メンバーのみ） | 🔲 |
| 2-2 | `TeamEventController`: color_key、完了（作成者・SuperAdmin） | 🔲 |
| 2-3 | `TeamScheduleCalendar.vue` の全面反映（ストリップ・祝日・色・完了・複製） | 🔲 |
| 2-4 | PHP テスト | 🔲 |
### P3 案件
| # | タスク | 状態 |
|---|---|---|
| 3-1 | 色設定 API（読み取り＝閲覧者も、書き込み＝案件を編集できる人）・祝日 API | 🔲 |
| 3-2 | 案件の予定の作成・更新・一覧に color_key | 🔲 |
| 3-3 | `ProjectCalendar.vue` に opt-in prop。状態色優先・通常のときだけ種類色 | 🔲 |
| 3-4 | 案件詳細ページ 2 つに prop を渡す（別画面は変更しない） | 🔲 |
| 3-5 | PHP テスト | 🔲 |
### P4 仕上げ
| # | タスク | 状態 |
|---|---|---|
| 4-1 | lint・全テスト・`npm run build` | 🔲 |
| 4-2 | ChangelogSeeder・CONSOLIDATED_05 の更新、PLAN/MANAGER/PROMPT を archived へ | 🔲 |
| 4-3 | デプロイ（別 worktree 方式。migration あり。承認を取ってから実行） | 🔲 |

## 設計上の決定事項（ユーザー確認済み）
| 項目 | 決定 | 日付 |
|---|---|---|
| 色設定の単位 | チームルームごと・案件ごとに独立（A） | 2026-10-28 |
| 案件の予定の色 | 状態色を優先、通常のときだけ種類色（A） | 2026-10-28 |
| チームの完了チェック・複製 | 足す（A） | 2026-10-28 |
| 色設定を変更できる人 | チーム＝メンバー全員／案件＝案件を編集できる人（A） | 2026-10-28 |
| 完了チェックを押せる人 | 作成者と SuperAdmin のみ。複製はメンバー全員（A） | 2026-10-28 |

## 作業ログ
| 日付 | 内容 |
|---|---|
| 2026-10-28 | 調査（Clerk／チームルーム／案件のカレンダーの差）、仕様 5 問の確認、PLAN/MANAGER/PROMPT 作成 |
