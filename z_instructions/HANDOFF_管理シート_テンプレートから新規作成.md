# 管理シートをテンプレートから新規作成できるようにする

## 依頼内容

対象画面:

`https://sun-brain.co.jp/members/coordinator/project_jobs/3?tab=workflow`

Coordinator の「プロジェクトジョブ > 管理シート」タブでは、既存の管理シートをテンプレートとして登録できるが、新規作成時にテンプレートを選んで読み込むUIがない。

「進行管理表」の新規作成と同様に、保存済みテンプレートを選択して管理シートを新規作成できるようにする。

## 調査済みの関連ファイル

- `resources/js/Pages/Coordinator/ProjectJobs/Show.vue`
  - 進行管理表の新規作成モーダル: 684行付近
  - 管理シートの新規作成モーダル: 878行付近
  - 進行管理表の作成処理: 1567行付近
  - 管理シートの作成処理: 1704行付近
- `app/Http/Controllers/Coordinator/ProjectJobController.php`
  - 案件詳細の Inertia props
  - `sheetTemplates` はアクセス可能な `ProgressTemplate` を取得している
  - `workflowTemplates` も返しているが、こちらは旧形式の `WorkflowTemplate`
- `app/Http/Controllers/Coordinator/WorkflowSheetController.php`
  - `store()` に `template_id` と列構成コピーの処理が一部存在する
  - `registerAsTemplate()` が現在の管理シートをテンプレート登録する
- `app/Models/ProgressTemplate.php`
- `app/Models/WorkflowTemplate.php`
- `app/Models/WorkflowSheet.php`
- `database/migrations/2026_05_14_100001_create_workflow_templates_table.php`
- `database/migrations/2026_05_14_100003_create_workflow_sheets_table.php`
- `database/migrations/2026_05_17_100005_add_sheet_type_to_progress_templates.php`
- `resources/js/Pages/Coordinator/WorkflowTemplates/Index.vue`

## 重要: 判明している不整合

UIに選択欄を追加するだけでは不十分。サーバー側に以下の不整合がある。

### 1. 管理シート登録テンプレートは `progress_templates`

`WorkflowSheetController::registerAsTemplate()` は、管理シートの `column_config` を `ProgressTemplate` として保存している。

```php
$template = ProgressTemplate::create([
    'name'          => $validated['name'],
    'column_config' => $sheet->getEffectiveColumnConfig(),
    'sheet_type'    => 'management',
    'created_by'    => $request->user()->id,
    'is_shared'     => $validated['is_shared'] ?? false,
]);
```

### 2. `ProgressTemplate::$fillable` に `sheet_type` がない

現在の `app/Models/ProgressTemplate.php` の `$fillable` に `sheet_type` が含まれていないため、上記の `sheet_type => management` は保存されない可能性が高い。

今後の登録を正しく分類するため、`sheet_type` を `$fillable` に追加すること。

既存データは `sheet_type = null` の可能性があるため、一覧を `sheet_type = management` だけに絞ると、すでに登録済みの管理シートテンプレートが表示されなくなる点に注意する。

### 3. `WorkflowSheetController::store()` の外部キーが不整合

`store()` は現在 `template_id` を `progress_templates.id` として検証・読込している。

```php
'template_id' => 'nullable|exists:progress_templates,id',
```

しかし `workflow_sheets.template_id` の外部キーは旧形式の `workflow_templates.id` を参照している。

そのため、選んだ `ProgressTemplate` のIDをそのまま以下へ保存すると、IDの偶然一致を除いて外部キーエラーになる。

```php
'template_id' => $validated['template_id'] ?? null,
```

今回の修正では、管理シートの作成時に `ProgressTemplate::column_config` をコピーし、`workflow_sheets.template_id` にはそのIDを保存しない（`null` にする）のが最小かつ安全。

旧 `WorkflowTemplate` / `workflow_templates` は `stage_config` ベースの別系統なので、今回の「管理シート画面からテンプレートとして登録したものを読み込む」機能と混同しないこと。

## 推奨実装

### フロントエンド

`resources/js/Pages/Coordinator/ProjectJobs/Show.vue` の管理シート新規作成モーダルへ以下を追加する。

- 「テンプレート（任意）」の `<select>`
- `— 使用しない —` の選択肢
- 保存済みテンプレートの一覧
- 選択IDを `template_id` としてPOST
- 作成成功時にシート名とテンプレートIDをリセット

既存の進行管理表モーダルとUI・操作感を揃える。

利用するテンプレート一覧について:

- `sheetTemplates` はすでに案件詳細の props として渡されている
- 現在の取得条件は「共有テンプレート、または自分が作成したテンプレート」
- 既存の管理シートテンプレートで `sheet_type` が未保存の可能性があるため、まずはこの一覧をそのまま使うのが後方互換上安全
- 明確に管理シート用だけへ分ける場合は、既存レコードの移行・判別方法も同時に設計する

想定する状態:

```js
const newWorkflowTemplateId = ref(null);
```

想定するPOST:

```js
router.post(
    route('coordinator.project_jobs.workflow_sheets.store', { projectJob: job.id }),
    {
        name,
        template_id: newWorkflowTemplateId.value ?? null,
    },
    {
        onSuccess: () => {
            showCreateWorkflowModal.value = false;
            newWorkflowName.value = '';
            newWorkflowTemplateId.value = null;
        },
    },
);
```

### バックエンド

`WorkflowSheetController::store()` で以下を行う。

1. `template_id` は引き続き `progress_templates` に対して検証
2. 指定されたテンプレートが、共有テンプレートまたはログインユーザー自身のテンプレートであることを確認
3. 権限がないテンプレートIDなら 403 またはバリデーションエラー
4. `column_config` をテンプレートからコピー
5. `workflow_sheets.template_id` は `null` のまま保存し、旧 `workflow_templates` の外部キーへ `ProgressTemplate` のIDを入れない
6. テンプレート未指定時は現在のデフォルト列構成を維持

アクセス制御例:

```php
$template = ProgressTemplate::query()
    ->whereKey($validated['template_id'])
    ->where(function ($query) use ($request) {
        $query->where('is_shared', true)
            ->orWhere('created_by', $request->user()->id);
    })
    ->firstOrFail();
```

`ProgressTemplate::$fillable` には `sheet_type` を追加する。

## テスト観点

最低限、次を確認する。

1. 管理シート新規作成モーダルにテンプレート選択欄が表示される
2. テンプレート未指定で従来どおりデフォルト構成の管理シートを作成できる
3. テンプレート指定時、その `column_config` が新規管理シートへコピーされる
4. 作成時に外部キーエラーが発生しない
5. 他ユーザーの非共有テンプレートをID直指定しても利用できない
6. 管理シートから新たにテンプレート登録すると `sheet_type = management` が保存される
7. 既存の進行管理表のテンプレート作成機能に影響がない
8. `npm run build` または対象Vueのビルドが通る
9. 関連PHPテストを追加・実行する

## 作業上の注意

SunBwork の作業ツリーには `public/build` 配下に大量の既存差分がある。これらは今回の依頼とは無関係なので、上書き・削除・リセットしないこと。

ビルドを実行すると `public/build` の差分がさらに更新される可能性がある。コミット対象を確認し、今回変更したソースとテスト以外を不用意に含めないこと。

## 完了条件

Coordinator のプロジェクトジョブ詳細で「管理シート」タブを開き、「新規作成」から保存済みテンプレートを選択し、その列構成を引き継いだ管理シートを正常に作成できること。
