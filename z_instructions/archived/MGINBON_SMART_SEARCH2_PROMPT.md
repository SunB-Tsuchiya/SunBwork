# 銀本「便利な検索」再開プロンプト2

`z_instructions/MGINBON_SMART_SEARCH_PLAN2.md` と `z_instructions/MGINBON_SMART_SEARCH_MANAGER2.md` を最初から読み、現在のチェックリストと停止点を確認して作業を再開する。

必須条件:

- 既存のFileMaker型検索（複数箱、AND/OR、含める/除外、演算子、日付の直接入力＋カレンダー）を壊さない。
- 提案済み10機能をPLAN2のPhase順に実装する。
- 条件エンジンを一覧、件数preview、CSVで共用する。
- 旧検索JSONを後方互換で受け入れる。
- 日付はJSTで扱い、相対日付は実行時評価する。
- 既存未コミット変更と `storage/**` を保護する。
- 本番データを調査しない。
- Sakura SSH・migration・seed・deployは正確なコマンドを提示し、利用者の明示確認まで実行しない。
- ローカルmigrationも対象DBを確認し、利用者の明示確認まで実行しない。
- destructive DB操作は禁止。

再開時:

1. `git status --short` と対象差分を読み取り専用で確認。
2. MANAGER2の未完了Phaseを特定。
3. 対象Phaseの実装・テスト・MANAGER2更新を行う。
4. Phase 1〜3完了時に利用者の画面確認を依頼する。
5. migration実行が必要になった時点で、対象DBと正確なコマンドを提示して停止する。
