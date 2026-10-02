# 銀本「便利な検索」機能 MANAGER2

更新日: 2026-10-02
状態: 完了。Phase 1〜8実装・ローカルDB適用・自動検証・利用者画面確認済み

## チェックリスト

- [x] 利用者要望と採用対象10機能の整理
- [x] 既存FileMaker検索・CSV・ルート・DB構造の調査
- [x] UI規約確認
- [x] PLAN2作成
- [x] PROMPT2作成
- [x] 利用者によるPLAN2承認
- [x] Phase 1 条件エンジンv2
- [x] Phase 2 便利な検索UI
- [x] Phase 3 件数プレビュー
- [x] Phase 1〜3の利用者画面確認
- [x] Phase 4 保存済み検索・履歴
- [x] migration内容の作成・レビュー
- [x] migration実行（利用者承認後、ローカル `sunbwork_mginbon` のみ）
- [x] Phase 5 異常検索
- [x] Phase 6 ハイライト
- [x] Phase 7 CSV
- [x] Phase 8 統合検証・文書・変更履歴
- [x] 完了資料をarchivedへ移動

## 作業ログ

### 2026-10-01

- 利用者が提案10項目を原則すべて採用し、「便利な検索」別メニューとしての計画作成を依頼。
- 現行 `MGinbonLedgerSearch` はFileMaker型の箱内AND、箱間OR、除外、演算子に対応済み。
- 保存検索と個人履歴は永続化が必要なため、`mginbon`接続へ2テーブル追加する案とした。
- 相対日付は保存時固定ではなくJST実行時評価とする。
- 一覧、preview件数、CSVが同じ検索サービスを通る構成とする。
- 既存未コミット変更と `storage/**` は保護中。
- SSH・migration・seed・deployは未実行。

## 現在の停止点

Phase 1〜8の実装、自動検証、Phase 6の一致セル・一致理由表示、Phase 7の検索条件付きCSVの利用者画面確認が完了。ChangelogSeederは更新済みだが、seedは明示確認なしに実行しない。

## Phase 1〜3 実装ログ

- 条件JSON version 2 envelopeとversion 1後方互換decodeを追加。
- JST実行時評価の今日・昨日・明日・今週・来週・今月・過去/今後N日・今日以前/以降を追加。
- 工程日付の未入力検索をNOT EXISTSで実装。
- 便利な検索メニュー（よく使う、日付・未入力、異常、保存済み、履歴）を追加。
- 置換、OR追加、現在結果内AND再絞り込みを追加。
- 実行済み検索条件の要約チップと個別解除を追加。
- 400ms debounce、rate limit付き件数プレビューAPIを追加。
- 初期異常検索5種を追加。残る異常ルールはPhase 5で実装する。
- 保存済み・履歴タブはPhase 4まで準備表示。migrationは未作成・未実行。
- 銀本テスト32件・113 assertions成功。


## Phase 4 実装ログ（2026-10-01）

- `mginbon_saved_searches` と `mginbon_search_histories` migrationを作成（未実行）。
- 個人用／共有用、年度固有／全年度、表示方法を保存するモデル・APIを追加。
- 共有検索の変更権限をCoordinator/Admin/SuperAdminへ限定。
- 履歴は本人・年度別直近20件、同一条件の連続実行を集約。
- 保存済み検索と履歴の復元・即時実行・削除・履歴から保存への昇格UIを追加。
- 条件allowlist、canonical JSON、SHA-256 hash、要約サービスを追加。
- 検索失敗を避けるため、履歴記録失敗は検索結果表示を妨げない設計。
- ローカル銀本DB名は `sunbwork_mginbon`。000011/000012がPendingであることを読み取り確認。
- 銀本検索7 tests / 26 assertions成功、Vue build成功、`git diff --check`成功。


## Phase 4〜5 完了ログ（2026-10-01）

- 利用者の明示承認後、対象DB名 `sunbwork_mginbon` を再確認してmigration 000011/000012を適用。
- 一時SQLite銀本DBによる保存・一覧・履歴連続集約・削除・共有権限の統合テストを追加。
- 異常検索を全12種へ拡張。校正日逆転、複数担当、重複、無効担当参照、前工程完了後の未着手、期限超過、工程タスク欠落を追加。
- 銀本対象10 tests / 52 assertions成功、Vue build成功、`git diff --check`成功。
- Sakura SSH・migration・seed・deployは未実行。


## お気に入り検索シミュレーション調整（2026-10-01）

- UI名を「保存済み」から「お気に入り保存」へ変更。
- 条件を保持したまま実レコード結果を下部へ表示する「シミュレートする」を追加。
- シミュレーション中も便利な検索パネルを維持し、「条件をやり直す」で検索箱へ戻れるようにした。
- 最終的な「検索実行」とシミュレーションを分離し、シミュレーションでは履歴を記録しない。
- お気に入り保存ボタンが無効になる条件（検索条件なし／検索名なし）を画面へ明示。
- 銀本対象10 tests / 52 assertions成功、Vue build成功、`git diff --check`成功。

## Phase 6〜8 完了ログ（2026-10-02）

- 検索結果のページ内レコードに対し、どの条件に一致したかを条件ごとに再判定する `matchDescriptors` を追加。
- LISTと表形式で一致した学校情報・工程日付・担当者セルを強調し、各レコードの「一致理由」を展開表示できるようにした。
- CSV出力に画面と同じ詳細検索、媒体優先順、学校順を適用。「検索条件」「検索実行日時」「検索実行者」を追加した。
- SQLite統合テストでMySQL専用の学校コードソート式を検出し、DBドライバ別の安全な数値ソートに修正。
- MGinbon関連38 tests / 151 assertions成功、Vue本番build成功。Sakura SSH・migration・seed・deployは未実行。
- 利用者が一致ハイライト、一致理由、表形式、複数検索箱、検索条件付きCSVの画面確認に成功。本計画を完了とした。
