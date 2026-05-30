# COSHARE1_PROMPT — 新セッション開始用プロンプト

## このセッションで行う作業

`company_clients` 中間テーブルを使ったクライアント会社間共有の実装。  
設計は完了済み。コードを書く。

---

## 設計サマリー

**問題:** サンエー印刷のAdminがサン・ブレーンのクライアント一覧を全件閲覧できてしまう。  
**原因:** `ClientController::index()` で `admin` ロールに `forCompany` スコープが未適用。  
**解決策:** `company_clients` 中間テーブルで「どの会社がどのクライアントを使うか」を管理。

---

## 本番データ（2026-05-30 確認済み）

- companies: id=1(Superadmin), id=2(サン・ブレーン), id=3(サンエー印刷)
- clients: 44件（全てサン・ブレーン所有。company_id=2が41件、NULLが3件）
- departments: 情報出版/製版/オンデマンド(co=2)、総務部/経理部/営業部(co=3)
- company_clients: テーブル未存在

---

## 実装フェーズ（詳細は COSHARE_PLAN1.md を参照）

### Phase 1 — DB + Model + Policy

1. マイグレーション新規作成:
   - `company_clients (company_id FK, client_id FK, PK(両方))` テーブル
   - データ移行: 既存44件すべてを company_id=2 で挿入

2. `app/Models/Client.php`:
   - `companies()` belongsToMany 追加
   - `scopeForCompany` を `whereHas('companies', ...)` に変更

3. `app/Policies/ClientPolicy.php`:
   - `view/update/delete` を `client->companies()->where('companies.id', $user->company_id)->exists()` ベースに

### Phase 2 — Controller

`app/Http/Controllers/ClientController.php` を以下のように変更:
- `index()`: superadmin のみ全件、それ以外は forCompany。部署一覧 prop を追加
- `create()`/`edit()`: departments を自社のみ
- `store()`: company_clients にも INSERT（`$client->companies()->attach($companyId)`）
- `update()`: client_code ユニークルールをグローバルに
- `csvUpload()`/`csvPreview()`/`csvStore()`: 部署フィルタ + company_clients
- `clientsJson()`/`checkDuplicate()`: admin も forCompany 適用
- `merge()`/`batchMerge()`: マージ後 `$target->companies()->syncWithoutDetaching($source->companies->pluck('id'))`、その後 $source を削除

### Phase 3 — Vue

`resources/js/Pages/Clients/Index.vue`:
- `departments: { type: Array, default: () => [] }` prop を追加
- 部署タブを `allDepartments` computed（clients から動的抽出）→ `props.departments` に変更
- `DEPT_COLORS` ハードコードを汎用 id ベースに変更

---

## 注意事項

- `clients.company_id` カラムは削除しない（互換性維持）
- `store()` では `clients.company_id` にも値を設定し続ける（互換性）
- マイグレーションは `insertOrIgnore` で冪等に
- Policy の `view` が N+1 にならないよう Index クエリ側で事前に company_clients を考慮すること
- 本番デプロイ時は `php artisan migrate` を必ず実行（テーブル追加）

---

## 参照ファイル

- 設計: `z_instructions/COSHARE_PLAN1.md`
- 進捗: `z_instructions/COSHARE_MANAGER1.md`
- デプロイ: `z_instructions/DEPLOY_SAKURA.md`
