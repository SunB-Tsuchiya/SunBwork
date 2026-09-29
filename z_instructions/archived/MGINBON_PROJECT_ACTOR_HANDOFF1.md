# MGinbon ProjectJob連動 引継ぎ1

更新日: 2026-09-28

## 現在地

銀本の出稿表レイアウト、年度取込、集計、CSV、年度別値一覧マスターまで実装済み。担当者連携は ProjectJob を正本とする設計を確定し、Phase A を完了した。Sakura本番には未反映。

## 最初に読むファイル

1. `AGENTS.md`
2. `z_instructions/MGINBON_PROJECT_ACTOR_PLAN1.md`
3. `z_instructions/MGINBON_PROJECT_ACTOR_MANAGER1.md`
4. `z_instructions/MGINBON_VALUE_LINK_PLAN1.md`
5. `z_instructions/MGINBON_MANAGER1.md`
6. `z_instructions/MGINBON_PROJECT_ACTOR_NEXT_PROMPT.md`

## 完了済み

- `project_job_subcontractors` を追加し、ProjectJob単位で外注先を登録可能にした。
- ProjectJob詳細に「外注先管理」画面を追加した。
- `ProjectJobAssigneeOptions` は案件リーダー・副リーダー・案件メンバー・案件外注先だけを返す。
- 案件未登録の管理外注先とghost userは新規担当候補に出さない。
- ProjectJob複製時、チームメンバーに加えて案件外注先も複製する。
- 年度別13値一覧は追加・編集・削除（無効化）・復元・並べ替え・年度コピーに対応。
- 既存割当、履歴、実績は所属解除や値削除で消さない方針。

## 検証済み

- `ProjectJobAssigneeOptionsTest` と `MGinbonAnnualImportTest`: 4 tests / 18 assertions 成功。
- 関連PHP 3ファイルの構文検査成功。
- `npm run build` 成功。
- `git diff --check` 成功。
- migration `2026_09_26_100001_create_project_job_subcontractors_table` はローカル batch 13 で適用済み。
- ローカルは既存外注割当0件だったため、migrationのバックフィルも0件。

## 次に行うこと

Phase Bから順に進める。

1. 年度取込時に既存ProjectJob選択または銀本専用ProjectJob作成を必須にする。
2. 既存の未接続年度ではLIST閲覧以外の担当・日付・帳票値更新を止め、接続案内を表示する。
3. 工程データ利用後の案件接続解除・変更を禁止する使用中判定を実装する。
4. Phase Cで担当者系5値一覧をユーザー／外注先へリンクする。
5. Phase DでLIST・詳細・出稿表・JobBox保存側を同じ候補判定へ統一する。

## 重要な設計制約

- 大本はProjectJob。銀本年度マスターが人名の正本になってはいけない。
- 正式担当は、そのProjectJobに所属するユーザーまたは外注先だけ。
- 任意文字列は帳票表示・旧値保持には使えるが、JobBoxの正式担当にはできない。
- 年度コピーだけでProjectJobへ人や外注先を追加しない。
- DBの `mginbon_projects.project_job_id` は既存データ救済のため当面nullableのまま、アプリ層で必須化する。
- 複数DBをまたぐため、ProjectJob作成とmginbon接続の失敗時整合性を慎重に扱う。
- 売上本番データを閲覧しない。破壊的DB操作をしない。

## 注意事項

- 作業ツリーには今回の銀本一連の変更がまとまっている。実行時生成物の `storage/**` はコミット対象にしない。
- `public/build` はリポジトリ管理されているため、最終ビルド結果をコミット対象に含める。
- SakuraへSSH・migration・seed・deployする前には、AGENTS.mdどおり正確なコマンドを提示して利用者確認を取る。
