# 管理シートテンプレート管理 PLAN 1

状態: 完了（2026-06-30）

## 目的

Coordinator のプロジェクトジョブ詳細「管理シート」タブから、管理シート用テンプレートの一覧・新規作成・編集・削除へ移動できるようにする。

テンプレートは、管理シートの作成・「テンプレートとして登録」で既に使用している `progress_templates` のうち `sheet_type = management` のレコードを正とする。旧 `workflow_templates` は `stage_config` ベースで互換性がないため、この機能では使用しない。

## 仕様

### 導線

- `ProjectJobs/Show.vue` の管理シート見出しに「テンプレート管理」を追加する。
- 専用ルート `coordinator.management_templates.*` を使用する。
- 管理シートが既に存在する場合も「新規作成」と「テンプレート管理」を表示する。

### 一覧

- 共有テンプレート、またはログインユーザーが作成したテンプレートを表示する。
- `sheet_type = management` のみを対象とする。
- 名前、説明、作成者、共有状態、更新日を表示する。
- 作成者または Admin / SuperAdmin のみ編集・削除できる。

### 新規作成・編集

- 名前、説明、共有状態、`column_config` を編集できる。
- `ColumnTreeEditor` を利用し、管理シートと同じ列型を設定できる。
- 管理シートはデフォルト行を内部利用するため、`row_config` は編集しない。
- 新規作成時は管理シートの標準列構成を初期値にする。
- 保存時はサーバー側で必ず `sheet_type = management` とする。

### 後方互換

- `sheet_type = null` は進行管理表側に残す。種別不明データを管理シート側へ自動分類しない。
- 旧 `workflow_templates` のルート・画面・DBは今回削除しない。
- 既存の進行管理テンプレート画面は、管理用テンプレートを除外して混在を防ぐ。

## アクセス制御

- 一覧・閲覧: 共有、本人作成、Admin / SuperAdmin。
- 編集・削除: 本人作成、Admin / SuperAdmin。
- URL直指定でも種別が `management` でなければ404とする。

## 変更予定ファイル

- `routes/web.php`
- `app/Http/Controllers/Coordinator/ManagementTemplateController.php`（新規）
- `app/Http/Controllers/Coordinator/ProgressTemplateController.php`
- `resources/js/Pages/Coordinator/ManagementTemplates/Index.vue`（新規）
- `resources/js/Pages/Coordinator/ManagementTemplates/Edit.vue`（新規）
- `resources/js/Pages/Coordinator/ProjectJobs/Show.vue`
- `tests/Feature/ManagementTemplateTest.php`（新規）
- 完了時に関連統合ドキュメントと `ChangelogSeeder` を更新

## 検証

- 管理シートタブから一覧へ移動できる。
- 管理用テンプレートを新規作成・編集・削除できる。
- 保存したテンプレートが管理シート新規作成モーダルに現れる。
- 他ユーザーの非共有テンプレートを操作できない。
- 進行管理テンプレート一覧に管理用テンプレートが混在しない。
- Featureテストと `npm run build` が成功する。
