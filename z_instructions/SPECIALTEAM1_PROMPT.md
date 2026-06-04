# SPECIALTEAM1_PROMPT.md — 新セッション開始用プロンプト

## 機能概要
Admin が複数会社のメンバーを登録できる「特別チーム」（team_type='special'）。
Admin タブメニューに「特別チーム」を追加し、会社→部署絞り込みでメンバーを選択できる。

## 設計サマリー
- DB: マイグレーション不要。teams テーブルの team_type='special' を使用
- リーダー: Admin の所属会社のメンバー（全ロール可）
- メンバー: 全登録会社から選択可（会社→部署フィルター付き）
- 権限制御: team_management パーミッション
- ユーザーへの影響: team_user 登録のみ → TeamSwitcher は変更不要

## 関連ファイル
- `app/Http/Controllers/Admin/SpecialTeamController.php`
- `routes/web.php`（admin グループ）
- `resources/js/ziggy.js`
- `resources/js/Components/Tabs/AdminNavigationTabs.vue`
- `resources/js/Pages/Admin/SpecialTeams/Index.vue`
- `resources/js/Pages/Admin/SpecialTeams/Create.vue`
- `resources/js/Pages/Admin/SpecialTeams/Edit.vue`

## 参照ドキュメント
- `z_instructions/SPECIALTEAM_PLAN1.md`
- `z_instructions/SPECIALTEAM_MANAGER1.md`
