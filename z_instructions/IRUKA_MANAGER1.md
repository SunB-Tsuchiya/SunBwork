# IRUKA_MANAGER1.md — イルカ（在席管理）機能 進捗管理

作成日: 2026-05-15

---

## 進捗一覧

| Phase | タスク | 状態 | 担当セッション | 備考 |
|---|---|---|---|---|
| **Phase 1** | migration 作成・実行 | ✅完了 | 2026-05-15 | |
| **Phase 1** | UserPresenceStatus モデル作成 | ✅完了 | 2026-05-15 | |
| **Phase 1** | UserPresenceController 作成 | ✅完了 | 2026-05-15 | maybeAutoCreateDiary含む |
| **Phase 1** | routes/web.php ルート追加 | ✅完了 | 2026-05-15 | |
| **Phase 2** | IrukaStatusBadge.vue 作成 | ✅完了 | 2026-05-15 | ヘッダー用 |
| **Phase 2** | IrukaStatusModal.vue 作成 | ✅完了 | 2026-05-15 | モーダル本体 |
| **Phase 2** | AppLayout.vue ヘッダー組み込み | ✅完了 | 2026-05-15 | |
| **Phase 3** | IrukaBoard.vue 作成 | ✅完了 | 2026-05-15 | |
| **Phase 3** | 部署フィルター実装 | ✅完了 | 2026-05-15 | |
| **Phase 3** | 30秒ポーリング実装 | ✅完了 | 2026-05-15 | |
| **Phase 4** | Dashboard.vue タブ切替 (User) | ✅完了 | 2026-05-15 | localStorage保存 |
| **Phase 4** | Admin Dashboard 置換 | ✅完了 | 2026-05-15 | |
| **Phase 4** | Coordinator Dashboard 置換 | ✅完了 | 2026-05-15 | |
| **Phase 4** | Leader Dashboard 置換 | ✅完了 | 2026-05-15 | |
| **Phase 4** | Clerk Dashboard 置換 | ✅完了 | 2026-05-15 | |
| **Phase 4** | SuperAdmin Dashboard 置換 | ✅完了 | 2026-05-15 | |
| **Phase 4** | Prepress Dashboard 置換 | ✅完了 | 2026-05-15 | |
| **Phase 5** | autoCheckout ロジック実装 | ✅完了 | 2026-05-15 | UserPresenceControllerに実装 |
| **Phase 6** | カレンダー連携スタブ | 保留 | | status_sourceフラグ設計済み |
| **Phase 7** | migration sort_order/is_hidden追加 | ✅完了 | 2026-05-15 | |
| **Phase 7** | PresenceBoardSettingsController作成 | ✅完了 | 2026-05-15 | Admin全部署/Leader自部署 |
| **Phase 7** | IrukaBoardSettings.vue作成 | ✅完了 | 2026-05-15 | ▲▼並替・トグル・保存 |
| **Phase 7** | UserPresenceController sort_order/is_hidden対応 | ✅完了 | 2026-05-15 | |
| **Phase 7** | Admin Dashboard 管理ボタン追加 | ✅完了 | 2026-05-15 | headerExtrasスロット |
| **Phase 7** | Leader Dashboard 管理ボタン追加 | ✅完了 | 2026-05-15 | 自部署のみ |
| **Phase 8** | 管理ボタンをタブメニューに移動 | ✅完了 | 2026-05-16 | Admin/Leader タブに「在席ボード管理」追加 |
| **Phase 8** | BoardSettings LAYOUT_SPEC_V2 対応 | ✅完了 | 2026-05-16 | 戻るボタン・max-w-2xl・indigo保存ボタン |
| **Phase 8** | BoardSettings 部署フィルター（Admin） | ✅完了 | 2026-05-16 | 部署ボタンで絞り込み、部署カラム削除 |
| **Phase 8** | BoardSettings 全Leader対応 | ✅完了 | 2026-05-16 | department_idベース・isDepartmentLeader条件撤廃 |
| **Phase 8** | BoardSettings テーブルUI改善 | ✅完了 | 2026-05-16 | 固定幅・中央寄せ・▲▼常時表示 |
| **Phase 9A** | statusConfig.js 18ステータスに更新 | ✅完了 | 2026-05-16 | meeting分割・新slug・パステル色定義 |
| **Phase 9A** | IrukaStatusModal.vue UIリニューアル | ✅完了 | 2026-05-16 | 6行3列・パステル色・ドット削除・statuses prop |
| **Phase 9A** | IrukaStatusBadge.vue 更新 | ✅完了 | 2026-05-16 | statuses fetch・モーダルへ渡す |
| **Phase 9B** | syncCalendarStatus() 実装 | ✅完了 | 2026-05-16 | index()冒頭で呼び出し |
| **Phase 9B** | EventItemType→status マッピング | ✅完了 | 2026-05-16 | conference/outing等6種対応 |
| **Phase 9C** | migration iruka_status_orders | ✅完了 | 2026-05-16 | migrate実行済み |
| **Phase 9C** | IrukaStatusOrder モデル（lazy seed） | ✅完了 | 2026-05-16 | getOrCreateForCompany()で自動初期化 |
| **Phase 9C** | PresenceBoardSettingsController 拡張 | ✅完了 | 2026-05-16 | updateStatuses追加・statusOrders prop |
| **Phase 9C** | BoardSettings.vue ステータス管理タブ追加 | ✅完了 | 2026-05-16 | タブ切替・▲▼・表示/非表示 |
| **Phase 9C** | IrukaBoard.vue statuses対応 | ✅完了 | 2026-05-16 | statuses fetch・モーダルへ渡す |

---

## 作業ログ

### 2026-05-16
- Phase 8 実装完了・ビルド成功
- 管理ボタンをタブメニューへ移動、BoardSettings レイアウト・UI全面改善
- 全Leader対応（department_idベース）
- Phase 9 設計完了（カレンダー連携・ステータスUI・ボード設定拡張）
- EventItemType DB調査完了・マッピング確定

### 2026-05-15
- Q&A完了・設計ドキュメント作成
- Phase 1〜5 実装完了・ビルド成功

---

## 作業フロー

```
Phase 1（バックエンド）
  ↓ 完了後
Phase 2（ヘッダー統合）← ここから見た目が変わり始める
  ↓ 完了後
Phase 3（イルカボード）
  ↓ 完了後
Phase 4（ダッシュボード統合）
  ↓ 完了後
Phase 5（退社自動日報）
  ↓ 完了後
Phase 6（カレンダー連携・保留）
```

---

## 重要決定事項

| 決定事項 | 内容 |
|---|---|
| 更新方式 | 30秒ポーリング（Reverbはさくら非対応） |
| 他人ステータス変更 | 全ロール・全ユーザーが全員分可能 |
| 他人モーダル | ステータスのみ変更可（コメントは自分のみ） |
| ダッシュボード | User: タブ切替 / 他ロール: イルカボードに置換 |
| 退社自動日報 | start_time=勤務形態の定時 / end_time=退社ボタン押下時刻 |
| 既存日報あり | 退社時に上書きしない |
| カレンダー連携 | status_source フラグで設計、マッピングは後日 |

---

## 新セッション開始時

→ `IRUKA1_PROMPT.md` の内容をコピーしてClaude Codeに貼り付ける
