# OPCAL_MANAGER1.md — オペレーターカレンダー 進捗管理

---

## 作業フロー

```
Phase 1（本体 + メンバー予定表フィルタ修正）→ 動作確認 → （将来）Phase 2 提案・着手
```

Phase 1 完了後、`npm run build` を実行し動作確認してから完了とする。
Phase 2（二重予約リクエスト機能）は Phase 1 完了後に Claude から改めて設計を提案する。

---

## 進捗一覧テーブル

### Phase 1: オペレーターカレンダー本体 + メンバー予定表フィルタ修正

| # | タスク | 状態 | 備考 |
|---|-------|------|------|
| 1-1 | マイグレーション: operator_calendar_members | ✅ 完了 | |
| 1-2 | マイグレーション: operator_calendar_color_assignments（11色初期投入） | ✅ 完了 | |
| 1-3 | マイグレーション: operator_reservations | ✅ 完了 | |
| 1-4 | `php artisan migrate` | ✅ 完了 | |
| 1-5 | Model: OperatorCalendarMember | ✅ 完了 | |
| 1-6 | Model: OperatorCalendarColorAssignment | ✅ 完了 | |
| 1-7 | Model: OperatorReservation | ✅ 完了 | |
| 1-8 | OperatorCalendarController（index/data/all） | ✅ 完了 | |
| 1-9 | OperatorCalendarController（members系 CRUD） | ✅ 完了 | reorderは未実装（要件外のため見送り） |
| 1-10 | OperatorCalendarController（reservations系 CRUD） | ✅ 完了 | |
| 1-11 | OperatorCalendarController（color_assignments 更新） | ✅ 完了 | |
| 1-12 | routes/web.php にルート追加 | ✅ 完了 | |
| 1-13 | OperatorCalendar.vue（タイムライン基本構造・日付ナビ） | ✅ 完了 | |
| 1-14 | OperatorCalendar.vue（+メンバー機能） | ✅ 完了 | |
| 1-15 | OperatorCalendar.vue（ドラッグ選択→予約作成モーダル） | ✅ 完了 | |
| 1-16 | OperatorCalendar.vue（予約ブロック編集・移動・リサイズ・削除） | ✅ 完了 | |
| 1-17 | OperatorCalendar.vue（色設定パネル） | ✅ 完了 | |
| 1-18 | OperatorCalendar.vue（案件一覧トグルテーブル） | ✅ 完了 | |
| 1-19 | CoordinatorNavigationTabs.vue にタブ追加 | ✅ 完了 | |
| 1-20 | ProjectJobMemberScheduleController フィルタ修正（whereNotNull event_item_type_id） | ✅ 完了 | |
| 1-21 | npm run build + 動作確認 | 🔄 作業中 | build成功。ブラウザでの動作確認はユーザー依頼待ち |

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
| 2026-07-08 | — | 設計ドキュメント作成（要件ヒアリング完了） | Claude |

---

## 注意事項・決定事項

- 予約データは `events` テーブルと完全独立。オペレーター本人のカレンダー/スケジュールには一切反映しない
- `starts_at`/`ends_at` は JST文字列そのまま格納（校正カレンダーのUTC変換方式は踏襲しない）
- 予約ブロックの色 = 予約者（`reserved_by_user_id`）の色。対象オペレーター本人の色ではない
- 色割当は製版ボードとは別テーブル（`operator_calendar_color_assignments`）
- 利用ロール: Coordinator/Clerk/Admin/SuperAdmin のみ。**Leader は含めない**
  （既存の `coordinator` ミドルウェアは Leader も通すため、コントローラー側で追加チェックが必要）
- 編集・削除は利用ロール内の誰でも可能（予約者本人限定にしない）
- 二重予約は Phase 1 では許容（警告なし、重なり表示のみ）
- メンバー一覧（+メンバーで追加）は全 Coordinator/Clerk 共有の1リスト
- 案件一覧トグルテーブルは全オペレーター・全期間を全件表示

---

## 関連ドキュメント

- 設計詳細: `z_instructions/OPCAL_PLAN1.md`
- 新セッション用プロンプト: `z_instructions/OPCAL1_PROMPT.md`
