# DEDUP1_PROMPT — 新セッション開始用プロンプト

## このファイルの使い方

新しい会話セッションで「クライアント重複チェック」機能の実装を続ける場合、以下をそのまま貼り付けてください。

---

## プロンプト（ここから）

クライアント管理に「重複チェック」機能を実装しています。設計は `z_instructions/DEDUP_PLAN1.md`、進捗は `z_instructions/DEDUP_MANAGER1.md` を確認してください。

**機能概要:**
- クライアント一覧に「重複チェック」ボタンを追加
- ボタン押下でスキャン結果ページ（`Clients/DuplicateCheck.vue`）を表示
- 3 種類の重複検出: コード重複 / コード欠損同名 / 名前類似（カタカナ・括弧・全半角差異）
- ユーザーがペアごとに「残すクライアント」をラジオ選択
- チェックした複数ペアをまとめて統合（`batchMerge` エンドポイント）

**変更ファイル（4 ファイル）:**
1. `app/Http/Controllers/ClientController.php` — `normalizeClientName()` 拡張 + `duplicateCheckPage()` + `batchMerge()` 追加
2. `routes/web.php` — admin/leader/coordinator 各グループに 2 ルート追加
3. `resources/js/Pages/Clients/Index.vue` — 「重複チェック」ボタン追加
4. `resources/js/Pages/Clients/DuplicateCheck.vue` — 新規ページ作成

**現在の進捗:** DEDUP_MANAGER1.md の進捗テーブルを確認してください。

**必ず CLAUDE.md の作業ルールに従って実装してください。**

---

## 設計サマリー（参照用）

### 重複検出ロジック（PHP）

```php
// normalizeClientName に追加
$name = mb_convert_kana($name, 'hc', 'UTF-8'); // 'as' の後に実行

// ペア比較
$aNorm = normalizeClientName($a->name);
$bNorm = normalizeClientName($b->name);
$aCode = $a->client_code ? trim($a->client_code) : null;
$bCode = $b->client_code ? trim($b->client_code) : null;

if ($aCode && $bCode && $aCode === $bCode)                  → 'same_code'
if ((!$aCode || !$bCode) && $a->name === $b->name)          → 'code_missing_name_match'
if ($aNorm === $bNorm && !already_matched)                   → 'fuzzy_name'
```

### ルート（3 グループ共通パターン）

```php
// clients/csv/upload などの後・resource('clients') の前に追加
Route::get('clients/duplicate-check', [ClientController::class, 'duplicateCheckPage'])->name('clients.duplicate_check');
Route::post('clients/batch-merge',    [ClientController::class, 'batchMerge'])->name('clients.batch_merge');
```

### batchMerge リクエスト形式

```json
{
  "merges": [
    { "source_id": 42, "target_id": 7 },
    { "source_id": 88, "target_id": 23 }
  ]
}
```

### DuplicateCheck.vue ペアカード UI

- 2カラム並列表示（左: client_a、右: client_b）
- 各カラム: ラジオボタン「残す」+ 名前・コード・案件数・登録日
- デフォルト: 案件数が多い方のラジオを選択済みに
- ペア左端にチェックボックス（統合対象選択）
- ページ上部: 全選択ボタン・統合ボタン
