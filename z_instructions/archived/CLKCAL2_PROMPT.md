# CLKCAL2_PROMPT — 新セッション開始用プロンプト

以下をそのままAIに渡せば作業を再開できます。

---

`z_instructions/CLKCAL_PLAN2.md` と `z_instructions/CLKCAL_MANAGER2.md` を読んで、
Clerk カレンダーの独自仕様（色分け・完了機能）追加作業を進めてください。

## 設計サマリー

- Phase1（`CLKCAL_PLAN1.md`、archived）で Clerk にカレンダー機能を移設済み。今回はその上に独自仕様を追加する。
- 予定に色を付けられるようにする。色パレットは `Prepress/Board.vue` の `CARD_COLORS`（indigo/blue/cyan/teal/
  green/yellow/orange/red/pink/purple/gray の11色）をそのまま使う。
- 各色のラベルは会社ごとに自由記入できる（`clerk_calendar_colors` テーブル、company_id + color_key でユニーク）。
  Prepress/Operator の色設定は `user_id` 選択式・全社共通1セットだが、Clerk は自由記入・会社ごとに独立させる。
- 色設定パネルは Board.vue の「担当色変更パネル」（ドラッグ並び替え可）と同じUIで、select の代わりに
  text input（自由記入、maxlength 6）にする。ボタンバー上では CSV取込ボタンから離して右寄せに配置する。
- 予定の作成・編集モーダルに色ピッカー（○＋下に6文字ラベル、truncate）を追加し、選んだ色をカレンダー・
  週間プランナー表示に反映する（FullCalendarは inline style で塗るため、色ごとの hex マップを新設する）。
- 完了機能: `clerk_events.completed_at` で管理。完了時はその色のまま opacity を下げ、タイトルに取り消し線。
  切り替えは編集モーダルの「完了にする」ボタンから。

`CLKCAL_MANAGER2.md` の進捗一覧テーブルを都度更新しながら実装を進めてください。
Vue/JSファイルを変更したら最後に `npm run build` を実行してください。
