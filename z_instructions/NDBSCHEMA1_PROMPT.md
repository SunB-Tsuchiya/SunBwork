# NDBSCHEMA1_PROMPT.md - NSystem 5年度対応DB再設計 再開用プロンプト

このファイルは、CodexまたはClaudeの新しいセッションでNDBSCHEMA1を安全に再開するためのプロンプトである。

---

## 再開時メッセージ

NSystem 5年度対応DB再設計（NDBSCHEMA1）の作業を続けます。

最初に次のファイルを確認してください。

1. Codexの場合: `/home/w229/SunBwork/AGENTS.md`
2. Claudeの場合: `/home/w229/SunBwork/CLAUDE.md`
3. `/home/w229/SunBwork/z_instructions/CODEX_CLAUDE_HANDOVER.md`
4. `/home/w229/SunBwork/z_instructions/NDBSCHEMA_PLAN1.md`
5. `/home/w229/SunBwork/z_instructions/NDBSCHEMA_MANAGER1.md`
6. `/home/w229/SunBwork/z_instructions/NSYSTEM_GUIDE.md`
7. `/home/w229/SunBwork/z_instructions/CONSOLIDATED_01_layout_and_ui.md`

`NDBSCHEMA_MANAGER1.md`の最新ステータスと作業ログを確認し、下記の「最新引き継ぎ」を前提に未完了タスクから再開してください。正規化DBと2024年度移行は承認・実装済みです。同じマイグレーションや移行を作り直さないでください。

---

## 最新引き継ぎ（2026-06-19 20:10 JST）

### 現在の状態

- ユーザー承認後、NSystem正規化DBをローカルMySQLへ作成済み。
- `2026_06_19_130001_normalize_n_system_tables.php`を適用済み。
- `2026_06_19_130002_rebuild_n_exam_daimons_fulltext_with_ngram.php`を適用済み。
- 旧3テーブルは`n_legacy_*`へrenameして全件保持している。削除禁止。
- 学校一覧、学校別問題・解答、大問遷移、全文検索は新テーブルへ切替済み。
- `2026_06_19_183000_make_n_publication_entries_one_school_one_exam.php`を適用済み。
- `n_publication_entries`は`school_id`と`exam_id`を直接持つ。`n_publication_entry_exams`は廃止済み。
- 2022～2026年ExcelのMコード・学校監査取込を実装済み。`n_publication_editions`は5件、`n_publication_entries`は894件。
- `/n-demo`一覧は選択年度のMコード昇順表示へ変更済み。年度ボタンは問題文書が登録済みの年度だけを表示するため、現状は2024のみ。
- 原則として1つの年度・Mコードは1校・1Nコードに対応する。正式例外として2025年度以降のM109だけは、同一問題を使う`4551 / 開智中学校`と`4751 / 開智所沢中等教育学校`がMコードを共有する。例外は同じM109の2行で保持済み。
- `2026 M106 4331 → 4335` は監査行に原文を残しつつ、現状運用に合わせて `4331` として登録済み。
- さくら本番へのデプロイとGitコミットは行っていない。

### ローカルDB移行結果

```text
n_legacy_schools                 159
n_schools                        148
n_school_years                   148
n_exam_series                    158
n_exams                          158（すべてadmission_year=2024）
n_exam_documents                1219
n_exam_daimons 問題              2244
n_exam_daimons 解答              2376
n_source_school_rows 未解決          1（464F）
```

旧問題は2247件だが、新問題は2244件。差の3件は学校名が`コード464F`の仮データである。学校リストには正規の`464N / 星野学園中学校`が別に存在するため、464Fを464Nへ誤統合していない。464Fの3件は`n_legacy_questions_daimon`に保持し、`n_source_school_rows`へ`unresolved`として記録した。

### 実装済みの主要ファイル

```text
database/migrations/2026_06_19_130001_normalize_n_system_tables.php
database/migrations/2026_06_19_130002_rebuild_n_exam_daimons_fulltext_with_ngram.php
app/Models/NSystem/NSchool.php
app/Models/NSystem/NSchoolYear.php
app/Models/NSystem/NExamSeries.php
app/Models/NSystem/NExam.php
app/Models/NSystem/NExamDocument.php
app/Models/NSystem/NExamDaimon.php
app/Models/NSystem/NPublicationEdition.php
app/Models/NSystem/NPublicationEntry.php
app/Models/NSystem/NImportBatch.php
app/Models/NSystem/NSourceSchoolRow.php
app/Console/Commands/NSystem/NSystemImport.php
app/Http/Controllers/NSystem/NdemoController.php
app/Services/NSystem/NQuestionSearchService.php
```

旧`NImport.php`、`NQuestionsDaimon.php`、`NAnswersDaimon.php`は削除済み。新しい取込コマンドは`php artisan n-system:import`。

### 検証済み

- Feature 9件・42 assertions、Unit 5件・10 assertions、合計14件・52 assertions成功。
- 実DBで「時代」を検索し394件、結果年度2024、試験IDを使った大問URLを確認。
- `nsystem-schema-1`を`ChangelogSeeder`へ追加し、ローカルDBへseed済み。
- Vue/JSは変更していないため、この作業では`npm run build`を実行していない。

### 次に着手できる項目

1. 年度・入試系列フィルターの画面追加。
2. 5年度横断検索の要件整理とUI実装。
3. `CODEX_CLAUDE_HANDOVER.md` の更新。
4. 必要なら さくら本番へのデプロイ、またはGitコミット。

これらは新しい追加作業である。ユーザーの指示範囲を確認してから着手すること。

### 作業ツリーの注意

- NDBSCHEMA設計3ファイルと今回のPHP・migration・テスト・ガイド変更は未コミット。
- `public/build`には今回と無関係な既存の大量差分がある。戻さない、今回のコミットへ含めない。
- `z_NDBSystem/*.xlsx`はGitへコミットしない。
- `n_legacy_*`削除はユーザーの別途明示承認が必要。

---

## 背景

SunBWorkは社内管理システムだが、`/n-demo`のNSystemはクライアント提案用の中学入試問題DBデモである。将来NSystem一式を削除・分離できることが重要である。

2024年度の既存データは、学校、入試系列、年度別試験、問題・解答へ分離済み。今後必要に応じて2022～2026年度の学校リストと年度版Mコードを取り込む。

---

## 業務ルール

### Nコード

- Nコード全体は「学校＋入試回」の長期安定コード。
- 約9割の学校は1コードのみ。
- 人気校等は第1回・第2回・特別選抜で複数コードを持つ。
- 問題セットはNコードの入試系列単位で動く。
- 先頭3文字は5年間では学校照合キーとして安定。
- 少数のコード更新があるため、Nコード自体をDB物理PKにはしない。

例:

```text
海城: 2241 第1回 / 2242 第2回
攻玉社: 1641 第1回 / 1642 第2回 / 164N 特別選抜
```

### Mコード

- 年度版書籍の掲載区分＋五十音順。
- 100番台=共学、200番台=男子、300番台=女子、500番台=地方。
- 掲載校の追加・削除、共学化で毎年変動し得る。
- Mコード単独を学校FKにしない。
- `publication_edition_id + mikuni_code`で扱う。
- Mコード掲載行へ`school_id`と`exam_id`を直接持たせ、正規化後の1掲載行を必ず1校・1Nコードにする。
- 同年度内のNコード重複は常に拒否する。
- Mコード重複は原則拒否し、2025年度以降のM109かつNコード集合`{4551, 4751}`だけを許可する。
- 原ExcelのM109複合セルは監査行へ原文保存し、正規化掲載行は同じM109の2行に分ける。
- Mコード履歴は5年度分を保存するが、学校一覧の年度ボタンは問題文書が登録済みの年度だけを表示する。現状は2024のみ。

---

## 確定設計

最終テーブル:

```text
n_schools
n_school_years
n_exam_series
n_exams
n_publication_editions
n_publication_entries
n_exam_documents
n_exam_daimons
n_import_batches
n_source_school_rows
```

旧テーブルは次へ退避済み。

```text
n_legacy_schools
n_legacy_questions_daimon
n_legacy_answers_daimon
```

旧テーブル削除はユーザーの別途明示承認が必要。

主要キー:

```text
n_schools.id                 内部学校PK
n_schools.n_code_prefix      Nコード先頭3文字の業務キー
n_exam_series.id             学校＋入試回の内部PK
n_exams.id                   年度別試験PK
n_exams.n_code               その年度のNコード全体
n_publication_entries        年度版Mコード行
                              school_idとexam_idで1校・1Nコードへ直接対応
n_exam_documents             Q/A＋科目
n_exam_daimons               大問HTML・テキスト
```

---

## 命名・分離規則

- DBはすべて`n_`、退避は`n_legacy_`。
- Controller、Middleware、Request、Model、ServiceはLaravel標準フォルダ内の`NSystem/`へ置く。
- Modelクラスは`N`始まり、Commandクラスは`NSystem`始まり。
- Commandは`app/Console/Commands/NSystem/`、コマンド名は`n-system:*`。
- Vueは`Pages/NSystem`、`Components/NSystem`、専用`NSystemDemoLayout`。
- Viewは`resources/views/n_system`、Routeは`routes/nsystem.php`。
- SunBWork業務テーブルへNSystem専用カラム・外部キーを追加しない。

---

## インポート対象

```text
z_NDBSystem/Nコードリスト2022.xlsx
z_NDBSystem/Nコードリスト2023.xlsx
z_NDBSystem/Nコードリスト2024.xlsx
z_NDBSystem/Nコードリスト2025.xlsx
z_NDBSystem/Nコードリスト2026.xlsx
storage/app/private/n_import/*.json
```

ExcelはGit管理外のローカル入力。OSメタデータファイルは無視する。

例外:

```text
2025 M109: 4551 / 4751（同一問題を使う正式共有例外）
2026 M109: 4551 / 4751（同一問題を使う正式共有例外）
2026 M106: 4331 → 4335
2024 import: 464F と学校リスト464N
2026 Excel: 2022～2025と列位置が異なる
```

例外は黙って補正せず、監査テーブルと解決記録へ残す。

---

## 実装状況

1. 完了: 現行DB件数・構造の記録。
2. 完了: 旧テーブルを`n_legacy_`へrename。
3. 一部完了: 新`n_`テーブルのmigrationとモデル。掲載学校中間テーブルは改訂承認待ち。
4. 完了: 5年度Excelの学校リスト監査インポート。
5. 完了: 464Fは未解決のまま保持し、M109共有例外と2026 M106の4331採用を実装。
6. 完了: 問題・解答JSONと5年度Mコードの統合インポート`n-system:import`。
7. 完了: 2024既存DB件数検証と5年度Excel照合。
8. 完了: Controller・検索Serviceを新構造へ切替。
9. 未着手: 年度・入試系列フィルター。
10. 完了: PHP構文、自動テスト、実DB、ngram検索確認。
11. 完了: NSYSTEM_GUIDE、ChangelogSeeder、MANAGER更新。
12. 保留: 全フェーズ未完了のため設計文書はarchiveしていない。

---

## 重要な禁止事項

- ユーザー承認前に実装しない。
- `n_legacy_*`を初期作業中に削除しない。
- Nコード全体またはMコードを`n_schools`の物理PKにしない。
- 第1回・第2回を同じ試験レコードへ統合しない。
- 学校名だけで自動的に学校を確定しない。
- Mコード500番台を学校の性別属性として扱わない。
- 元Excelの複合コード・変更表記を黙って捨てない。
- M109の承認済み例外以外で、複数Nコードを1つのMコードへ登録したり自動分割したりしない。
- Excel列を固定位置で決めず、年度ごとのヘッダー名から解決する。
- NSystem外の業務テーブルを変更しない。
- ユーザーまたは他AIの無関係な変更を戻さない。

---

## 完了時検証

```bash
docker compose exec laravel bash -lc "php artisan migrate:status"
docker compose exec laravel bash -lc "php artisan test tests/Unit/NSystem/NQuestionSearchServiceTest.php tests/Feature/NSystem/NQuestionSearchTest.php"
docker compose exec laravel bash -lc "php artisan n-system:import --help"
```

Vue/JSを変更した場合だけ`npm run build`を実行する。次の作業完了後、ユーザーから「引き継ぎ」と指示された場合は、このファイルと必要に応じて`CODEX_CLAUDE_HANDOVER.md`へJST日時付きで追記する。
