# ANNEX_MANAGER1.md — 通知機能拡張 進捗管理 v1

作成日: 2026-05-30

## ステータス凡例
| 記号 | 意味 |
|---|---|
| ⬜ | 未着手 |
| 🔄 | 作業中 |
| ✅ | 完了 |
| ⏸ | 保留 |

---

## Phase 1: DB + Model + Route + featureFlag

| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 1-1 | migration: announcements に target_company_id 追加 | ✅ | |
| 1-2 | Announcement モデル: fillable + attachments() | ✅ | |
| 1-3 | routes/web.php: edit/update/destroy 追加 | ✅ | |
| 1-4 | HandleInertiaRequests: crossCompanyAnnouncement フラグ追加 | ✅ | |

## Phase 2: コントローラー

| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 2-1 | store(): target_company_id 対応 + 添付処理 | ✅ | |
| 2-2 | create(): companies 一覧追加 | ✅ | |
| 2-3 | show(): 添付データ追加 | ✅ | |
| 2-4 | edit(): 編集フォーム | ✅ | |
| 2-5 | update(): タイトル・本文・添付更新 | ✅ | |
| 2-6 | destroy(): 添付 + recipients + 本体削除 | ✅ | |

## Phase 3: フロントエンド

| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 3-1 | Create.vue: 会社セレクタ + 添付アップロード | ✅ | |
| 3-2 | Show.vue (Clerk): 添付表示 + 編集・削除ボタン | ✅ | CONSOLIDATED_01 準拠 |
| 3-3 | Edit.vue: 新規作成 | ✅ | |
| 3-4 | Announcements/Show.vue (受信者側): 添付表示 | ✅ | |

## Phase 4: ビルド・確認

| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 4-1 | php artisan migrate | ✅ | 2026-05-30 実行済み |
| 4-2 | npm run build | ✅ | |
| 4-3 | 動作確認 | ⬜ | ブラウザ確認 |

---

## 作業ログ

| 日時 | 内容 |
|---|---|
| 2026-05-30 | ANNEX_PLAN1.md / ANNEX_MANAGER1.md 作成。設計確定（編集は本文・添付のみ、受信者変更なし） |
| 2026-05-30 | Phase 1〜4-2 完了。migration 実行・全コントローラー実装・Create/Show/Edit.vue 実装・受信者側 Show.vue 添付表示追加。npm run build 成功 |

---

## リスク管理

| リスク | 対処 |
|---|---|
| 添付 multipart/form-data のサイズ | php.ini / nginx の upload_max_filesize 確認（既存制限内で動作） |
| target_company_id null 移行 | migration で nullable のため既存データに影響なし |
| 受信者側で添付 eager load | announcement→attachmentables→attachments を load して渡す |
