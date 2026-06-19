# DEMOMGR1_PROMPT.md — 新セッション開始用プロンプト

このファイルの内容を新しい Claude セッションの最初のメッセージとして貼り付ける。

---

## ユーザーが貼り付けるメッセージ（ここから）

デモページ管理機能（DEMOMGR）の作業を続けます。

設計書とマネージャーを確認してください:
- `/home/w229/SunBwork/z_instructions/DEMOMGR_PLAN1.md`
- `/home/w229/SunBwork/z_instructions/DEMOMGR_MANAGER1.md`

マネージャーの進捗一覧を見て、未完了タスク（⬜）から再開してください。

---

## 背景

SunBwork の SuperAdmin タブメニューに「デモページ管理」を追加する作業。
NSystem（/n-demo）のゲスト認証を .env ベースから DB ベースに移行し、
管理画面から許可メール・パスワード・公開期限を制御できるようにする。

- 設計書: `DEMOMGR_PLAN1.md`（DB定義・ルート・ファイル一覧・注意事項すべて記載）
- NSystem ガイド: `z_instructions/NSYSTEM_GUIDE.md`
