# CLIENTCODE1_PROMPT.md — 新セッション開始用プロンプト

## このセッションで実装したこと

SunBWork のクライアント管理画面に `client_code`（Client ID）カラムを追加しました。
詳細設計は `CLIENTCODE_PLAN1.md`、進捗は `CLIENTCODE_MANAGER1.md` を参照。

---

## 新セッション引き継ぎ用プロンプト

```
SunBWork プロジェクトで、クライアント管理の `client_code` 機能を実装しました。
以下のファイルを変更済みです:

- database/migrations/2026_05_21_000001_add_client_code_to_clients_table.php
- app/Models/Client.php
- app/Http/Controllers/ClientController.php
- resources/js/Pages/Clients/Index.vue
- resources/js/Pages/Clients/Create.vue
- resources/js/Pages/Clients/Edit.vue

設計仕様は z_instructions/CLIENTCODE_PLAN1.md を参照してください。

[継続する作業の内容をここに記述]
```

---

## 重要仕様サマリー

- `client_code` は `clients` テーブルの nullable unique カラム（varchar 64）
- DB 主キー `id` と混同しないこと（project_jobs.client_id は FK として別物）
- 重複チェックは3パターン: `no_code_same_name`（ブロック）/ `diff_code_same_name`（確認）/ `same_code_diff_name`（アラート）
- Index.vue では DB id を非表示にして `client_code` を「Client ID」列として表示
