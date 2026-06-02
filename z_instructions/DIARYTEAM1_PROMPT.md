# DIARYTEAM1_PROMPT.md — 新セッション開始用プロンプト

## このプロンプトのコピー＆ペースト用テキスト

---

日報権限チーム（DiaryTeam）機能の実装を続けます。

## 機能概要

Admin が「日報権限チーム」を CRUD 管理できるようにし、  
Clerk / Coordinator / ProofCoordinator をチームのリーダーに任命して、  
そのリーダーが担当チームメンバーの日報のみ閲覧できる機能を追加します。  
Leader / Admin の日報ルートとは分離した `diary-manager` プレフィックスで独立したルートを持ちます。

## 設計ドキュメント

- 詳細仕様: `z_instructions/DIARYTEAM_PLAN1.md`
- 進捗管理: `z_instructions/DIARYTEAM_MANAGER1.md`

## 現在の進捗

DIARYTEAM_MANAGER1.md の進捗テーブルを参照してください。  
現在 Phase X まで完了しています。

## 実装のポイント

### DB 構造
- `diary_teams` テーブル: id / company_id / name / description
- `diary_team_leaders` pivot: diary_team_id / user_id（clerk/coordinator/proof_coordinator のみ）
- `diary_team_members` pivot: diary_team_id / user_id（閲覧対象）

### 権限フロー
1. Admin → `admin/diary-teams` で CRUD
2. Clerk 等がリーダーに設定される → `diary_team_leaders` にレコード作成
3. `HandleInertiaRequests` で `auth.is_diary_manager = true` を shared data に流す
4. AppLayout でナビボタン「日報管理」を表示
5. `diary-manager/diaryinteractions` にアクセス → `DiaryManagerMiddleware` で保護
6. `DiaryManager/DiaryInteractionController` が `diaryManagerMemberIds()` で範囲を絞って日報表示

### 既存コードとの関係
- `Diaries/DiaryInteractionController.php` は変更しない（Admin/Leader 用を維持）
- `DiaryManager/DiaryInteractionController.php` を新規作成（buildPermittedUserIds を差し替え）
- `User::isDiaryManager()` / `diaryManagerMemberIds()` を User モデルに追加

### CLAUDE.md ルール（必須）
1. Vue/JS 変更後は `npm run build`（プロジェクトルートで実行）
2. Artisan は `docker compose exec laravel bash -lc "php artisan ..."`
3. 新規ページは AppLayout を使用する
4. ルートは `routes/web.php`（api.php には置かない）

## 次にすべきこと

DIARYTEAM_MANAGER1.md の最初の未着手タスクから実装を始めてください。  
各 Phase 完了後はユーザーに確認を取ってから次の Phase に進んでください。

---
