# 銀本 ファイル名一括工程登録 PLAN1

作成日: 2026-10-02
状態: 完了（2026-10-03）

## 1. 目的

数十件の出稿PDFファイル名をテキスト欄へ貼り付け、対象の年度・Nコード・媒体・教科を自動判定して、選択した1工程の日付を銀本LISTへ一括登録する。登録前に必ずpreviewを表示し、誤登録と意図しない上書きを防ぐ。

## 2. 固定ファイル名仕様

```text
NNNNYYYY__MEDIA_SUBJECT.pdf
実例: 30812026__AASh.pdf
```

実際の区切りは `NNNNYYYY__` に続けて媒体記号と教科記号を連結する。上記例はNコード3081、2026年度、解説解答、社会。

### 媒体記号

| 記号 | 媒体 |
| --- | --- |
| `Q` | 問題 |
| `A` | 解答のみ |
| `AA` | 解説解答 |
| `Y` | 解答用紙 |
| `T` | 傾向と対策 |

`AA` を `A` より先に判定する。`m` は使用しない。

### 教科記号

| 記号 | 教科コード |
| --- | --- |
| `Ko` | `japanese` |
| `Sa` | `math` |
| `Sh` | `social` |
| `Ri` | `science` |

### 解析規則

- 前後空白とWindowsのフルパスは許容し、basenameを解析する。
- 拡張子 `.pdf` は大文字小文字を問わない。本体の記号は上記固定表記とする。
- 完全一致の解析パターンを使い、部分一致で誤判定しない。
- 同一の年度・Nコード・媒体・教科が複数行ある場合は重複としてpreviewで通知する。

## 3. 初期対象工程12項目（1回に1つ選択）

| 表示名 | milestone code |
| --- | --- |
| 初校出 | `initial_shared_on` |
| 初校校正入 | `initial_text_proof_started_on` |
| 初校校正UP | `initial_text_proof_completed_on` |
| 再校出 | `reproof_shared_on` |
| 校正①校正入 | `reproof_scan_check_started_on` |
| 校正①校正UP | `reproof_scan_check_completed_on` |
| 校正②校正入 | `reproof_text_proof_started_on` |
| 校正②校正UP | `reproof_text_proof_completed_on` |
| 三校出 | `third_shared_on` |
| 四校出 | `fourth_shared_on` |
| 五校出 | `fifth_shared_on` |
| 校了 | `completed_on` |

工程日、対象工程、上書き方針はバッチ全体の共通値とする。ファイルごとの工程指定は実装しない。再校の校正①・校正②を別々に指定できるため、初期の日付登録項目は合12件とする。

## 4. UI

- 銀本上部メニューに「ファイル名一括登録」を追加。
- 年度は表示中の銀本年度を使う。ファイル名の年度と異なる行はエラーにする。
- 工程選択、日付（カレンダー＋直接入力）、上書き許可、複数行textareaを表示。
- ブラウザーのフォルダ選択（`webkitdirectory`）、PDF複数選択、ドラッグ＆ドロップからファイル名をtextareaへ取り込む。PDF本体はサーバーへ送信しない。
- 取込時はPDF以外を除外し、既存行を含む同一ファイル名を重複追加しない。フォルダ選択非対応環境ではPDF複数選択と貼り付けを代替手段とする。
- preview表に元ファイル名、学校名、Nコード、媒体、教科、工程、現在値、登録値、判定を表示。
- previewを経由しない登録は許可しない。preview後に入力を変更した場合は再previewを必須とする。
- 結果は「登録可能」「同じ日付を登録済み」「別の日付あり」「対象なし」「形式エラー」「重複」に分類。
- 初期値では既存値を上書きしない。「既存日付を上書き」を選択した場合のみ更新対象にする。

## 5. サーバー処理と安全性

- 新規DBテーブルとmigrationは追加しない。
- 解析・対象解決を `MGinbonFilenameBulkService` に集約し、previewとcommitで同じロジックを使う。
- Nコード、年度、媒体名、教科の4要素が一意に一致する場合だけ登録可能とする。
- commit時も再解析・再照合し、preview結果をクライアントから信頼しない。
- `mginbon` 接続のトランザクション内で対象itemをlockし、milestoneをupsertする。
- 更新した各セルを `mginbon_change_logs` へ `source=manual` で記録する。
- 年度のProjectJob接続確認と既存 `MGinbonProjectAccess::requireLinked()` を利用する。
- 1回の入力は200行を上限とし、空行は除外する。
- 一部エラー行は更新せず、登録可能行だけを処理する。登録中の例外はバッチ全体をrollbackする。

## 6. 予定ファイル

- `app/Services/MGinbon/MGinbonFilenameBulkService.php` 新規
- `app/Http/Controllers/Coordinator/MGinbonFilenameBulkController.php` 新規
- `resources/js/Components/MGinbon/FilenameBulkMenu.vue` 新規
- `resources/js/Pages/Coordinator/MGinbon/LedgerIndex.vue`
- `routes/web.php`
- `tests/Unit/MGinbon/MGinbonFilenameBulkServiceTest.php` 新規
- `tests/Feature/MGinbonFilenameBulkTest.php` 新規
- `z_instructions/CONSOLIDATED_09_domain_rules.md`
- `database/seeders/ChangelogSeeder.php`

## 7. 実装フェーズ

1. 固定ファイル名parserと単体テスト
2. preview APIと対象照合
3. トランザクション一括登録・変更履歴
4. 一括登録UIと二段階確認
5. 統合テスト・Vue build・利用者画面確認
6. 統合文書・Changelog更新、完了後archivedへ移動

## 8. 検証項目

- `Q/A/AA/Y/T` と `Ko/Sa/Sh/Ri` の全組み合わせを誤認識しない。
- `30812026__AASh.pdf` が2026年度・Nコード3081・解説解答・社会になる。
- フルパス、CRLF、空行、重複行、不正記号、別年度を適切に分類する。
- 対象なし・媒体なし・教科なしを個別表示する。
- 上書きOFFで既存値を保護し、ONでのみ別日付を更新する。
- 対象教科以外のmilestoneを変更しない。
- ロック中の再照合と変更履歴が正しい。
- preview後の入力変更でcommitを無効化する。
- 登録後のLIST再読込みで日付が反映される。

## 9. 本番反映

Sakura SSH・migration・seed・deployは、正確なコマンドを利用者へ提示し、明示確認を得るまで実行しない。本計画ではmigration追加を予定しない。
