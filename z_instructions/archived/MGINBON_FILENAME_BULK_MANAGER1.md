# 銀本 ファイル名一括工程登録 MANAGER1

更新日: 2026-10-03
状態: 完了

## チェックリスト

- [x] ファイル名固定形式の確認
- [x] 媒体記号 `Q/A/AA/Y/T` の確定
- [x] 教科記号 `Ko/Sa/Sh/Ri` の確定
- [x] 初期工程10件の承認
- [x] PLAN1・MANAGER1・PROMPT1作成
- [x] 再校校正入／UPを校正①・校正②の別項目として確定
- [x] 利用者によるPLAN1承認
- [x] Phase 1 parser
- [x] Phase 2 preview API
- [x] Phase 3 commit API・履歴
- [x] Phase 4 UI
- [x] Phase 5 統合検証・画面確認
- [x] Phase 6 文書・Changelog
- [x] 完了資料をarchivedへ移動

## 保護事項

- 既存未コミット変更と `storage/**` を変更・復元しない。
- Sakura SSH・migration・seed・deployは明示確認前に実行しない。
- 初期実装で新規DBテーブルは作成しない。

## 作業ログ

### 2026-10-02

- 利用者より、数十件の出稿ファイル名からLIST工程日を一括登録する要望。
- 例 `30812026__AASh.pdf` はNコード3081、2026年度、解説解答、社会と確認。
- 問題記号は `m` ではなく `Q`。解答用紙は `Y`。
- 工程と1日付をバッチ共通とし、preview後に登録可能行だけを反映する単純な構成とした。
- 新規migrationなしで、既存milestoneとchange logを使う計画。
- LISTの校正列名を「初校校正／初校校正入／初校校正UP」「校正①校正／校正①校正入／校正①校正UP」「校正②校正／校正②校正入／校正②校正UP」へ明確化し、工程セル幅を4.5remへ拡張。
- 一括登録項目は校正①・校正②を別々に指定する12項目とし、LIST表示名と一致させる。
- `MGinbonFilenameBulkService` を追加し、固定ファイル名解析、対象の一意照合、重複・年度相違・既存値の分類をpreviewとcommitで共通化。
- commitではitemをlockして現在値を再確認し、対象教科のmilestoneと `mginbon_change_logs` だけをトランザクション更新。
- LIST上部に「一括登録」を追加。工程・日付・上書き方針・ファイル一覧を入力し、行ごとの解析結果を確認してから登録できるようにした。
- ローカルフォルダ選択、PDF複数選択、ドラッグ＆ドロップを追加。ブラウザー内でPDF名だけを抽出し、本体をSakuraへ送信しない方式とした。
- 専用9 tests / 49 assertions、MGinbon全47 tests / 200 assertions、Vue本番build成功。migration・seed・Sakura操作は未実行。

### 2026-10-03

- 利用者がフォルダ選択とファイル名取込を画面確認し、「ばっちり」と確認。
- 媒体記号が問題・解答のみ・解説解答・解答用紙・傾向と対策の混在でも、各ファイル名から個別に解析されることを確認。
- 統合仕様とChangelogSeederを更新し、完了資料をarchivedへ移動。
