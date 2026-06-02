# WORKLOAD_DEPT_MANAGER1 — 進捗管理

## 進捗テーブル

| # | タスク | ファイル | 状態 |
|---|--------|---------|------|
| 1 | PLAN / MANAGER / PROMPT 作成 | z_instructions/ | ✅ 完了 |
| 2 | コントローラー修正（resolveScope / fetchDepartments / fetchItems / store） | WorkloadSettingController.php | 🔄 進行中 |
| 3 | Index.vue 修正（部署スコープバー UI） | WorkloadSetting/Index.vue | ⬜ 未着手 |
| 4 | Edit.vue 修正（スコープバー + save に department_id 追加） | WorkloadSetting/Edit.vue | ⬜ 未着手 |
| 5 | ビルド（さくら用）・コミット・ローカル用ビルド戻し | — | ⬜ 未着手 |

---

## 作業ログ

### 2026-06-02
- 設計確認：department_id カラムは全対象テーブルに nullable で存在済み（DB変更不要）
- 既存レコードは全て department_id = NULL → 会社スコープに自動分類される
- スコープの関係：完全独立（継承なし）、ワークロード分析での優先ロジックは将来実装
- PLAN / MANAGER / PROMPT ファイル作成完了
