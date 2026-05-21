# CLIENTCODE_PLAN1.md — クライアント Client ID 追加＋重複チェック改善

作成日: 2026-05-21

---

## 目的・背景

クライアント管理にユーザー定義の識別コード（Client ID）を追加する。
DB の主キー `id` とは別の業務用コード（`client_code`）として管理する。
あわせて、クライアント名重複チェックのロジックを `client_code` の有無に応じて3パターンに分岐させる。

---

## ユーザー指示（原文記録）

### 指示 #1（2026-05-21）

> admin, leader, coordinator にある、クライアント管理で、IDを入れられるようにする。
> このIDはユニークコードになる。DBのIDとは別にする。Client_IDなどにする。
> 登録、編集でこのIDがいじれるように。
> ひとまず、clients/index の ID は、Client ID として表示する。DBの ID は表示しない。

### 指示 #2（2026-05-21）

> クライアントの登録で、名前が一致するものは統合するかどうかを聞く仕組みにしている。
> クライアント名は同じでも ID で異なる場合があるとのこと。
> - ID 未登録で名前が同じ場合は警告するが
> - ID が違っていて同じ名前の場合は通すようにする。CONFIRM は出す。
> - ID が同じで名前が違う場合はアラートを出す。
> この修正も含めてください。
> また、z_instructions にこのクライアント系の修正設計ファイルを作成し、私の修正指示を記録していってください。

---

## DB 設計

### 変更テーブル: `clients`

| カラム名 | 型 | NULL | INDEX | 説明 |
|---|---|---|---|---|
| `client_code` | `varchar(64)` | nullable | UNIQUE | ユーザー定義の識別コード。未設定可。 |

追加位置: `name` カラムの直後（`after('name')`）

マイグレーションファイル: `2026_05_21_000001_add_client_code_to_clients_table.php`

---

## 重複チェック仕様（新ロジック）

### エンドポイント: `POST clients/check-duplicate`

#### リクエスト（追加パラメータ）
- `client_code` (nullable string, max:64) — 入力中の Client ID

#### レスポンス（新形式）
```json
{
  "no_code_same_name":   [ { "id": 1, "name": "...", "client_code": null } ],
  "diff_code_same_name": [ { "id": 2, "name": "...", "client_code": "ABC" } ],
  "same_code_diff_name": [ { "id": 3, "name": "...", "client_code": "XYZ" } ]
}
```

#### 判定ロジック（名前は normalizeClientName で正規化して比較）

| 状況 | 分類キー | フロント挙動 |
|---|---|---|
| 一方でも client_code が null で名前一致 | `no_code_same_name` | 警告モーダル（登録ブロック）。名前変更を促す |
| 両方 client_code があり異なる & 名前一致 | `diff_code_same_name` | 確認モーダル（「それでも登録する」ボタンあり） |
| client_code が一致 & 名前が不一致 | `same_code_diff_name` | アラートモーダル（登録ブロック）。Client ID 変更を促す |
| 上記いずれにも該当しない | 全配列が空 | 即登録 |

優先順位: `same_code_diff_name` → `no_code_same_name` → `diff_code_same_name`

---

## 変更ファイル一覧

| ファイル | 変更内容 |
|---|---|
| `database/migrations/2026_05_21_000001_add_client_code_to_clients_table.php` | 新規作成 |
| `app/Models/Client.php` | `client_code` を `$fillable` に追加 |
| `app/Http/Controllers/ClientController.php` | `store` / `update` バリデーション追加、`checkDuplicate` 3分岐対応、`clientsJson` に `client_code` を含める |
| `resources/js/Pages/Clients/Index.vue` | ID列を「Client ID」（`client_code`）に変更。DB `id` 非表示 |
| `resources/js/Pages/Clients/Create.vue` | `client_code` フィールド追加、3パターン重複チェックモーダル対応 |
| `resources/js/Pages/Clients/Edit.vue` | `client_code` フィールド追加 |

---

## 注意事項

- `project_jobs.client_id` 等の外部キーとカラム名が衝突しないよう、カラム名は `client_code` とする
- さくら本番への反映時は `php artisan migrate` を必ず実行すること（CLAUDE.md 規則 ③）
- `clientsJson` は `client_code` を返すようにする（統合モーダルの ID 列に表示するため）
