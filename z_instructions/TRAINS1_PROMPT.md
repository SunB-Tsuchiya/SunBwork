# TRAINS1_PROMPT.md — 新セッション継続プロンプト

このファイルを新セッション冒頭に貼り付けて作業を継続すること。

---

## 作業継続プロンプト（コピー用）

```
交通費申請・管理機能の実装を継続します。

設計ドキュメント:
- 詳細設計: z_instructions/TRAINS_PLAN1.md
- 進捗管理: z_instructions/TRAINS_MANAGER1.md

TRAINS_MANAGER1.md の進捗表を確認し、未完了タスクから作業を再開してください。

【重要ルール（必ず守ること）】
1. CLAUDE.md を参照してからコードを書く
2. Artisan はコンテナ内: docker compose exec laravel bash -lc "php artisan ..."
3. Vue/JS 変更後は必ず npm run build
4. ページは AppLayout を使い、py-12/max-w-7xl をページ側で重複させない
5. ToastUnified は AppLayout 内にあるので各ページに書かない
6. 質問は1つずつ行う

作業を開始する前に、MANAGER ファイルの現在の進捗状況を読んで、どこから再開するか教えてください。
```

---

## 設計サマリー

### 機能概要

**交通費入力（フォーム）:**
- 請求日（カレンダーピッカー）
- 部門コード（セレクター: 0共通/10情報出版/20制作/30製版/50オンデマンド）
- 所属・氏名（ログインユーザーから自動入力）
- 明細行（動的追加）: 発生日・行先・用件・区間（出発→到着）・IC/切符・金額
- 合計自動計算
- 出力: 印刷/PDF/Excel

**交通費一覧（管理）:**
- 年月フィルター
- 部署ごとにグループ表示
- 各社員の合計金額と詳細表示

### API戦略

| フェーズ | プラン | 機能 |
|---------|--------|------|
| Ph1 | フリー | 駅名補完のみ・運賃手入力 |
| Ph2 | 買い切り（Amazon）| ルート探索・IC/切符運賃自動入力 |

### ディレクトリ構造

```
Backend:
  app/Http/Controllers/Billing/Transport/
    ExpenseController.php
    ExpenseListController.php
  app/Models/
    TransportExpense.php
    TransportExpenseItem.php
  app/Services/Billing/
    EkispertService.php

Frontend:
  resources/js/Pages/SuperAdmin/Billing/Transport/
    Index.vue   # 交通費入力
    List.vue    # 交通費一覧
  resources/js/Components/Billing/Transport/
    ExpenseRow.vue         # 明細1行
    RouteSearchModal.vue   # ルート検索（Ph2）
```

### DB テーブル

- `transport_expenses` — 申請ヘッダー（user_id, department_code, billing_date, total_amount, status）
- `transport_expense_items` — 明細行（transport_expense_id, occurrence_date, destination, purpose, station_from, station_to, fare_type, amount）

### ルート名

```
superadmin.billing.transport.index    # 交通費入力
superadmin.billing.transport.store
superadmin.billing.transport.show
superadmin.billing.transport.update
superadmin.billing.transport.destroy
superadmin.billing.transport.list     # 交通費一覧
superadmin.billing.transport.station_search  # 駅名検索API
```

### 用件セレクター

| 表示 | 値 |
|------|-----|
| 打合せ（往復） | round_trip |
| 打合せ（往路） | outbound |
| 打合せ（帰路） | return |
| 打合せ（直帰） | direct_home |
| その他 | other |

### 発生日パース仕様

- `422` / `0422` → 4月22日
- `4/22` / `4-22` → 4月22日
- 保存: date型（YYYY-MM-DD）、年は請求日の年を使用

### ロール配置（最終）

| 機能 | 現在（テスト） | 最終 |
|------|-------------|------|
| 交通費入力 | SuperAdmin | User |
| 交通費一覧 | SuperAdmin | Clerk |

### 未解決・要確認事項

1. 駅すぱあとAPIキー取得状況（ユーザーが申請中）
2. PhpSpreadsheet / barryvdh/laravel-dompdf がプロジェクトに入っているか
3. teams テーブルの構造（部署取得方法）
4. フェーズ2の買い切りAPIキー取得タイミング
