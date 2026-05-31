# CONTEXT_MANAGER1 — 進捗管理

## 作業ログ

| 日時 | 内容 |
|------|------|
| 2026-05-31 | 設計・計画作成、ユーザー確認取得（オプションA: グローバルモード時は「会社を選択してください」表示） |

---

## 進捗一覧

| # | 作業内容 | ファイル | 状態 |
|---|---------|---------|------|
| 1 | SuperAdminGlobalGuard.vue 新規作成 | `resources/js/Components/SuperAdminGlobalGuard.vue` | 未着手 |
| 2 | Clerk AnnouncementController::index() 修正 | `app/Http/Controllers/Clerk/AnnouncementController.php` | 未着手 |
| 3 | Clerk Announcements/Index.vue 修正 | `resources/js/Pages/Clerk/Announcements/Index.vue` | 未着手 |
| 4 | Coordinator ProjectJobController::index() 修正 | `app/Http/Controllers/Coordinator/ProjectJobController.php` | 未着手 |
| 5 | Coordinator ProjectJobs/Index.vue 修正 | `resources/js/Pages/Coordinator/ProjectJobs/Index.vue` | 未着手 |
| 6 | JobBoxController::global() 修正 | `app/Http/Controllers/ProjectJobs/JobBoxController.php` | 未着手 |
| 7 | Coordinator/JobBox/Index.vue 修正 | `resources/js/Pages/Coordinator/JobBox/Index.vue` | 未着手 |
| 8 | JobBoxController::user() 修正 | `app/Http/Controllers/ProjectJobs/JobBoxController.php` | 未着手 |
| 9 | JobBox/Index.vue 修正 | `resources/js/Pages/JobBox/Index.vue` | 未着手 |
| 10 | npm run build | — | 未着手 |

---

## 作業フロー

```
[Guard コンポーネント作成]
  → [Clerk 通知修正]
  → [Coordinator プロジェクト一覧修正]
  → [JobBox::global() 修正]
  → [JobBox::user() 修正]
  → [ビルド・確認]
```

---

## 注意事項

- JobBoxController.php は 1700 行超の大ファイル。global() と user() 両方を同ファイルで修正
- `user()` メソッドは `project_jobs` join がないため join 追加が必要
- 一般ユーザーの挙動には一切影響しない（SuperAdmin ガード内のみ修正）
- isGlobalMode は全ページで必ず渡す（props 宣言忘れに注意）
