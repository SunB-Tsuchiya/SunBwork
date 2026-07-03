# ACTUALCOPY_MANAGER1.md — 進捗管理

最終更新: 2026-07-03
関連: [ACTUALCOPY_PLAN1.md](ACTUALCOPY_PLAN1.md)

---

## 作業フロー

1. Phase 1: DB・Model
2. Phase 2: Controller・Route
3. Phase 3: フロントエンド（EventDetailModal / UserCalendar）
4. Phase 4: 動作確認（ローカルで一通り操作テスト）・`npm run build`
5. Phase 5: ユーザー確認 → 問題なければ ChangelogSeeder 追記・CONSOLIDATED_09（ドメインルール）更新 → 完了ファイルを archived/ へ移動

---

## 進捗一覧

| Phase | 内容 | 状態 |
|---|---|---|
| 1 | migration追加（source_schedule_event_id）、Event モデルにリレーション追加 | 完了 |
| 2 | `ScheduleEventController::materialize()` 追加、`range()` の重複除外ロジック追加、ルート追加 | 完了 |
| 3 | `EventDetailModal.vue` に「実績として記録する」ボタン追加、`UserCalendar.vue` 側の再読み込みハンドラ追加 | 完了 |
| 4 | ローカルDockerで動作確認（招待イベント→実績記録→編集→削除、マスター側変更してもコピーに影響しないこと） | 完了 |
| 5 | ChangelogSeeder追記・CONSOLIDATED_09更新・ファイルアーカイブ | 完了 |

---

## 作業ログ

- 2026-07-03: 設計確定。ユーザーとの相談の結果、当初案の「別テーブルでの表示オーバーレイ方式」ではなく「実績記録時に自分名義のevents` 行を複製する方式」を採用。理由: 工数分析（`WorkloadAnalyzerController`）が `user_id` 一致のイベントのみ集計する実装のため、複製方式なら無改修で反映される。生成トリガーは手動ボタンのみに決定（自動生成は行わない）。
- 2026-07-03: 全Phase実装完了。migration・Event モデル・`ScheduleEventController::materialize()`・ルート・`EventDetailModal.vue`（？アイコン付きボタン）・`UserCalendar.vue` を実装し、ローカルDockerでtinkerによるE2E動作確認済み（招待→実績コピー作成→重複除外→マスター削除してもコピー無傷→工数分析側で拾われることを確認）。`npm run build` 成功。ChangelogSeeder に `actual-copy-1` を追記・反映済み。CONSOLIDATED_09 にドメインルールとして追記済み。さくらデプロイは未実施。
