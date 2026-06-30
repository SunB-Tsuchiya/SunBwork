# PROOFRESV1_PROMPT

SunBWork の校正予約機能を再開するための要約。

## 確定事項

- 通常の `proof_requests` は流用せず、専用 `proof_reservations` テーブルを使用する。
- Coordinator 案件一覧の「校正予約」ボタンから専用モーダルを開く。
- 依頼予定と締め切りは、それぞれ日時入力または自由記述へ切替可能。
- proof-admin に予約一覧・詳細を追加する。
- 両端が確定日時の場合、詳細のボタンで校正カレンダーへ登録する。
- 校正カレンダー月表示では、依頼予定を開始、締め切りを終了とする期間ストリップを1本表示する。

## 最初に読むもの

- `AGENTS.md`
- `CLAUDE.md`
- `z_instructions/PROOFRESV_PLAN1.md`
- `z_instructions/PROOFRESV_MANAGER1.md`
- `z_instructions/CONSOLIDATED_01_layout_and_ui.md`
- `z_instructions/CONSOLIDATED_05_calendar_and_jobbox.md`

## 禁止事項

- 予約を通常校正依頼の受信箱・ジョブ管理へ混入させない。
- JST入力をブラウザの `toISOString().slice(0, 10)` で日付化しない。
- 既存のユーザー変更や無関係な `public/build` 差分を巻き戻さない。
