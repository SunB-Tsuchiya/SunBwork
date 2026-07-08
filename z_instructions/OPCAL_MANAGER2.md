# OPCAL_MANAGER2.md — オペレーターカレンダー Phase 2 進捗管理

---

## 進捗一覧テーブル

| # | タスク | 状態 | 備考 |
|---|-------|------|------|
| 2-1 | マイグレーション: operator_reservation_requests | ✅ 完了 | |
| 2-2 | マイグレーション: operator_reservation_notifications | ✅ 完了 | FK自動生成名が64文字超過でエラー→明示的な短い制約名で解決 |
| 2-3 | `php artisan migrate` | ✅ 完了 | |
| 2-4 | Model: OperatorReservationRequest | ✅ 完了 | |
| 2-5 | Model: OperatorReservationNotification | ✅ 完了 | |
| 2-6 | Controller: notifications / markNotificationRead | ✅ 完了 | |
| 2-7 | Controller: storeRequest | ✅ 完了 | |
| 2-8 | Controller: respondRequest（自動却下ロジック含む） | ✅ 完了 | |
| 2-9 | Controller: index/data に pendingRequestReservationIds 追加 | ✅ 完了 | |
| 2-10 | routes/web.php にルート追加 | ✅ 完了 | |
| 2-11 | Vue: 予約作成モーダルの競合検出＋分岐ボタン | ✅ 完了 | |
| 2-12 | Vue: 通知ベル・ドロップダウン（承諾/拒否ボタン） | ✅ 完了 | |
| 2-13 | Vue: 保留リクエストありブロックの点滅表示 | ✅ 完了 | |
| 2-14 | npm run build + 動作確認 | 🔄 作業中 | build成功。ブラウザでの動作確認はユーザー依頼待ち |

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
| 2026-07-08 | Phase1 | 実装完了（UTC/JSTバグ修正・localStorage永続化含む、Codexレビュー対応済み） | Claude |
| 2026-07-08 | Phase2 | 設計ドキュメント作成、ユーザー確認済み | Claude |

---

## 注意事項・決定事項

- リクエストは新規作成時のみ。ドラッグ移動・リサイズでの重複には適用しない
- 承諾＝既存予約を削除して新規予約に置き換え（残す/両方保持ではない）
- 通知はページ内のみ。AppLayout・HandleInertiaRequestsミドルウェアは変更しない
- 承諾/拒否は利用ロール内なら誰でも可能（対象予約の予約者本人に限定しない）
- 1件承諾されたら同一既存予約への他の pending リクエストは自動 rejected

---

## 関連ドキュメント

- 設計詳細: `z_instructions/OPCAL_PLAN2.md`
- 新セッション用プロンプト: `z_instructions/OPCAL2_PROMPT.md`
- Phase 1: `z_instructions/OPCAL_PLAN1.md` / `OPCAL_MANAGER1.md` / `OPCAL1_PROMPT.md`
