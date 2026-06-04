# SPECIALTEAM_PLAN1.md — 会社横断特別チーム機能 設計書

## 概要
Admin が複数会社のメンバーを登録できる「特別チーム」を新設する。
既存の `teams` テーブルを流用し、`team_type = 'special'` で識別する。

## DB 設計
- マイグレーション不要（既存テーブルを使用）
- `teams.team_type = 'special'`
- `teams.company_id` = Admin の所属会社（チームの管理会社）
- `teams.department_id` = NULL（会社横断のため部署不要）
- `team_user` テーブルに各メンバーを登録（既存通り）

## 変更ファイル一覧

| # | ファイル | 種別 | 内容 |
|---|---|---|---|
| 1 | `app/Http/Controllers/Admin/SpecialTeamController.php` | 新規 | index/create/store/edit/update/destroy |
| 2 | `routes/web.php` | 変更 | admin グループに special-teams リソースルート追加 |
| 3 | `resources/js/ziggy.js` | 再生成 | Ziggy ルート更新 |
| 4 | `resources/js/Components/Tabs/AdminNavigationTabs.vue` | 変更 | 「特別チーム」タブ追加（team_management 権限） |
| 5 | `resources/js/Pages/Admin/SpecialTeams/Index.vue` | 新規 | 一覧ページ |
| 6 | `resources/js/Pages/Admin/SpecialTeams/Create.vue` | 新規 | 作成フォーム（会社→部署絞り込み） |
| 7 | `resources/js/Pages/Admin/SpecialTeams/Edit.vue` | 新規 | 編集フォーム |

## 機能仕様

### 作成フォーム
- チーム名（必須）
- 説明（任意）
- リーダー: Admin の所属会社のメンバー（全ロール可）
- メンバー選択:
  - 会社セレクト → 部署セレクト（「すべて」選択可）→ 自動絞り込み
  - チェックボックスで複数選択
  - 選択済みメンバー一覧表示
- 日報閲覧: チェックボックス（デフォルト OFF）

### 権限
- `team_management` パーミッションで制御（既存 Admin 権限と同じキー）

### ユーザーへの影響
- `team_user` テーブルに登録されたメンバーはチームメニューに自動表示（変更なし）
- TeamSwitcher は変更不要

## 注意事項
- `team_type = 'special'` は DiaryInteractionController の `buildPermittedUserIds()` に影響しない（`can_read_diary` フラグで制御）
- `routes/web.php` を変更するため Ziggy 再生成が必須
