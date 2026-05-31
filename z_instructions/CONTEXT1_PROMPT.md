# CONTEXT1_PROMPT — 新セッション開始用プロンプト

## タスク概要

SuperAdmin のコンテキスト切り替え（会社選択）に応じて、各ページのデータを選択会社でフィルタリングする修正。

## 問題

- SuperAdmin が「サンエー」を選択しても「サン・ブレーン」の通知一覧が表示される
- Coordinator JobBox / User JobBox も会社に関係なく SuperAdmin 自身の空データが表示される
- グローバル管理モード（会社未選択）では会社固有ページに「会社を選択してください」を表示すべき

## 設計方針（確定済み）

- グローバルモード → `isGlobalMode: true` + 空データ → Vue 側で「会社を選択してください」バナー
- 会社X選択時 → company_id = X でデータフィルタリング
- 一般ユーザーへの影響なし

## 参照ファイル

- 設計詳細: `z_instructions/CONTEXT_PLAN1.md`
- 進捗管理: `z_instructions/CONTEXT_MANAGER1.md`

## コンテキスト情報

- セッションキー: `superadmin_context.company_id`（null = グローバルモード）
- `ResolvesContextCompany` トレイト: `app/Http/Controllers/Concerns/ResolvesContextCompany.php`
- 今回は直接 `session('superadmin_context.company_id')` を参照（トレイト未使用コントローラーでも対応）

## 変更ファイル（9ファイル、うち1新規）

1. `resources/js/Components/SuperAdminGlobalGuard.vue` ← 新規
2. `app/Http/Controllers/Clerk/AnnouncementController.php`
3. `resources/js/Pages/Clerk/Announcements/Index.vue`
4. `app/Http/Controllers/Coordinator/ProjectJobController.php`
5. `resources/js/Pages/Coordinator/ProjectJobs/Index.vue`
6. `app/Http/Controllers/ProjectJobs/JobBoxController.php`（global + user 両メソッド）
7. `resources/js/Pages/Coordinator/JobBox/Index.vue`
8. `resources/js/Pages/JobBox/Index.vue`
