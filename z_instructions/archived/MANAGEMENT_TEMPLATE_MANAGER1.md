# 管理シートテンプレート管理 MANAGER 1

## 状態

完了。

## チェックリスト

- [x] ハンドオフと現行実装の調査
- [x] UI規約の確認
- [x] 旧 `workflow_templates` と現行 `progress_templates` の不整合確認
- [x] ユーザーによる実装方針確認
- [x] ルート・Controller実装
- [x] 一覧・編集画面実装
- [x] プロジェクトジョブ詳細への導線追加
- [x] 進行管理テンプレートとの表示分離
- [x] Featureテスト
- [x] フロントビルド
- [x] Changelog・統合ドキュメント更新
- [x] PLAN / MANAGER / PROMPT を `archived/` へ移動

## 作業ログ

### 2026-06-30

- 管理シート新規作成が `ProgressTemplate.column_config` を利用することを確認。
- 既存 `WorkflowTemplates/Index.vue` は旧 `WorkflowTemplate.stage_config` 用であり、今回のテンプレート選択と接続されていないことを確認。
- 現行形式に統一する専用管理画面の設計を作成。
- 専用CRUD、アクセス制御、管理シートタブへの導線を実装。
- 管理用と進行管理用テンプレートを一覧・作成モーダルで分離。
- Featureテスト8件・38アサーション成功。
- `npm run build` 成功。
