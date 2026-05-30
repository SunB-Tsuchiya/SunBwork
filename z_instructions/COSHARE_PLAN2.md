# COSHARE_PLAN2 — クライアント共有UI拡張

## 概要

1. **同一 client_code 共有確認**: 他社に既存のコードを入力したとき「共有しますか？」モーダルを表示
2. **編集モード**: クライアント一覧に編集モードボタンを追加し、ロールに応じてトグルUI表示
   - SuperAdmin → 会社間共有/非共有（`company_clients`）
   - Admin / Leader / Coordinator → 自社内の部署間共有/非共有（`client_departments`）

---

## 新規エンドポイント（3本）

| Method | URL | ルート名 | 処理 |
|---|---|---|---|
| POST | `clients/{client}/share-to-my-company` | `clients.share_to_my_company` | 他社クライアントを自社 company_clients に追加 |
| POST | `clients/{client}/toggle-department` | `clients.toggle_department` | 指定部署と client_departments をトグル（JSON レスポンス） |
| POST | `clients/{client}/toggle-company` | `clients.toggle_company` | SuperAdmin: 指定会社と company_clients をトグル（JSON レスポンス） |

`toggle-department` と `share-to-my-company` は admin / coordinator / leader 全グループに追加。  
`toggle-company` は admin グループのみ（コントローラー内で superadmin のみ許可）。

---

## Controller 変更詳細

### `checkDuplicate()` に `other_company_match` を追加

```php
// client_code が自社に存在しないが、他社の company_clients に存在する場合
$otherCompanyMatch = [];
if ($request->filled('client_code') && $user->company_id && $user->user_role !== 'superadmin') {
    $code = trim($request->client_code);
    $global = Client::where('client_code', $code)
        ->whereDoesntHave('companies', fn($q) => $q->where('companies.id', $user->company_id))
        ->with('companies:id,name')
        ->first(['id', 'name', 'client_code']);
    if ($global) {
        $otherCompanyMatch = [[
            'id' => $global->id, 'name' => $global->name,
            'client_code' => $global->client_code,
            'companies' => $global->companies->map(fn($c) => ['id'=>$c->id,'name'=>$c->name])->values(),
        ]];
    }
}
```

### `index()` に allCompanies を追加（SuperAdmin用）

```php
$allCompanies = $isSuperAdmin
    ? Company::where('id', '!=', 1)->orderBy('id')->get(['id', 'name'])
    : collect();
if ($isSuperAdmin) { $clients->load('companies:id,name'); }
return Inertia::render('Clients/Index', [
    ...,
    'allCompanies' => $allCompanies,
]);
```

### `shareToMyCompany(Client $client)`

```php
// 他社クライアントを自社 company_clients に attach
$client->companies()->syncWithoutDetaching([$user->company_id]);
return redirect()->route(...)->with('success', ...);
```

### `toggleDepartmentAdmin(Request $request, Client $client)` — JSON レスポンス

```php
// 自社の部署のみ操作可（department.company_id === user.company_id）
// 現在紐付いていれば detach、なければ attach
return response()->json(['attached' => $attached]);
```

### `toggleCompany(Request $request, Client $client)` — JSON レスポンス

```php
// superadmin のみ。company_clients をトグル
return response()->json(['attached' => $attached]);
```

---

## Vue 変更詳細

### Create.vue — 共有確認モーダル

`submit()` 内の checkDuplicate 後に追加:
```js
if (data.other_company_match?.length > 0) {
    shareModalClient.value = data.other_company_match[0];
    return; // モーダル表示して停止
}
```

モーダル内ボタン:
- 「共有する」→ `POST clients/{id}/share-to-my-company` (Inertia router.post)
- 「キャンセル」→ `form.client_code = ''` してモーダルを閉じる

### Index.vue — 編集モード

```js
const editMode = ref(false);
// SuperAdmin: allCompanies prop を使って company バッジ表示
// 非SuperAdmin: props.departments を使って department バッジ表示
```

バッジの ●/○ 判定:
- 非SuperAdmin: `client.departments.some(d => d.id === dept.id)`
- SuperAdmin: `client.companies.some(c => c.id === company.id)`

トグル時:
- AJAX POST → レスポンスの `attached` でローカル状態を楽観的更新
- `clientsState` ref でローカルコピーを管理（props を直接変更しない）

---

## 変更ファイル

| ファイル | 変更種別 |
|---|---|
| `routes/web.php` | 3ルート追加 |
| `app/Http/Controllers/ClientController.php` | checkDuplicate修正 + index修正 + 3メソッド追加 |
| `resources/js/Pages/Clients/Create.vue` | 共有確認モーダル追加 |
| `resources/js/Pages/Clients/Index.vue` | 編集モード + ロール別トグル追加 |
