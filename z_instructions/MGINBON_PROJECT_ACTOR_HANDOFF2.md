# MGinbon ProjectJob連動 引継ぎ2

更新日: 2026-09-28

## 現在地

ProjectJobを正本とする担当者連動のPhase Bを実装し、年度取込時の案件接続必須化、未接続年度の更新ガード、工程利用後の接続変更禁止まで完了した。ローカルで2024年度を既存ProjectJobへ接続して年度取込できることを利用者が確認済み。Sakura本番には未反映。

## 最初に読むファイル

1. `AGENTS.md`
2. `z_instructions/MGINBON_PROJECT_ACTOR_PLAN1.md`
3. `z_instructions/MGINBON_PROJECT_ACTOR_MANAGER1.md`
4. `z_instructions/MGINBON_PROJECT_ACTOR_HANDOFF2.md`
5. `z_instructions/MGINBON_VALUE_LINK_PLAN1.md`
6. `z_instructions/MGINBON_MANAGER1.md`
7. `z_instructions/MGINBON_PROJECT_ACTOR_NEXT_PROMPT.md`

## Phase Bで実装したこと

- 年度取込時に「既存ProjectJobへ接続」または「銀本専用ProjectJob作成」を必須化。
- 銀本専用案件では作成者をリーダー兼案件チームメンバーとして登録。
- 専用案件作成後にMGinbon年度作成が失敗した場合、新規ProjectJobを補償削除。
- プレビューをPOST後にGET年度取込画面へリダイレクトするPRG方式へ変更。年度作成の検証エラー時にPOST専用preview URLへGETして405になる不具合を修正。
- 未接続年度は台帳をLIST表示へ固定。
- 未接続年度の担当、日付、入稿チェック、帳票値、旧担当対応の更新を共通サービス `MGinbonProjectAccess` で拒否。
- 工程参加者、作業パッケージ、JobBox割当、手入力日付が存在する年度ではProjectJob接続の変更・解除を拒否。
- 台帳に未接続案内と、使用開始後の接続固定案内を追加。

## 手動確認

- 利用者が2024年ProjectJobを作成。
- 2024年度を既存案件接続で年度取込し、成功を確認。
- 台帳上部に選択した連携案件が正しく表示されることを確認。
- 最初の試行では年度自体は作成済みだったが、検証エラーの戻り先がPOST専用preview URLになって405が発生した。この年度をローカルMGinbon DBから一度削除し、PRG修正後に再取込して成功。
- 現在のローカル2024年度データは再取込済み。これはDBデータなのでGitには含まれない。

## 次に最優先で行うこと: 年度準備画面

年度対象校Excelを取り込んだ直後は学校だけがあり、媒体は未取込である。現状はこれを「LIST」と表示し、各行に「媒体未取込」が並ぶため、利用者から「LISTではなく取込準備画面にすべき。別タブメニューが必要」と指摘された。

次の構成で実装する。

1. 媒体が0件の年度は、空のLISTではなく「年度準備」を既定表示にする。
2. 準備状況を表示する。
   - 対象校リスト取込済み
   - ProjectJob接続済み／未接続
   - 媒体データ取込済み／未取込
3. 「媒体データを取り込む」または既存の取込確認フローへ進む明確なボタンを置く。
4. 上部を「年度準備」と「制作進行」に分離する。媒体未取込中はLIST・入稿チェック・出稿表などを利用不可または準備中表示にする。
5. 媒体取込後は制作進行のLISTを既定表示にする。
6. 既存の年度切替、ProjectJob接続操作、Sakuraの`/members`配下でも動くZiggy routeを維持する。

新しいページまたはコンポーネントを作る場合は、先に `z_instructions/CONSOLIDATED_01_layout_and_ui.md` を読むこと。

## その後

- Phase C: 担当者系5値一覧を案件所属ユーザー／外注先へリンク。
- Phase D: LIST・詳細・出稿表・JobBox保存側を同じ候補判定へ統一。
- 年度コピーで案件所属を増やさず、コピー先案件に所属しない担当リンクは継承しない。

## 検証結果

- 関連PHPファイルの構文検査成功。
- `MGinbonAnnualImportTest`: 3 tests / 15 assertions 成功。
- `npm run build` 成功。
- `git diff --check` 成功。
- `ProjectJobAssigneeOptionsTest` はコードのアサーション前に、既存migrationが `operator_reservation_requests` を重複作成するテストDB再構築エラーで停止。この既存migration問題を直すか、正常なテストDBで再実行すること。

## 注意事項

- `storage/**` の変更は実行時生成物であり、今回のcommit対象にしない。
- `public/build` はリポジトリ管理対象なので、最新ビルド結果を含める。
- 本番売上データを閲覧しない。破壊的DB操作をしない。
- SakuraへのSSH、migration、seed、deployは利用者へ正確なコマンドを提示し、明示確認後に行う。
