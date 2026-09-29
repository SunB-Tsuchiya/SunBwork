# MGinbon 文字校正一覧 進捗管理1

更新日: 2026-09-29

## 状態

完了。利用者による画面・保存・LIST反映・トースト確認済み。

## チェックリスト

- [x] FileMaker画面の構成確認
- [x] 既存の校正工程・日付データの対応確認
- [x] 現行取込JSONにページ数が存在しないことを確認
- [x] ページ数の保存粒度を「学校・試験回 × 区分 × 教科」と決定
- [x] 三校・四校の発注日・納品日を対象外と決定
- [x] Laravel画面からページ数を編集する方針を決定
- [x] 実装計画の利用者確認
- [x] migration・保存API
- [x] 文字校正レイアウト
- [x] 自動テスト・ビルド
- [x] 利用者による画面確認
- [x] Changelog・統合文書更新・計画文書archive

## 注意

- 本番へのSSH・migration・deployは別途、正確なコマンドを提示して明示確認を得る。
- `storage/**` の既存変更には触れない。
- ページ数取込は、FileMakerの全フィールド名または取込データが得られるまで実装しない。
- MyJobの `amounts` / `amounts_unit` との双方向連動は今回の対象外。

## 検証結果

- ローカルMGinbon DBへmigration適用済み。
- `php artisan test --filter=MGinbon`: 26 tests / 91 assertions 成功。
- `npm run build`: 成功。既知のBrowserslist・Vite glob警告のみ。
- `git diff --check`: 成功。
