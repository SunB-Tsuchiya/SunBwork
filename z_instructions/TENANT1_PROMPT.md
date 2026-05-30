# TENANT1_PROMPT.md — 新セッション開始用プロンプト

## タスク概要

マルチテナント情報隔離修正（`TENANT_PLAN1.md` 参照）の実装を継続してください。

## 背景

サンエー印刷（`company_type=general`）を追加した後、そのAdminログイン時にサン・ブレーンの
案件・部署・進行レポートが見えてしまう問題を修正する。

`project_jobs` テーブルに `company_id` カラムを追加し、各コントローラーで会社スコープを
適切に適用する。また、校正機能（proof系）をサン・ブレーン専用に制限する。

## 設計ドキュメント

- `z_instructions/TENANT_PLAN1.md` — 詳細仕様・変更ファイル一覧
- `z_instructions/TENANT_MANAGER1.md` — 進捗管理

## 重要な設計判断

- `project_jobs.company_id` は nullable FK（`companies.id`）
- バックフィル: `UPDATE project_jobs pj JOIN clients c ON pj.client_id = c.id SET pj.company_id = c.company_id`
- `ResolvesContextCompany::contextCompanyId()` を使って会社フィルタを統一
- SuperAdmin はコンテキスト切り替えに応じて絞り込む（NULL = グローバルモードで全社参照）
- User proof ルート5本を `company_type:sunbrain` ミドルウェアで保護
- `UserNavigationTabs.vue` で `auth.companyType === 'sunbrain'` の場合のみ校正タブ表示

## 進捗

TENANT_MANAGER1.md の進捗テーブルを確認し、未着手のタスクから着手してください。
実装完了後は `npm run build` および `php artisan migrate` を実行してください。
