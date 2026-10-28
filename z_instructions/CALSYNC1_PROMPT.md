# CALSYNC1_PROMPT.md — 新セッション開始用（チームルーム／案件カレンダーを Clerk 相当に）

▼ここから
チームルームのスケジュールと、案件のスケジュール（案件詳細のスケジュールタブ）を、Clerk のカレンダーと同等の機能・見た目にする作業の続きです。
先に `z_instructions/CALSYNC_PLAN1.md`（詳細仕様）と `CALSYNC_MANAGER1.md`（進捗）を読んでください。

## 概要
- お手本: `/clerk/calendar`（`Components/Clerk/ClerkScheduleCalendar.vue`、`useClerkCalendarStrip.js`、`ClerkCalendarColorPanel.vue`）
- 対象: `Components/TeamRoom/TeamScheduleCalendar.vue`、`Components/ProjectCalendar.vue`（案件詳細のスケジュールタブ）
- 足すもの: 月のストリップ、祝日（会社の休日カレンダー）、色設定（チーム・案件ごとに独立）、予定の色の選択、（チームのみ）完了チェックと複製
- 既にあるもの（触らない）: ドラッグ・リサイズ・CSV・週プランナー・パネル編集

## 確定済みの仕様（ユーザー確認済み）
1. 色設定は、チームルームごと・案件ごとに独立。
2. 案件の予定は、状態色（完了＝緑・超過＝赤・間近＝黄）を優先。通常のときだけ種類色。
3. チームルームに完了チェックと複製を足す。
4. 色設定を変更できる人: チーム＝メンバー全員／案件＝案件を編集できる人（コーディネーター・Leader・管理者）。
5. 完了チェック: 作成者と SuperAdmin のみ。複製: メンバー全員。

## 守ること
- Clerk のカレンダーの動きを変えない（ストリップ部品は引数化のみ。既定は今の Clerk の経路）。
- `ProjectCalendar.vue` は opt-in の prop で追加のみ。`Coordinator/ProjectSchedules/Calendar.vue` は変更しない。
- migration の外部キー名は 64 文字以内の短い名前を明示する。テストは 1 つずつ実行する。
- 日付は JST。FullCalendar の allDay の終了日は排他的（+1 日）の既存の扱いを変えない。
- 本番デプロイは、別 worktree 方式（TRAINS と同じ）で、push と SSH のコマンドを先に見せて承認を取ってから実行する。
▲ここまで
