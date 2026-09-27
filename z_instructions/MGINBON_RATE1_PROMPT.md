# MGinbon 年度別外注単価マスター 再開プロンプト1

`z_instructions/MGINBON_RATE_PLAN1.md` と `z_instructions/MGINBON_RATE_MANAGER1.md` を読み、利用者承認後に年度別外注単価マスターを実装してください。

## 現在地

- 出稿表まで実装・利用者確認済み。
- 次工程はR5の年度×外注先×作業種別単価。
- 初版設計は作図／スキャンの点単価と税抜参考金額。
- 税、丸め、締め、支払確定は未確定のため実装しない。
- 単価未設定を0円扱いせず、他年度から自動流用しない。
- `mginbon_work_measurements`の単価スナップショット列は将来確定用にnullableで準備するが、初版では自動確定保存しない。
- FileMakerの単価登録・金額帳票画像が入手できれば帳票列の確定に使う。
- Sakuraへはデプロイしない。

## 実装前の必須確認

1. 利用者がPLAN1を承認済みであること。
2. 作業ツリーの既存変更を保護すること。
3. MGinbonテストは `/tmp` の一時SQLiteを使用し、ローカル開発DBを破壊しないこと。
4. 新ページは `CONSOLIDATED_01_layout_and_ui.md` のAppLayout規約に従うこと。
