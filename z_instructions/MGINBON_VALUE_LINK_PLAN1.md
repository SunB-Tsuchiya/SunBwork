# MGinbon 値一覧・実画面連動 計画1

作成日: 2026-09-26
状態: 設計完了・実装前

## 1. 目的

年度別の13種類の値一覧を、単独の設定画面で終わらせず、LIST、詳細編集、取込、出稿表、工程担当の各入力候補へ接続する。無効化した値は新規選択肢から除外するが、保存済みの過去値は表示し続ける。

## 2. 共通ルール

- 候補取得は年度プロジェクトIDと一覧コードを必須にする。
- 画面へ渡すのは `is_active=true` の値だけ。現在保存中の値が無効になった場合は、その値だけ末尾へ「使用停止」として補う。
- 入力時はサーバー側でも同年度の有効候補か検証する。自由入力を許す欄は候補外警告に留める。
- 値の削除は物理削除せず無効化し、履歴表示と既存データを維持する。
- 年度コピーは値、順番、有効状態、社員・外注先リンクを複製する。
- 13一覧を直接個別クエリせず、共通の `MGinbonValueOptions` サービスから取得する。

## 3. 13項目の接続先

| 一覧コード | 正本・保存先 | 入力・表示先 | 接続方法 |
| --- | --- | --- | --- |
| `checker` | 値一覧＋社員/外注リンク | 初校チェック等のチェック工程 | `initial_check` などチェック工程の担当候補を絞る |
| `drawing_text_operator` | 値一覧＋社員/外注リンク | 文字入力・作図担当、LIST、出稿表 | `text_input`、`drawing` の担当候補を絞る |
| `composition_operator` | 値一覧＋社員/外注リンク | 初校・再校・三校以降のOP、LIST、出稿表 | operation系工程の担当候補を絞る |
| `original_scan_operator` | 値一覧＋社員/外注リンク | 原本scan担当 | scan工程・発注書の候補を絞る |
| `original_image_replacement` | `mginbon_items.original_image_replacement_status`（追加） | LIST・詳細 | 年度値一覧のselect |
| `media_category` | `mginbon_media_types` が正本 | LIST絞込、取込、媒体表示 | 値一覧を許可候補、正規media IDを保存。名称変更で既存IDを壊さない |
| `school_category` | `mginbon_production_units.school_category` | 詳細・LIST・取込 | 年度値一覧のselect |
| `payment_month` | `mginbon_items.payment_month`（追加） | LIST・詳細・集計 | 年度値一覧のselect |
| `proofreader` | 値一覧＋社員/外注リンク | 初校校正・再校校正・赤字照合 | proof系工程の担当候補を絞る |
| `nichinoken_category` | `mginbon_production_units.n_category` | 詳細・LIST・取込・帳票 | 年度値一覧のselect |
| `subject` | `mginbon_subjects`＋`mginbon_item_subjects` が正本 | 媒体の教科構成、取込 | 値文字列を4教科コードの組合せプリセットへ対応付ける |
| `answer_availability` | `mginbon_items.answer_availability`（追加） | LIST・詳細・出稿表右上 | 学校／媒体ごとのselect。帳票一括値では上書きしない |
| `ginbon_publication` | `mginbon_items.publication_status` | 詳細・LIST・出稿表 | 年度値一覧のselect |

## 4. 担当者系の設計

- 値一覧行に既存の `linked_user_id` または `linked_subcontractor_id` を設定するUIを追加する。
- 文字列だけの値は旧FileMaker表示・移行確認用として保持するが、新しい工程担当としては保存しない。
- 候補は「接続案件のチームメンバー・外注先」と「該当一覧の有効リンク」の積集合にする。
- 一人が組版と校正を兼任できるよう、同じユーザーを複数一覧へリンク可能にする。
- リンク未設定の移行期間は候補欠落を避けるため、現行の全チーム候補を表示し「区分未設定」と明示する。設定が一件でもある工程区分から厳格絞込へ移行する。

## 5. 実装段階

1. 共通候補取得サービス、削除値の除外、値一覧リンクUI
2. 詳細画面の学校分類・日能研分類・銀本掲載をselect化
3. 不足カラム3種を追加し、詳細・LIST・出稿表へ接続
4. media/subject正規マスターとの対応付けと取込検証
5. 工程種別ごとの担当候補絞込をLIST・詳細・出稿表で共通化
6. 全13項目のFeature test、既存値・無効値・年度分離・年度コピーを検証

## 6. 非破壊要件

- 値一覧の名称変更だけで既存の媒体ID、教科ID、担当割当を更新しない。
- 無効値を保存済みレコードから消さない。
- 年度Aの編集を年度Bへ反映しない。
- 出稿表の「空欄で印刷」時は値一覧や保存値を更新しない。
- 候補区分を設定しても既存の正式担当・JobBox割当を自動変更しない。
