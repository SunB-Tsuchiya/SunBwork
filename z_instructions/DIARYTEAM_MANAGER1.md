# DIARYTEAM_MANAGER1.md — 日報権限チーム 進捗管理

作成日: 2026-06-02  
対応 PLAN: DIARYTEAM_PLAN1.md

---

## 進捗サマリー

| フェーズ | 内容 | 状態 |
|---------|------|------|
| Phase 1 | DB + モデル | ✅ 完了 |
| Phase 2 | Middleware + ルート | ✅ 完了 |
| Phase 3 | Admin CRUD | ✅ 完了 |
| Phase 4 | DiaryManager 閲覧 | ✅ 完了 |
| Phase 5 | ナビゲーション統合 | ✅ 完了 |
| Phase 6 | ビルド・確認 | ✅ ビルド成功（2026-06-02）|

---

## 詳細タスク一覧

### Phase 1: DB + モデル
| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| 1-1 | `diary_teams` マイグレーション作成 | ⬜ | company_id, name, description |
| 1-2 | `diary_team_leaders` マイグレーション作成 | ⬜ | pivot, unique[team_id, user_id] |
| 1-3 | `diary_team_members` マイグレーション作成 | ⬜ | pivot, unique[team_id, user_id] |
| 1-4 | `app/Models/DiaryTeam.php` 作成 | ⬜ | leaders(), members() relations |
| 1-5 | `User::isDiaryManager()` 追加 | ⬜ | |
| 1-6 | `User::diaryManagerMemberIds()` 追加 | ⬜ | |
| 1-7 | `php artisan migrate` 実行 | ⬜ | |

### Phase 2: Middleware + ルート
| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| 2-1 | `DiaryManagerMiddleware.php` 作成 | ⬜ | diary_team_leaders に存在するか確認 |
| 2-2 | Middleware 登録（bootstrap/app.php） | ⬜ | `'diary_manager'` エイリアス |
| 2-3 | Admin DiaryTeam ルート追加（routes/web.php） | ⬜ | resource route |
| 2-4 | diary-manager ルート追加（routes/web.php） | ⬜ | 4メソッド |

### Phase 3: Admin CRUD
| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| 3-1 | `Admin/DiaryTeamController.php` 作成 | ⬜ | index/create/store/edit/update/destroy |
| 3-2 | `Admin/DiaryTeams/Index.vue` 作成 | ⬜ | 一覧テーブル |
| 3-3 | `Admin/DiaryTeams/Create.vue` 作成 | ⬜ | フォーム（リーダー/メンバー MultiSelect） |
| 3-4 | `Admin/DiaryTeams/Edit.vue` 作成 | ⬜ | フォーム（プリフィル） |

### Phase 4: DiaryManager 閲覧
| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| 4-1 | `DiaryManager/DiaryInteractionController.php` 作成 | ⬜ | buildPermittedUserIds を差し替え |
| 4-2 | `DiaryManager/Interactions/Index.vue` 作成 | ⬜ | routePrefix='diary_manager' |
| 4-3 | `DiaryManager/Interactions/Show.vue` 作成 | ⬜ | routePrefix='diary_manager' |

### Phase 5: ナビゲーション統合
| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| 5-1 | `HandleInertiaRequests.php` に `is_diary_manager` 追加 | ⬜ | |
| 5-2 | `AppLayout.vue` に「日報管理」ボタン追加 | ⬜ | `auth.is_diary_manager` で制御 |
| 5-3 | Admin ナビに「日報権限管理」ボタン追加 | ⬜ | diary_management 権限で制御 |

### Phase 6: ビルド・確認
| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| 6-1 | `npm run build` | ⬜ | |
| 6-2 | 動作確認: Admin DiaryTeam CRUD | ⬜ | |
| 6-3 | 動作確認: DiaryManager 日報閲覧 | ⬜ | |
| 6-4 | 動作確認: ナビボタン表示/非表示 | ⬜ | |

---

## 作業ログ

| 日付 | 内容 |
|------|------|
| 2026-06-02 | PLAN / MANAGER / PROMPT ファイル作成、ユーザー承認取得 |
| 2026-06-02 | Phase 1-6 全実装完了・ビルド成功 |

---

## ステータス凡例
- ⬜ 未着手
- 🔄 作業中
- ✅ 完了
- ❌ ブロック中

---

## 注意事項・決定事項ログ

- `proof_coordinator` = `proof_coordinator`（User.user_role の値）。`proof-coordinator` とハイフン表記はしない
- Admin の「日報権限管理」表示制御は既存 `adminPermission.diary_management` を使う（新フラグ不要）→ 2026-06-02 確認済み
- SuperAdmin 対応を含める。Admin DiaryTeamController で `contextCompanyId()` を使い、ユーザー候補を絞る → 2026-06-02 確認済み
- SuperAdmin は diary-manager ルートを使わない（admin ルートで管理するため DiaryManagerMiddleware への対応不要）
- DiaryManager はコメント・既読操作ともに可能（Leader と同等）→ 2026-06-02 確認済み
- Admin の日報権限管理は既存 `adminPermission.diary_management` で制御する（新フラグ不要）
