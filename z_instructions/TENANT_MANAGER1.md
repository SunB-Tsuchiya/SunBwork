# TENANT_MANAGER1.md — マルチテナント情報隔離修正 進捗管理

## 作業ステータス

| Phase | タスク | 状態 |
|---|---|---|
| 1 | migration 作成（company_id to project_jobs） | ✅ 完了 |
| 1 | ProjectJob モデル修正 | ✅ 完了 |
| 2 | Admin/ProjectJobController.index() 修正 | ✅ 完了 |
| 2 | Admin/ProjectJobController.show() 修正 | ✅ 完了 |
| 2 | Admin/TeamController.index() 修正 | ✅ 完了 |
| 3 | Coordinator/ProjectJobController.store()/clone()/storeFromTemplate()/share() 修正 | ✅ 完了 |
| 3 | Coordinator/ProgressReportController.index() 修正 | ✅ 完了 |
| 4 | Leader/ProjectJobController.index() 空チームバグ修正 + company_id guard | ✅ 完了 |
| 4 | Leader/ProjectJobController.show() company_id guard | ✅ 完了 |
| 5 | routes/web.php proof ルート保護 | ✅ 完了 |
| 6 | UserNavigationTabs.vue 校正タブ条件表示 | ✅ 完了 |
| - | migrate 実行（ローカル） | ✅ 完了 |
| - | npm run build | ✅ 完了 |
| - | ChangelogSeeder 追記（version: tenant-1） | ✅ 完了 |
| - | 本番 migrate 実行 | ⬜ 未実施（デプロイ時） |

## 作業ログ

### 2026-05-30
- 設計ドキュメント作成（TENANT_PLAN1.md / TENANT_MANAGER1.md / TENANT1_PROMPT.md）
- 本番DB調査: 全89件サン・ブレーン、サンエー印刷チームなし・ユーザー2名のみを確認
- department_id 追加は不要と判断（prepress board は別テーブル、作業担当レベルはassignmentsに既存）
- Leader/ProjectJobController の空チーム全件漏洩バグを発見（確認: Laravel空クロージャでWHERE句なし）
- 全9ファイル修正 + migration + build + ChangelogSeeder 完了

## チェックポイント

- [ ] サンエー印刷Adminでログインし、案件総覧にサン・ブレーンの案件が表示されないこと
- [ ] サンエー印刷AdminでTeam管理ページにサン・ブレーンのチームが表示されないこと
- [ ] 進行レポートでサン・ブレーンのProgressCellが表示されないこと
- [ ] サンエー印刷Userのナビタブに「校正状況」が表示されないこと
- [ ] `/user/proof/status` に直接アクセスすると403になること（サンエー印刷ユーザー）
- [ ] サン・ブレーンのAdmin・Coordinatorは従来通り動作すること
- [ ] SuperAdmin はコンテキスト切り替えで各社データを参照できること
