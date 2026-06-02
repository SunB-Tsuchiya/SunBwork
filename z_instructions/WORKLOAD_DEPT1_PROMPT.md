# WORKLOAD_DEPT1_PROMPT — 新セッション開始用プロンプト

## このファイルの使い方

新セッションでこの作業を継続する場合、以下をプロンプトとして貼り付ける。

---

## プロンプト本文

`workload-setting`（作業項目設定）に部署スコープを追加する作業を継続してください。

**設計サマリー:**
- 各設定（Stages / WorkItemTypes / Sizes / Statuses / Difficulties）を「会社全体（department_id=NULL）」と「部署固有（department_id=X）」で完全独立して登録・編集できる
- UI: ページ上部に部署切り替えボタンバー。Leader は自部署のみ編集可（他はグレー）。SuperAdmin/Admin は全スコープ切り替え可
- DB変更不要（department_id カラムは全対象テーブルに nullable で存在済み）
- スコープは URL クエリパラメータ `?dept=company|{id}` で管理

**詳細設計:** `z_instructions/WORKLOAD_DEPT_PLAN1.md` を参照
**進捗:** `z_instructions/WORKLOAD_DEPT_MANAGER1.md` を参照

**変更対象ファイル:**
1. `app/Http/Controllers/WorkloadSettingController.php`
2. `resources/js/Pages/WorkloadSetting/Index.vue`
3. `resources/js/Pages/WorkloadSetting/Edit.vue`

作業完了後は `npm run build` → さくら用ビルド → コミット → ローカル用ビルドに戻す。
