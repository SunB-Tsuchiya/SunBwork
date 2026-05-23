# PJOB_MANAGER1.md — 進捗管理

## 進捗テーブル

| Phase | タスク | ステータス |
|---|---|---|
| 1 | DB Migration 作成 | ✅ 完了 |
| 1 | `php artisan migrate` 実行 | ✅ 完了 |
| 2 | `ProjectJob` model fillable/casts 更新 | ✅ 完了 |
| 2 | `ProjectJobController::create()` — salesReps prop 追加 | ✅ 完了 |
| 2 | `ProjectJobController::store()` — 新フィールドバリデーション追加 | ✅ 完了 |
| 2 | `Coordinator/ProjectJobs/Create.vue` フォーム改修 | ✅ 完了 |
| 3 | `Coordinator/SalesRepController::apiList()` 追加 | ✅ 完了 |
| 3 | `Coordinator/ProjectJobCsvController.php` 新規作成 | ✅ 完了 |
| 3 | `routes/web.php` coordinator CSV ルート追加 | ✅ 完了 |
| 3 | `Coordinator/ProjectJobs/Index.vue` CSV ボタン + モーダル追加 | ✅ 完了 |
| 4 | `Prepress/TicketController::downloadSample()` 追加 | ✅ 完了 |
| 4 | `routes/web.php` prepress sample ルート追加 | ✅ 完了 |
| 4 | `Prepress/Tickets/Index.vue` サンプルダウンロードボタン追加 | ✅ 完了 |
| 全体 | `npm run build` | ✅ 完了 |

## 作業ログ
- 2026-05-23: 設計ドキュメント作成、ユーザー確認後に全フェーズ実装完了
