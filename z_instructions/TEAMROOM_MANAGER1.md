# TEAMROOM_MANAGER1.md — チームルーム 進捗管理

---

## 作業フロー

```
Phase 1（基盤）→ Phase 2（スケジュール）→ Phase 3（会議記録）→ Phase 4（ボード）
```

各フェーズ完了後に `npm run build` を実行し、動作確認してから次フェーズに進む。

---

## 進捗一覧テーブル

### Phase 1: 基盤 + 概要・メンバータブ

| # | タスク | 状態 | 備考 |
|---|-------|------|------|
| 1-1 | マイグレーション: team_events | ⬜ 未着手 | |
| 1-2 | マイグレーション: team_boards | ⬜ 未着手 | |
| 1-3 | マイグレーション: team_board_columns | ⬜ 未着手 | |
| 1-4 | マイグレーション: team_board_cards | ⬜ 未着手 | |
| 1-5 | マイグレーション: team_meeting_minutes | ⬜ 未着手 | |
| 1-6 | マイグレーション: team_meeting_attendees | ⬜ 未着手 | |
| 1-7 | マイグレーション: team_meeting_comments | ⬜ 未着手 | |
| 1-8 | `php artisan migrate` | ⬜ 未着手 | |
| 1-9 | Model: TeamEvent | ⬜ 未着手 | |
| 1-10 | Model: TeamBoard | ⬜ 未着手 | |
| 1-11 | Model: TeamBoardColumn | ⬜ 未着手 | |
| 1-12 | Model: TeamBoardCard (SoftDeletes + morphToMany attachments) | ⬜ 未着手 | |
| 1-13 | Model: TeamMeetingMinute (morphToMany attachments) | ⬜ 未着手 | |
| 1-14 | Model: TeamMeetingAttendee | ⬜ 未着手 | |
| 1-15 | Model: TeamMeetingComment | ⬜ 未着手 | |
| 1-16 | TeamRoomController (index, show) | ⬜ 未着手 | |
| 1-17 | routes/web.php にルート追加 | ⬜ 未着手 | |
| 1-18 | TeamRoom/Index.vue | ⬜ 未着手 | |
| 1-19 | TeamRoom/Show.vue（タブシェル + overview） | ⬜ 未着手 | |
| 1-20 | TeamOverview.vue | ⬜ 未着手 | |
| 1-21 | AppLayout.vue サイドバーにリンク追加 | ⬜ 未着手 | |
| 1-22 | npm run build + 動作確認 | ⬜ 未着手 | |

### Phase 2: スケジュールタブ

| # | タスク | 状態 | 備考 |
|---|-------|------|------|
| 2-1 | TeamEventController (index/store/update/destroy) | ⬜ 未着手 | |
| 2-2 | TeamSchedule.vue | ⬜ 未着手 | |
| 2-3 | Show.vue に schedule タブ追加 | ⬜ 未着手 | |
| 2-4 | npm run build + 動作確認 | ⬜ 未着手 | |

### Phase 3: 会議記録

| # | タスク | 状態 | 備考 |
|---|-------|------|------|
| 3-1 | TeamMeetingMinuteController (CRUD) | ⬜ 未着手 | |
| 3-2 | TeamMeetingCommentController (store/destroy) | ⬜ 未着手 | |
| 3-3 | Minutes/Create.vue (Quill + 参加者 + 添付) | ⬜ 未着手 | |
| 3-4 | Minutes/Show.vue (本文 + 参加者 + コメント) | ⬜ 未着手 | |
| 3-5 | Minutes/Edit.vue | ⬜ 未着手 | |
| 3-6 | TeamMinutesList.vue（タブ内一覧） | ⬜ 未着手 | |
| 3-7 | Show.vue に minutes タブ追加 | ⬜ 未着手 | |
| 3-8 | npm run build + 動作確認 | ⬜ 未着手 | |

### Phase 4: プロジェクトボード

| # | タスク | 状態 | 備考 |
|---|-------|------|------|
| 4-1 | TeamBoardController (store, updateColumns) | ⬜ 未着手 | |
| 4-2 | TeamBoardCardController (store/update/destroy) | ⬜ 未着手 | |
| 4-3 | TeamBoard.vue（ボード/一覧 切り替え + 新規作成） | ⬜ 未着手 | |
| 4-4 | TeamBoardCard.vue | ⬜ 未着手 | |
| 4-5 | TeamBoardEditMode.vue（カラム管理） | ⬜ 未着手 | |
| 4-6 | カード添付 API（既存 AttachmentController 流用） | ⬜ 未着手 | |
| 4-7 | Show.vue に board タブ追加 | ⬜ 未着手 | |
| 4-8 | npm run build + 動作確認 | ⬜ 未着手 | |

---

## 状態凡例

| 記号 | 意味 |
|------|------|
| ⬜ 未着手 | まだ作業していない |
| 🔄 作業中 | 現在進行中 |
| ✅ 完了 | 実装・確認済み |
| ⚠️ 問題あり | ブロッカーあり、要確認 |

---

## 作業ログ

| 日付 | フェーズ | 内容 | 担当 |
|------|---------|------|------|
| 2026-06-03 | — | 設計ドキュメント作成 | Claude |

---

## 注意事項・決定事項

- ボードカードのカラム削除時: カードが存在する場合は警告ダイアログを表示。強制削除するとカードも消える（ソフトデリート）
- 会議記録の編集・削除: 作成者 OR チームの `leader_id` のみ可能
- 添付ファイル: `attachmentables` ポリモーフィックテーブルを流用。`attachable_type` に `App\Models\TeamBoardCard` または `App\Models\TeamMeetingMinute` を使用
- FullCalendar のイベントは JST 文字列で保存（proof イベントではないため UTC 変換不要）
- ボードのデフォルト3列: `予定`(color: yellow) / `作業中`(color: blue) / `完了`(color: green)
- チームルームへのアクセスは `team_user` ピボットのメンバーシップで制御（SuperAdmin は例外的にすべて閲覧可）

---

## 関連ドキュメント

- 設計詳細: `z_instructions/TEAMROOM_PLAN1.md`
- 新セッション用プロンプト: `z_instructions/TEAMROOM1_PROMPT.md`
