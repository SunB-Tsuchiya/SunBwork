# 管理シートテンプレート管理 再開プロンプト

状態: 完了（参照用）

`z_instructions/MANAGEMENT_TEMPLATE_PLAN1.md` と
`z_instructions/MANAGEMENT_TEMPLATE_MANAGER1.md` を読み、未完了項目から再開する。

目的は、管理シートで実際に利用される `progress_templates` の
`sheet_type = management` を対象に、一覧・新規作成・編集・削除と
プロジェクトジョブ詳細からの導線を実装すること。

旧 `workflow_templates` / `WorkflowTemplate.stage_config` は互換性がないため、
今回の新機能へ接続しない。既存機能を削除もしない。

作業ツリーにある `public/build` の既存差分を保護し、無関係な変更を戻さないこと。
