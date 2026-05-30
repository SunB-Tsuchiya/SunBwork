# COSHARE_MANAGER1 — 進捗管理

## 作業フロー

```
Phase1 (DB/Model/Policy) → Phase2 (Controller) → Phase3 (Vue) → build → 本番デプロイ
```

---

## 進捗一覧

| # | Phase | タスク | 状態 | 備考 |
|---|---|---|---|---|
| 1 | 1 | `company_clients` マイグレーション作成 | ✅ 完了 | データ移行込み |
| 2 | 1 | `Client::scopeForCompany` 変更 | ✅ 完了 | company_clients 参照へ |
| 3 | 1 | `Client::companies()` relationship 追加 | ✅ 完了 | belongsToMany |
| 4 | 1 | `ClientPolicy` 更新 | ✅ 完了 | view/update/delete |
| 5 | 2 | `index()` 修正 | ✅ 完了 | admin も forCompany 適用、部署 pass |
| 6 | 2 | `create()` / `edit()` 修正 | ✅ 完了 | 部署を自社のみ |
| 7 | 2 | `store()` 修正 | ✅ 完了 | company_clients INSERT |
| 8 | 2 | `update()` 修正 | ✅ 完了 | uniqueCode グローバル化 |
| 9 | 2 | `csvUpload()`/`csvPreview()`/`csvStore()` 修正 | ✅ 完了 | 部署フィルタ + company_clients |
| 10 | 2 | `clientsJson()` / `checkDuplicate()` 修正 | ✅ 完了 | admin も forCompany |
| 11 | 2 | `merge()` / `batchMerge()` 修正 | ✅ 完了 | company_clients マージ |
| 12 | 3 | `Index.vue` 修正 | ✅ 完了 | departments prop + タブ |
| 13 | - | `npm run build` | ✅ 完了 | さくら用 + ローカル用 |
| 14 | - | ローカル動作確認 | ✅ 完了 | サン・ブレーン + サンエー印刷 両ロール |
| 15 | - | 本番デプロイ（migrate 実施済み） | ✅ 完了 | 2026-05-30 本番確認済み |

---

## 動作確認チェックリスト

### サン・ブレーン Admin でログイン
- [ ] クライアント一覧に 44件表示される
- [ ] 部署タブが 情報出版 / 製版 / オンデマンド のみ表示
- [ ] 新規作成フォームの部署選択に 情報出版 / 製版 / オンデマンド のみ

### サンエー印刷 Admin でログイン
- [ ] クライアント一覧が空（0件）
- [ ] 部署タブが 総務部 / 経理部 / 営業部 のみ表示
- [ ] 新規クライアント作成後、自社一覧に表示される
- [ ] サン・ブレーンのクライアントは見えない

### SuperAdmin でログイン
- [ ] 全 44件（+サンエー印刷追加分）が表示される
- [ ] 部署タブは全社の部署が表示される（またはタブなし）

### 案件作成フォーム（clientsJson エンドポイント）
- [ ] サン・ブレーン Coordinator でクライアント検索 → 自社 44件のみヒット
- [ ] サンエー印刷 Coordinator で検索 → 自社クライアントのみヒット

### クライアント統合（merge）
- [ ] サン・ブレーン Admin が統合 → company_clients も正しく移行
- [ ] 統合後、統合先クライアントが一覧に表示される

---

## 作業ログ

| 日時 | 作業内容 |
|---|---|
| 2026-05-30 | 設計ドキュメント作成（PLAN/MANAGER/PROMPT） |
| 2026-05-30 | 全フェーズ実装・ビルド・本番デプロイ完了。ローカル・本番両方で動作確認済み |
