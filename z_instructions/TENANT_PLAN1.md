# TENANT_PLAN1.md — マルチテナント情報隔離修正 詳細仕様

## 背景・目的

サンエー印刷（`company_type=general`）を新規追加したところ、そのAdmin・Coordinatorが
サン・ブレーン（`company_type=sunbrain`）の案件・部署・クライアント・進行レポートを
参照できる状態になっている。守秘義務上の問題を解消し、会社ごとに情報を完全隔離する。

校正機能（proof系）はサン・ブレーン専用機能のため、他社ユーザーのUIからも非表示にする。

---

## 根本原因の分析

| 問題箇所 | 原因 |
|---|---|
| `Admin/ProjectJobController.index()` | `Team::where('team_type','department')` に `company_id` フィルタなし → 全社の部署を返す |
| `Admin/ProjectJobController.show()` | 任意の案件IDへのアクセスを会社チェックなしで許可 |
| `Admin/TeamController.index()` | `Team::get()` で全チームを返す |
| `Coordinator/ProgressReportController.index()` | isAdmin/isClerk 時に全 ProgressCell を返す |
| `Coordinator/ProjectJobController.store()/clone()` | 新規案件に `company_id` をセットしない |
| User proof ルート | `company_type:sunbrain` ミドルウェアなし |
| `UserNavigationTabs.vue` | `校正状況` タブを全社に表示 |

---

## DB設計

### project_jobs テーブルへの company_id 追加

```sql
ALTER TABLE project_jobs ADD COLUMN company_id BIGINT UNSIGNED NULL AFTER client_id;
ALTER TABLE project_jobs ADD CONSTRAINT fk_project_jobs_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL;
ALTER TABLE project_jobs ADD INDEX idx_project_jobs_company_id (company_id);

-- バックフィル: client 経由で company_id を補完
UPDATE project_jobs pj
JOIN clients c ON pj.client_id = c.id
SET pj.company_id = c.company_id
WHERE pj.company_id IS NULL;
```

---

## フェーズ別タスク

### Phase 1: Migration + Model
- [ ] `database/migrations/YYYYMMDD_add_company_id_to_project_jobs.php` を新規作成
  - `company_id` nullable FK 追加
  - バックフィル SQL（`client.company_id` 経由）
- [ ] `app/Models/ProjectJob.php`
  - `company_id` を `$fillable` に追加
  - `company()` BelongsTo リレーション追加
  - `scopeForCompany($query, $companyId)` スコープ追加

### Phase 2: Admin コントローラー
- [ ] `app/Http/Controllers/Admin/ProjectJobController.php`
  - `ResolvesContextCompany` trait を use
  - `index()`: departments を `company_id` でフィルタ、jobs を `scopeForCompany()` でフィルタ
  - `show()`: job の `company_id` がコンテキスト会社と一致しなければ 403
- [ ] `app/Http/Controllers/Admin/TeamController.php`
  - `index()`: teams を `company_id` でフィルタ

### Phase 3: Coordinator コントローラー
- [ ] `app/Http/Controllers/Coordinator/ProjectJobController.php`
  - `store()`: `'company_id' => $user->company_id` を ProjectJob::create に追加
  - `clone()`: `'company_id' => $projectJob->company_id` をコピー
- [ ] `app/Http/Controllers/Coordinator/ProgressReportController.php`
  - isAdmin/isClerk 時も `company_id` でスコープ（SuperAdmin は全社参照を維持）

### Phase 4: Leader コントローラー
- [ ] `app/Http/Controllers/Leader/ProjectJobController.php`
  - `index()`: **チームなし時（deptMemberIds・unitMemberIds が両方空）は早期 return で 0 件を返す** ← 重大バグ修正
  - `index()` / `show()`: `where('company_id', $user->company_id)` を追加（belt-and-suspenders）
  - `show()`: `canAccess` 計算前に `$projectJob->company_id !== $user->company_id` なら 403

### Phase 5: ルート
- [ ] `routes/web.php`
  - user proof 系ルート 5本を `company_type:sunbrain` ミドルウェアグループで囲む

### Phase 6: フロントエンド
- [ ] `resources/js/Components/Tabs/UserNavigationTabs.vue`
  - `usePage()` で `auth.companyType` を取得し、`=== 'sunbrain'` の場合のみ `校正状況` タブを表示

---

## 変更ファイル一覧（9本）

| # | ファイル | 種別 |
|---|---|---|
| 1 | `database/migrations/YYYYMMDD_add_company_id_to_project_jobs.php` | 新規 |
| 2 | `app/Models/ProjectJob.php` | 修正 |
| 3 | `app/Http/Controllers/Admin/ProjectJobController.php` | 修正 |
| 4 | `app/Http/Controllers/Admin/TeamController.php` | 修正 |
| 5 | `app/Http/Controllers/Coordinator/ProjectJobController.php` | 修正 |
| 6 | `app/Http/Controllers/Coordinator/ProgressReportController.php` | 修正 |
| 7 | `app/Http/Controllers/Leader/ProjectJobController.php` | 修正 |
| 8 | `routes/web.php` | 修正 |
| 9 | `resources/js/Components/Tabs/UserNavigationTabs.vue` | 修正 |

---

## 追加判明した重大バグ

`Leader/ProjectJobController.index()` の where クロージャ:
```php
->where(function ($sub) use ($deptMemberIds, $unitMemberIds) {
    if (!empty($deptMemberIds)) { ... }
    if (!empty($unitMemberIds)) { ... }
})
```
両配列が空のとき Laravel は WHERE 句を生成しない → `SELECT * FROM project_jobs`（全件）。
チームに所属していない Leader（サンエー印刷の小島さんなど）が **全社全案件を見られる状態**。

---

## 設計上の注意事項

- `project_jobs.company_id` は nullable。既存レコードのうち `client_id=NULL` のものは NULL のまま
- SuperAdmin はコンテキスト切り替えに応じて絞り込む（`contextCompanyId()` が NULL のときは全社参照を維持）
- `Admin/TeamController.index()` で `company_id = NULL` の場合（SuperAdmin グローバルモード）は全チームを返す
- `ProgressReportController` の SuperAdmin は全社参照を維持（`contextCompanyId() = NULL` 時はフィルタなし）
- User proof ルートを `company_type:sunbrain` で保護する際、Ziggy が `route('user.proof.status')` を生成できる必要がある → ルートは削除せずミドルウェアを追加するだけでよい
- `UserNavigationTabs.vue` は `usePage().props.auth.companyType` で判定
