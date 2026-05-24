# DEDUP_PLAN1 — クライアント重複チェック機能 設計仕様書

作成日: 2026-05-24

---

## 概要

クライアント管理に「重複チェック」ボタンを追加し、DB全体をスキャンして疑わしい重複ペアを一覧表示。  
ユーザーがペアごとに「残すクライアント」を選択し、一括統合できる機能。

---

## 重複検出ルール（3種類）

| タイプ | 条件 | ラベル |
|---|---|---|
| `same_code` | 両方に client_code があり、同じコード（念のため検出） | コード重複 |
| `code_missing_name_match` | 片方にコードあり・片方なし、かつ名前が完全一致 | コード欠損 |
| `fuzzy_name` | 名前を正規化後に一致（空白・カタカナ/ひらがな・全角半角数字/括弧） | 名前類似 |

**正規化拡張内容（normalizeClientName に追加）:**
- 現在: `mb_convert_kana($name, 'as')` → 全角英数/スペース→半角、法人格除去、空白除去、小文字化
- 追加: `mb_convert_kana($name, 'hc')` → 半角カタカナ→ひらがな、全角カタカナ→ひらがな（'as'適用後に実行）
- 'a' フラグで `（）` → `()` の変換は既に対応済み

---

## バックエンド変更

### 1. `app/Http/Controllers/ClientController.php`

#### `normalizeClientName()` 拡張

```php
private function normalizeClientName(string $name): string
{
    // 全角英数字・スペース・括弧 → 半角（既存）
    $name = mb_convert_kana($name, 'as', 'UTF-8');
    // 半角カタカナ → ひらがな、全角カタカナ → ひらがな（追加）
    $name = mb_convert_kana($name, 'hc', 'UTF-8');

    // 法人格除去（既存）
    // ... (変更なし)
    
    // スペース・中黒除去（既存）
    $name = preg_replace('/[\s　・]+/u', '', $name);
    
    // 小文字化（既存）
    $name = mb_strtolower($name, 'UTF-8');
    
    return $name;
}
```

#### 新メソッド `duplicateCheckPage()`

- `GET clients/duplicate-check` のコントローラーアクション
- DB から全クライアント（`id, name, client_code, created_at` + `withCount('projectJobs')`）を取得
- O(n²) で全ペアを比較し、3ルールで重複ペアを収集
- `Inertia::render('Clients/DuplicateCheck', ['pairs' => $pairs])` を返す

```php
$pairs[] = [
    'reason'   => $reason,      // 'same_code' | 'code_missing_name_match' | 'fuzzy_name'
    'client_a' => [
        'id'                 => $a->id,
        'name'               => $a->name,
        'client_code'        => $a->client_code,
        'project_jobs_count' => $a->project_jobs_count,
        'created_at'         => $a->created_at->format('Y-m-d'),
    ],
    'client_b' => [ ... ],
];
```

#### 新メソッド `batchMerge()`

- `POST clients/batch-merge` のコントローラーアクション
- リクエスト: `{ merges: [ { source_id, target_id }, ... ] }`
- DB トランザクション内で全ペアを処理（既存 `merge()` のロジック流用）
- 全ペア処理後に `clients.index` または `clients.duplicate_check` へリダイレクト

**バリデーション:**
```
merges          required|array|min:1
merges.*.source_id  required|integer|exists:clients,id
merges.*.target_id  required|integer|exists:clients,id
```

**権限:**
- 既存 `merge()` と同様: `requireAdminPermission` + `requireLeaderPermission` + `authorize('delete', $client)`
- superadmin 以外: 同一 company_id チェック

### 2. `routes/web.php`

admin / leader / coordinator の各グループに以下を追加（**Resource より前**に配置必須）:

```php
Route::get('clients/duplicate-check', [ClientController::class, 'duplicateCheckPage'])->name('clients.duplicate_check');
Route::post('clients/batch-merge',    [ClientController::class, 'batchMerge'])->name('clients.batch_merge');
```

**追加位置:**
- admin: `clients/csv/upload` の近く（現在 line 348 付近）
- leader: line 487 付近
- coordinator: line 603 付近

---

## フロントエンド変更

### 3. `resources/js/Pages/Clients/Index.vue`

`#headerExtras` に「重複チェック」ボタンを追加（「新規作成」の左に配置）:

```vue
<Link
    :href="route(`${routePrefix}.clients.duplicate_check`)"
    class="rounded bg-yellow-600 px-4 py-2 text-sm font-medium text-white hover:bg-yellow-700"
>重複チェック</Link>
```

表示条件: `!props.showDormant`（休眠一覧表示中は非表示）

### 4. `resources/js/Pages/Clients/DuplicateCheck.vue`（新規作成）

**Props:** `pairs: Array`

**UI 構成:**

```
ヘッダー: クライアント重複チェック
headerExtras: ← 一覧に戻る

[XX件の疑わしい重複が見つかりました]
[全選択] [選択解除]                   [選択した〇件を統合]  ← 右寄せ

── ペアカード（v-for） ──────────────────────────────────
☑ [コード重複 / コード欠損 / 名前類似]
  ┌────────────────────┐  ┌────────────────────┐
  │ (●) 残す           │  │ (○)                │
  │   ABC株式会社       │  │   ABC（株）         │
  │   コード: CODE001   │  │   コード: CODE001   │
  │   案件: 15件       │  │   案件: 3件        │
  │   登録: 2023-01-15  │  │   登録: 2024-05-20  │
  └────────────────────┘  └────────────────────┘
────────────────────────────────────────────────────────
```

**デフォルト選択ロジック:**
- 案件数が多い方をデフォルトで「残す」（ラジオ選択済み）
- 同数の場合は作成日が古い方（`created_at` が早い方）

**統合ボタン動作:**
- チェックありのペアを収集
- 各ペアから `{ source_id: 選択なし側.id, target_id: 選択あり側.id }` を生成
- `router.post(route('${routePrefix}.clients.batch_merge'), { merges: [...] })`

**空表示:** pairs.length === 0 のとき「重複するクライアントは見つかりませんでした」

---

## 変更ファイル一覧

| ファイル | 変更種別 |
|---|---|
| `app/Http/Controllers/ClientController.php` | 修正（normalizeClientName 拡張 + 2 メソッド追加） |
| `routes/web.php` | 修正（3 グループに各 2 ルート追加） |
| `resources/js/Pages/Clients/Index.vue` | 修正（ボタン追加） |
| `resources/js/Pages/Clients/DuplicateCheck.vue` | **新規作成** |

計: 4 ファイル

---

## フェーズ別タスク

| フェーズ | タスク |
|---|---|
| Phase 1 | `normalizeClientName()` 拡張（hc 追加） |
| Phase 2 | `duplicateCheckPage()` メソッド実装 |
| Phase 3 | `batchMerge()` メソッド実装 |
| Phase 4 | `routes/web.php` にルート追加（3 グループ） |
| Phase 5 | `Clients/Index.vue` にボタン追加 |
| Phase 6 | `Clients/DuplicateCheck.vue` 新規作成 |
| Phase 7 | `npm run build` |
| Phase 8 | 動作確認・ChangelogSeeder 追記 |

---

## 注意点・リスク

- `batchMerge()` はトランザクション内で実行するが、ループ中に1件失敗しても **ロールバック** or **スキップ+ログ** の選択が必要 → スキップ方式（個別 try-catch）で安全側に倒す
- `scan` は O(n²) だが、1社あたりの客数は通常 100〜500 件程度なので問題なし
- 新規ルートは Resource より前に定義（既存の `{client}` パラメータ に飲まれないよう）
- `normalizeClientName()` の変更は既存の `checkDuplicate()` / `csvPreview()` / `csvStore()` にも影響するが、より厳密になるだけで破壊的ではない
