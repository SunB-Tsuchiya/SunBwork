# 銀本 FileMaker型レコード検索・ナビゲーション PLAN1

作成日: 2026-10-01

## 目的

銀本進行の上部メニューをFileMakerのレコード操作に近づけ、レコード表示と検索結果を同じ「媒体レコード集合」に連動させる。

## 現状の問題

- 左上の矢印がブラウザ履歴で、銀本レコードを前後移動しない。
- 件数表示が学校ページング基準で、媒体レコード基準の現在位置を示さない。
- 上段検索は学校名・コードの単一キーワードだけ。
- FileMakerの検索モード（各欄への条件入力、検索条件追加、削除、実行、キャンセル）がない。
- 現在の学校単位ページングと媒体優先ソートでは、媒体レコード集合の通し番号を正確に扱えない。

## 確定仕様案

### 1. レコード表示

- レコードの単位は `mginbon_items`（学校・試験回×媒体）。
- 検索結果は既存の媒体優先順:
  1. 問題
  2. 解答のみ
  3. 解説解答
  4. 解答用紙
  5. 傾向と対策
  6. 旧互換の解答
- 各媒体内は、みくにコードの数値順、コード文字列、学校名、item ID順。
- 左右矢印は前後の媒体レコードへ移動する。
- 現在番号を直接入力して該当レコードへ移動できる。
- 分母は現在の検索結果に一致する媒体レコード総数。
- 単票表示は選択中の1媒体、連続表示・表形式は検索結果ページを表示。
- 「すべてを表示」は検索条件を解除し、全媒体レコード集合へ戻す。

### 2. FileMaker型検索モード

- 「検索」を押すと閲覧表示を検索条件入力表示へ切り替える。
- 識別欄と各工程セルを空欄の検索入力にする。
- 同一検索条件内の入力欄はAND。
- 「新規検索条件」で条件箱を追加し、複数条件箱はOR。
- 「検索条件削除」で選択中の条件箱を削除する。最低1箱は維持。
- 「検索実行」でサーバー検索し、先頭レコードを選択する。
- 「検索のキャンセル」で検索前の閲覧状態へ戻る。
- 初期フェーズでは「含める」条件を実装する。FileMakerの除外条件・演算子メニューは後続調整可能な構造にする。

### 3. 検索対象

- みくにコード
- 日能研コード
- 分類
- 学校名
- 媒体
- 科目
- 銀本掲載
- 備考
- 工程日付（コード×教科）
- 工程担当者（工程コード×教科）
- 日付は `YYYY-MM-DD` と `MM/DD` 入力を受け、JSTの日付値として比較する。
- 文字列は部分一致、コード・媒体・科目は選択肢も提供する。

### 4. URLと状態

- 検索条件はJSONを直接URLへ載せず、検証可能な配列パラメータとして送る。
- 表示モード、選択レコード番号、ページ、年度をURLクエリへ保持する。
- 年度やレイアウト変更時は検索条件を維持できる範囲で維持する。
- 不正・過大な条件数や値はLaravelバリデーションで拒否する。

## 影響予定ファイル

- `app/Http/Controllers/Coordinator/MGinbonLedgerController.php`
- `app/Services/MGinbon/MGinbonLedgerSearch.php`（新規）
- `resources/js/Pages/Coordinator/MGinbon/LedgerIndex.vue`
- `resources/js/Components/MGinbon/LedgerFindLayout.vue`（新規）
- `resources/js/Components/MGinbon/LedgerRecordNavigator.vue`（新規、必要なら）
- `tests/Feature/MGinbonLedgerSearchTest.php`（新規）
- `database/seeders/ChangelogSeeder.php`
- `z_instructions/CONSOLIDATED_09_domain_rules.md`
- 完了時に本PLAN・MANAGER・PROMPTを `z_instructions/archived/` へ移動

## 実装フェーズ

1. 媒体レコード基準の検索・件数・位置解決サービスとテスト。
2. 上部メニューのレコードナビゲーションを置換。
3. FileMaker型検索モードと複数条件箱を追加。
4. LISTの識別欄・教科別工程欄を検索入力へ切替。
5. 各専用レイアウトから共通検索結果集合を利用。
6. ビルド、銀本テスト、手動確認、文書・変更履歴更新。

## 保護・禁止事項

- 既存未コミット変更を戻さない。
- `storage/**` を変更・コミットしない。
- 新規migrationは想定しない。
- Sakura SSH・migration・seed・deployは正確なコマンドを提示し、利用者の明示確認まで実行しない。
- 本番データを調査しない。テストは合成fixtureを使う。

## 検証

- 検索対象ごとのFeature Test。
- AND条件、OR条件、0件、全件解除、媒体優先順、現在位置解決のテスト。
- `docker compose exec laravel bash -lc "php artisan test --filter=MGinbon"`
- `npm run build`
- `git diff --check`
- 利用者によるFileMaker画像との画面比較。
