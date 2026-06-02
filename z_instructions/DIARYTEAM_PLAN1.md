# DIARYTEAM_PLAN1.md — 日報権限チーム 詳細設計

作成日: 2026-06-02  
対象機能: 日報権限チーム（DiaryTeam）CRUD + 日報マネージャー閲覧機能

---

## 1. 背景・要件

### 背景
現在、日報は Leader（部署リーダー・副リーダー・チームリーダー）と Admin しか閲覧できない。  
小規模チームで Leader 権限を持たない役職（Clerk / Coordinator / ProofCoordinator）が  
日報を閲覧したいというニーズがある。

### 要件まとめ
| 項目 | 内容 |
|------|------|
| Admin 機能 | 「日報権限管理」ボタン → DiaryTeam CRUD |
| DiaryTeam | 名前・説明・リーダー（複数可）・メンバー（複数可）を管理 |
| リーダー候補 | `clerk` / `coordinator` / `proof_coordinator` のみ |
| 日報マネージャー表示 | リーダーに選ばれたユーザーに「日報管理」ボタン出現 |
| 閲覧範囲 | 自分が担当する DiaryTeam のメンバーの日報のみ |
| ルート分離 | admin / leader とは別の `diary-manager` プレフィックスで独立 |
| 複数チーム | 1人が複数チームのリーダーでも良い（全チームのメンバー分を閲覧可） |

---

## 2. DB 設計

### 2.1 新規テーブル

#### `diary_teams`
| カラム | 型 | 説明 |
|--------|-----|------|
| id | bigint PK | |
| company_id | bigint FK→companies | |
| name | varchar(255) | チーム名 |
| description | text nullable | 説明 |
| created_at | timestamp | |
| updated_at | timestamp | |

- Index: `company_id`

#### `diary_team_leaders`（pivot）
| カラム | 型 | 説明 |
|--------|-----|------|
| id | bigint PK | |
| diary_team_id | bigint FK→diary_teams | |
| user_id | bigint FK→users | clerk/coordinator/proof_coordinator のみ |
| created_at | timestamp | |
| updated_at | timestamp | |

- Unique: `[diary_team_id, user_id]`

#### `diary_team_members`（pivot）
| カラム | 型 | 説明 |
|--------|-----|------|
| id | bigint PK | |
| diary_team_id | bigint FK→diary_teams | |
| user_id | bigint FK→users | 閲覧対象ユーザー（全ロール可） |
| created_at | timestamp | |
| updated_at | timestamp | |

- Unique: `[diary_team_id, user_id]`

### 2.2 既存テーブルへの変更
なし（LeaderPermission / AdminPermission への追加は不要）

---

## 3. モデル設計

### `app/Models/DiaryTeam.php`
```php
fillable: ['company_id', 'name', 'description']

relations:
  company()       → BelongsTo(Company)
  leaders()       → BelongsToMany(User, 'diary_team_leaders')
  members()       → BelongsToMany(User, 'diary_team_members')
```

### `app/Models/User.php` への追加
```php
// 日報マネージャーかどうか
public function isDiaryManager(): bool
{
    return DB::table('diary_team_leaders')->where('user_id', $this->id)->exists();
}

// 自分がリーダーの DiaryTeam のメンバー ID 一覧
public function diaryManagerMemberIds(): array
{
    $teamIds = DB::table('diary_team_leaders')
        ->where('user_id', $this->id)
        ->pluck('diary_team_id');
    return DB::table('diary_team_members')
        ->whereIn('diary_team_id', $teamIds)
        ->pluck('user_id')
        ->unique()
        ->values()
        ->toArray();
}
```

---

## 4. コントローラー設計

### 4.1 Admin CRUD: `app/Http/Controllers/Admin/DiaryTeamController.php`

| メソッド | ルート | 機能 |
|---------|--------|------|
| index | GET admin/diary-teams | DiaryTeam 一覧 |
| create | GET admin/diary-teams/create | 作成フォーム |
| store | POST admin/diary-teams | 作成処理 |
| edit | GET admin/diary-teams/{id}/edit | 編集フォーム |
| update | PUT admin/diary-teams/{id} | 更新処理 |
| destroy | DELETE admin/diary-teams/{id} | 削除処理 |

権限チェック: `$this->requireAdminPermission('diary_management')`  
リーダー候補: `user_role IN ('clerk','coordinator','proof_coordinator')` かつ同 company_id  
メンバー候補: 同 company_id の全アクティブユーザー

### 4.2 DiaryManager 閲覧: `app/Http/Controllers/DiaryManager/DiaryInteractionController.php`

既存の `Diaries\DiaryInteractionController` をベースに以下を変更:
- `buildPermittedUserIds()` → `$currentUser->diaryManagerMemberIds()` を使う
- `requireAdminPermission` / `requireLeaderPermission` の代わりに Middleware で保護
- `routePrefix` → `'diary_manager'` を渡す

| メソッド | ルート | 機能 |
|---------|--------|------|
| index | GET diary-manager/diaryinteractions | 担当チームの日報一覧 |
| show | GET diary-manager/diaryinteractions/{diary} | 日報詳細 |
| markRead | POST diary-manager/diaryinteractions/{diary}/mark-read | 既読 |
| markReadAll | POST diary-manager/diaryinteractions/mark-read-all | 一括既読 |

---

## 5. Middleware

### `app/Http/Middleware/DiaryManagerMiddleware.php`
```php
// diary_team_leaders テーブルに user_id が存在すればアクセス許可
// なければ 403 or redirect
```

`bootstrap/app.php` に `'diary_manager' => DiaryManagerMiddleware::class` を追加

---

## 6. ルート設計

**ファイル: `routes/web.php`**

```php
// Admin: DiaryTeam CRUD
Route::prefix('admin')->name('admin.')->middleware(['auth:sanctum', 'admin'])->group(function () {
    // ... 既存 ...
    Route::resource('diary-teams', Admin\DiaryTeamController::class)
        ->names([
            'index'   => 'diary_teams.index',
            'create'  => 'diary_teams.create',
            'store'   => 'diary_teams.store',
            'edit'    => 'diary_teams.edit',
            'update'  => 'diary_teams.update',
            'destroy' => 'diary_teams.destroy',
        ]);
});

// DiaryManager: 日報閲覧（clerk/coordinator/proof_coordinator 専用）
Route::prefix('diary-manager')->name('diary_manager.')->middleware(['auth:sanctum', 'diary_manager'])->group(function () {
    Route::get('diaryinteractions', [DiaryManager\DiaryInteractionController::class, 'index'])
        ->name('diaryinteractions.index');
    Route::get('diaryinteractions/{diary}', [DiaryManager\DiaryInteractionController::class, 'show'])
        ->name('diaryinteractions.show');
    Route::post('diaryinteractions/{diary}/mark-read', [DiaryManager\DiaryInteractionController::class, 'markRead'])
        ->name('diaryinteractions.mark_read');
    Route::post('diaryinteractions/mark-read-all', [DiaryManager\DiaryInteractionController::class, 'markReadAll'])
        ->name('diaryinteractions.mark_read_all');
});
```

---

## 7. Vue ページ設計

### Admin 側（CRUD）

#### `resources/js/Pages/Admin/DiaryTeams/Index.vue`
- DiaryTeam の一覧テーブル（名前/リーダー人数/メンバー人数/操作）
- 「新規作成」ボタン → Create.vue

#### `resources/js/Pages/Admin/DiaryTeams/Create.vue`
- フォーム: チーム名 / 説明 / リーダー選択（MultiSelect: clerk/coordinator/proof_coordinator）/ メンバー選択（MultiSelect）

#### `resources/js/Pages/Admin/DiaryTeams/Edit.vue`
- Create.vue と同構成、既存データプリフィル

### DiaryManager 側（閲覧）

#### `resources/js/Pages/DiaryManager/Interactions/Index.vue`
- 既存 `Diaries/Interactions/Index.vue` とほぼ同構成
- ルートプレフィックスが `diary_manager` に変わる点のみ差異

#### `resources/js/Pages/DiaryManager/Interactions/Show.vue`
- 既存 `Diaries/Interactions/Show.vue` とほぼ同構成
- ルートプレフィックスが `diary_manager` に変わる点のみ差異

---

## 8. ナビゲーション

### Admin ナビ
**ファイル:** `resources/js/Components/Tabs/AdminTabs.vue`（または相当するナビコンポーネント）  
「日報権限管理」ボタンを Admin の管理メニューに追加。  
`adminPermission.diary_management` が true の場合に表示。

### DiaryManager ナビ
**ファイル:** `resources/js/layouts/AppLayout.vue` の nav 部分  
`auth.user.is_diary_manager`（または Inertia の shared data）が true のときに  
「日報管理」ボタンを表示する。

**Inertia shared data への追加（`HandleInertiaRequests.php`）:**
```php
'auth' => [
    'user' => [...],
    'is_diary_manager' => $user?->isDiaryManager() ?? false,
]
```

---

## 9. セキュリティ・バリデーション

- リーダー候補は同 company_id かつ `user_role IN ('clerk','coordinator','proof_coordinator')` のみ受け入れる
- メンバー候補は同 company_id のみ受け入れる
- DiaryManager が show/markRead を呼ぶとき `buildPermittedUserIds()` で範囲外なら 403
- Admin CRUD は `requireAdminPermission('diary_management')` で保護

---

## 10. 変更ファイル一覧

### 新規作成
| ファイル | 役割 |
|---------|------|
| `database/migrations/xxxx_create_diary_teams_table.php` | diary_teams テーブル |
| `database/migrations/xxxx_create_diary_team_leaders_table.php` | diary_team_leaders pivot |
| `database/migrations/xxxx_create_diary_team_members_table.php` | diary_team_members pivot |
| `app/Models/DiaryTeam.php` | DiaryTeam モデル |
| `app/Http/Middleware/DiaryManagerMiddleware.php` | 日報マネージャー Middleware |
| `app/Http/Controllers/Admin/DiaryTeamController.php` | Admin CRUD |
| `app/Http/Controllers/DiaryManager/DiaryInteractionController.php` | DiaryManager 閲覧 |
| `resources/js/Pages/Admin/DiaryTeams/Index.vue` | 一覧 |
| `resources/js/Pages/Admin/DiaryTeams/Create.vue` | 作成フォーム |
| `resources/js/Pages/Admin/DiaryTeams/Edit.vue` | 編集フォーム |
| `resources/js/Pages/DiaryManager/Interactions/Index.vue` | 日報一覧 |
| `resources/js/Pages/DiaryManager/Interactions/Show.vue` | 日報詳細 |

### 既存ファイル変更
| ファイル | 変更内容 |
|---------|---------|
| `app/Models/User.php` | `isDiaryManager()` / `diaryManagerMemberIds()` 追加 |
| `app/Http/Middleware/Kernel.php` or `bootstrap/app.php` | `diary_manager` Middleware 登録 |
| `routes/web.php` | Admin DiaryTeam ルート / diary-manager ルート追加 |
| `app/Http/Middleware/HandleInertiaRequests.php` | `is_diary_manager` shared data 追加 |
| `resources/js/layouts/AppLayout.vue` | 「日報管理」ナビボタン追加 |
| Admin ナビ関連 Vue | 「日報権限管理」ボタン追加 |

---

## 11. フェーズ別タスク

### Phase 1: DB + モデル
- [ ] 3つのマイグレーション作成
- [ ] `DiaryTeam` モデル作成
- [ ] `User` モデルにメソッド追加

### Phase 2: Middleware + ルート
- [ ] `DiaryManagerMiddleware` 作成・登録
- [ ] `routes/web.php` に2つのルートグループ追加

### Phase 3: Admin CRUD
- [ ] `Admin/DiaryTeamController.php` 作成
- [ ] `Admin/DiaryTeams/Index.vue` 作成
- [ ] `Admin/DiaryTeams/Create.vue` 作成
- [ ] `Admin/DiaryTeams/Edit.vue` 作成

### Phase 4: DiaryManager 閲覧
- [ ] `DiaryManager/DiaryInteractionController.php` 作成
- [ ] `DiaryManager/Interactions/Index.vue` 作成
- [ ] `DiaryManager/Interactions/Show.vue` 作成

### Phase 5: ナビゲーション統合
- [ ] `HandleInertiaRequests.php` に `is_diary_manager` 追加
- [ ] `AppLayout.vue` に「日報管理」ボタン追加
- [ ] Admin ナビに「日報権限管理」ボタン追加

### Phase 6: ビルド・確認
- [ ] `npm run build`
- [ ] `php artisan migrate`
- [ ] 動作確認

---

## 12. 未決定事項（要確認）

1. ~~**Admin ナビの「日報権限管理」ボタン** — Admin メニューの既存 diary_management 権限と統合するか、別権限にするか~~ → **確定: 既存 `adminPermission.diary_management` で制御**（2026-06-02 確認）
2. ~~**DiaryManager の日報**に対してコメント・既読の操作は Leader 相当で許可するか~~ → **確定: コメント・既読操作ともに可能（Leader と同等）**（2026-06-02 確認）
3. ~~**複数会社（SuperAdmin）**対応 — 今回は同 company_id 内のみで充分か~~ → **確定: SuperAdmin 対応を含める**（2026-06-02 確認）

### SuperAdmin 対応仕様
- Admin CRUD（diary-teams）: SuperAdmin はコンテキスト会社の DiaryTeam を管理できる
- DiaryManager 閲覧側は SuperAdmin が diary-manager Middleware を通ることはない（SuperAdmin は admin ルートを使うため対応不要）
- Admin CRUD コントローラーで `$this->contextCompanyId()` を使い、SuperAdmin 時はコンテキスト会社の diary_teams / ユーザー候補を絞る

---

*このファイルは実装開始前にユーザーの承認が必要*
