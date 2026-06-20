# NDBSCHEMA_PLAN1.md - NSystem 5年度対応DB再設計書

> 作成: 2026-06-19 12:57:29 JST (+0900)  
> 対象: クライアント提案用中学入試問題DBデモ `/n-demo`  
> 状態: 2024正規化移行済み・5年度Mコード取込と`/n-demo`年度UIをローカル実装済み

---

## 1. 目的

現在のNSystemは2024年度のみを対象とし、`n_schools` が「学校」と「年度別入試コード」の両方を兼ねている。これを2022～2026年度の5年分へ拡張し、次の分析を可能にする。

- 同一学校の複数年度をまとめて検索する
- 第1回、第2回、特別選抜などの入試系列を区別する
- 学校名変更前後を同じ学校として集計する
- 年度ごとに変動するMコードと掲載有無を履歴として保存する
- 「同じ学校で過去5年間に世界大戦を扱った問題が何問あったか」を集計する
- NSystemをSunBWork本体から将来まとめて切り離せる状態を維持する

この設計は5年度分のデモを完成させるためのもので、20年度分を前提とした過剰な基盤は作らない。ただし、同じ構造のまま年度を追加できるようにする。

---

## 2. 元データ調査

### 2.1 調査対象

```text
z_NDBSystem/Nコードリスト2022.xlsx
z_NDBSystem/Nコードリスト2023.xlsx
z_NDBSystem/Nコードリスト2024.xlsx
z_NDBSystem/Nコードリスト2025.xlsx
z_NDBSystem/Nコードリスト2026.xlsx
```

OS付随メタデータである `:Zone.Identifier`、`:AFP_AfpInfo`、`:com.apple.*` は学校データとして使用しない。

### 2.2 年度別掲載数

| 入試年度 | 掲載行 | 100番台 共学 | 200番台 男子 | 300番台 女子 | 500番台 地方 |
| --- | ---: | ---: | ---: | ---: | ---: |
| 2022 | 182 | 60 | 44 | 59 | 19 |
| 2023 | 179 | 61 | 44 | 55 | 19 |
| 2024 | 177 | 61 | 44 | 53 | 19 |
| 2025 | 177 | 61 | 44 | 53 | 19 |
| 2026 | 177 | 63 | 44 | 51 | 19 |

### 2.3 Nコードの意味

Nコード全体は「学校＋入試回」の安定した業務コードである。

```text
海城中学校
  2241 = 第1回
  2242 = 第2回

攻玉社中学校
  1641 = 第1回
  1642 = 第2回
  164N = 特別選抜
```

約9割の学校は1系列のみで、一部の人気校が複数系列を持つ。この複数系列は偶発的な重複ではなく、問題セットを区別するために必要な固定ルールである。

Nコード先頭3文字は、確認した5年間では学校系列の照合キーとして非常に安定している。

```text
201 = 三田国際学園中学校 → 三田国際科学学園中学校
274 = 明治大学付属中野八王子中学校 → 明治大学付属八王子中学校
```

同じ正規化学校名が年度間で別の先頭3文字へ移った例は確認されなかった。

ただし、Nコード全体には少数の更新例がある。

```text
城北埼玉中学校       4521 → 4524
東京都市大学付属中学校 196G → 1961
東京農業大学第一       2952 → 2951
江戸川学園取手         4331 → 4335
```

したがってNコードは重要な業務キーだが、DBの物理主キーにはせず、内部IDに紐づく履歴付きコードとして管理する。

### 2.4 Mコードの意味

Mコードは年度版書籍における掲載区分と五十音順の位置であり、学校の恒久IDではない。

- 100番台: 共学掲載セクション
- 200番台: 男子校掲載セクション
- 300番台: 女子校掲載セクション
- 500番台: 地方校掲載セクション
- 掲載校の追加・削除で後続番号が連鎖的に変わる
- 共学化した場合は掲載セクションも移動する

継続して登場する169のNコード先頭3文字のうち、42系列（24.9%）でMコード構成が5年間に一度以上変わっていた。

Mコードは年度版の掲載順であり、恒久学校IDや試験IDの代用にはしない。原則として各年度の1つのMコードは1校・1つのNコードに対応する。ただし2025年度以降のM109だけは、同一問題を使用する`4551 / 開智中学校`と`4751 / 開智所沢中等教育学校`の2校・2Nコードが同じMコードを共有する正式例外である。年度版の掲載行に`school_id`と`exam_id`を直接持たせ、年度・Mコード・学校・Nコードの組をDBで明示する。

### 2.5 学校名変更・表記差

確認できた主な変更:

```text
2011: 三田国際学園中学校 → 三田国際科学学園中学校
2741: 明治大学付属中野八王子中学校 → 明治大学付属八王子中学校
1896: 鴎友学園女子中学校 → 鷗友学園女子中学校
```

学校名セルには申請方法・料金・著作権等のメモが混在する年度がある。学校名と運用メモを分離し、元セルは監査用にそのまま保存する。

### 2.6 例外データ

- 2025・2026年度のM109は `4551 / 4751` と2つのNコード・2校を1原本行に含む正式例外。監査行は原文のまま保存し、正規化掲載行は同じM109を持つ2行へ分割する
- 2026年度の江戸川学園取手は `4331 → 4335` と変更前後が同じセルに入る
- 2022～2025年度と2026年度ではExcel列位置が異なるため、列記号ではなく正規化したヘッダー名で列を解決する
- 現在の2024インポートには学校リストにない `464F` があり、学校リスト側は `464N`
- 現在の2024インポートは159件、学校リストは177件
- 2024学校リストの地方校19件は現行インポートに含まれていない

インポート処理では文字列を黙って1コードへ決めず、例外として検出・報告し、必要なものは明示的な対応表で解決する。

---

## 3. 命名・分離規約

### 3.1 DB

NSystemのテーブルはすべて `n_` で始める。

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

旧構造を一時保存する場合も `n_legacy_` を使う。

```text
n_legacy_schools
n_legacy_questions_daimon
n_legacy_answers_daimon
```

一般テーブルへNSystem固有カラムを追加しない。NSystemの外部キーからSunBWork業務テーブルを参照しない。

### 3.2 PHP / Laravel

| 種別 | 配置・命名 |
| --- | --- |
| Controller | `app/Http/Controllers/NSystem/` |
| Middleware | `app/Http/Middleware/NSystem/` |
| Request | `app/Http/Requests/NSystem/` |
| Model | `app/Models/NSystem/`、クラス名は `N` 始まり |
| Service | `app/Services/NSystem/` |
| Command | `app/Console/Commands/NSystem/`、クラス名は `NSystem` 始まり |
| Feature test | `tests/Feature/NSystem/` |
| Unit test | `tests/Unit/NSystem/` |

マイグレーションはLaravel標準の `database/migrations/` 直下に置き、ファイル名と作成テーブルを必ず `n_` にする。

### 3.3 Frontend / View / Route

```text
resources/js/Pages/NSystem/
resources/js/Components/NSystem/
resources/js/layouts/NSystemDemoLayout.vue
resources/views/n_system/
routes/nsystem.php
```

ルート名は `n-demo.*`、ゲスト認証は `n-guest.*` を維持する。

---

## 4. 最終テーブル設計

### 4.1 `n_schools` - 学校の恒久マスター

```text
id                  BIGINT PK
n_code_prefix       VARCHAR(3) UNIQUE NOT NULL
canonical_name      VARCHAR(200) NOT NULL
prefecture          VARCHAR(20) NULL
is_active           BOOLEAN DEFAULT TRUE
merged_into_id      BIGINT NULL FK -> n_schools.id
created_at
updated_at
```

- `id`をすべての学校外部キーに使用する
- `n_code_prefix`は学校照合用の業務キー
- 名称変更では同じ`id`を維持する
- 合併等で別学校として扱う場合は新しい`id`を作り、`merged_into_id`で関係を残す

### 4.2 `n_school_years` - 年度別学校スナップショット

```text
id                  BIGINT PK
school_id           BIGINT FK -> n_schools.id
admission_year      SMALLINT UNSIGNED
school_name         VARCHAR(200)
normalized_name     VARCHAR(200)
gender_type         VARCHAR(10) NULL  // coed, boys, girls, unknown
prefecture          VARCHAR(20) NULL
notes               TEXT NULL
created_at
updated_at

UNIQUE(school_id, admission_year)
INDEX(admission_year, normalized_name)
```

- 名称変更と共学化を年度単位で保存する
- Mコード500番台は「地方掲載」であり性別ではないため、`gender_type`へ自動変換しない
- 元資料だけで性別を確定できない場合は`unknown`

### 4.3 `n_exam_series` - 学校＋入試回の系列

```text
id                  BIGINT PK
school_id           BIGINT FK -> n_schools.id
series_key          VARCHAR(50) NOT NULL
canonical_label     VARCHAR(100) NULL
is_active           BOOLEAN DEFAULT TRUE
created_at
updated_at

UNIQUE(school_id, series_key)
```

例:

```text
school=海城, series_key=first,  canonical_label=第1回
school=海城, series_key=second, canonical_label=第2回
school=攻玉社, series_key=special, canonical_label=特別選抜
```

`series_key`はNコード末尾から機械的に決めず、年度別入試名と既存コードを基に固定する。

### 4.4 `n_exams` - 年度別の実施試験

```text
id                  BIGINT PK
exam_series_id      BIGINT FK -> n_exam_series.id
admission_year      SMALLINT UNSIGNED
n_code              VARCHAR(10) NOT NULL
exam_label          VARCHAR(200) NULL
source_notes        TEXT NULL
created_at
updated_at

UNIQUE(exam_series_id, admission_year)
UNIQUE(admission_year, n_code)
INDEX(admission_year, n_code)
```

- Nコード全体はここで年度別に保持する
- コード変更時も同じ`exam_series_id`を維持できる
- 2026年の `4331 → 4335` は監査行へ原文を保持しつつ、現状運用に合わせて2026年レコードも`4331`で登録する

### 4.5 `n_publication_editions` - 年度版書籍

```text
id                  BIGINT PK
admission_year      SMALLINT UNSIGNED UNIQUE
title               VARCHAR(200)
source_filename     VARCHAR(255)
created_at
updated_at
```

### 4.6 `n_publication_entries` - Mコード掲載行

```text
id                       BIGINT PK
publication_edition_id   BIGINT FK -> n_publication_editions.id
school_id                BIGINT FK -> n_schools.id
exam_id                  BIGINT FK -> n_exams.id
mikuni_code              SMALLINT UNSIGNED
publication_section      VARCHAR(20) // coed, boys, girls, regional
sort_order               SMALLINT UNSIGNED
printed_school_name      VARCHAR(300)
printed_exam_label       VARCHAR(200) NULL
source_row_number        SMALLINT UNSIGNED NULL
source_notes             TEXT NULL
created_at
updated_at

UNIQUE(publication_edition_id, mikuni_code, exam_id)
UNIQUE(publication_edition_id, exam_id)
INDEX(publication_edition_id, publication_section, sort_order)
INDEX(school_id, publication_edition_id)
```

- 正規化後の1掲載行は必ず1校・1試験に対応する
- Mコード重複は原則禁止だが、2025年度以降のM109で`4551`と`4751`の2行だけを許可する
- `exam_id`から年度別Nコードを取得する。Nコード文字列を重複保存しない
- `school_id`は掲載学校を明示し、`exam_id → exam_series → school_id`と一致させる
- `UNIQUE(publication_edition_id, exam_id)`により、同じ年度版で同じNコードの試験を複数Mコードへ登録できないようにする
- `publication_section`は書籍上の区分であり、学校の恒久属性ではない
- 一覧の正規順序は`mikuni_code`昇順とする。`sort_order`は原本の物理行順を監査・再現するため別に保持する

### 4.7 `n_exam_documents` - 問題・解答ファイル

```text
id                  BIGINT PK
exam_id             BIGINT FK -> n_exams.id
subject             VARCHAR(5) // Ko, Sa, Sh, Ri
document_type       CHAR(1)    // Q, A
source_filename     VARCHAR(255) NULL
created_at
updated_at

UNIQUE(exam_id, subject, document_type)
INDEX(subject, document_type)
```

### 4.8 `n_exam_daimons` - 大問本文

```text
id                  BIGINT PK
exam_document_id    BIGINT FK -> n_exam_documents.id
daimon_index        TINYINT UNSIGNED
body_html           LONGTEXT
body_text           TEXT
created_at
updated_at

UNIQUE(exam_document_id, daimon_index)
FULLTEXT(body_text) WITH PARSER ngram
```

問題と解答を`document_type`で分けることで、同じ検索・表示基盤を共有しつつ、検索対象を問題だけに制限できる。

年度は`n_exams.admission_year`を唯一の正とし、大問ごとに重複保存しない。検索時は`n_exam_daimons → n_exam_documents → n_exams`で年度を絞り込む。

### 4.9 インポート監査

```text
n_import_batches
- id
- import_type
- source_filename
- source_year
- file_hash
- imported_at
- status
- summary_json

n_source_school_rows
- id
- import_batch_id
- source_row_number
- admission_year
- raw_mikuni_code
- raw_n_code
- raw_school_name
- raw_exam_label
- parsed_json
- resolution_status
- resolution_notes
```

元Excelの表記、メモ、複合コードを失わず、どの判断でマスターへ紐付けたか追跡できるようにする。

---

## 5. Eloquentモデル

```text
app/Models/NSystem/
  NSchool.php
  NSchoolYear.php
  NExamSeries.php
  NExam.php
  NPublicationEdition.php
  NPublicationEntry.php
  NExamDocument.php
  NExamDaimon.php
  NImportBatch.php
  NSourceSchoolRow.php
```

主要リレーション:

```text
NSchool
  hasMany NSchoolYear
  hasMany NExamSeries

NExamSeries
  belongsTo NSchool
  hasMany NExam

NExam
  belongsTo NExamSeries
  hasOne NPublicationEntry
  hasMany NExamDocument

NPublicationEdition
  hasMany NPublicationEntry

NPublicationEntry
  belongsTo NPublicationEdition
  belongsTo NSchool
  belongsTo NExam

NExamDocument
  belongsTo NExam
  hasMany NExamDaimon
```

モデルの`$table`は必ず明示し、NSystem外のモデルへリレーションを追加しない。

---

## 6. 検索・集計経路

「同じ学校の2022～2026年度で『世界大戦』を含む問題」を検索する経路:

```text
n_schools
  → n_exam_series
  → n_exams（admission_year BETWEEN 2022 AND 2026）
  → n_exam_documents（document_type = Q）
  → n_exam_daimons（body_textに世界大戦）
```

集計単位を明示的に選べるようにする。

- 該当大問数: `COUNT(n_exam_daimons.id)`
- 該当試験数: `COUNT(DISTINCT n_exams.id)`
- 該当年度数: `COUNT(DISTINCT n_exams.admission_year)`

画面の学校選択は`n_schools.id`を使い、NコードやMコードを直接送信しない。

---

## 7. インポート規則

### 7.1 学校リスト

1. ヘッダー文字列の空白・改行を正規化し、`みくにコード`、`日能研コード`、`学校名`、`入試回`の列を名前で解決する
2. Excelを`n_import_batches`と`n_source_school_rows`へ原文保存
3. Nコードを単純コード、複合コード、変更表記に分類する。複合コードは原則エラーとし、2025年度以降のM109にある`4551 / 4751`だけを承認済み例外として扱う
4. Nコード先頭3文字で学校候補を取得し、学校名は照合確認にのみ使う
5. 年度別名称を`n_school_years`へ保存
6. Nコード全体と入試回を`n_exam_series` / `n_exams`へ対応
7. `年度版＋Mコード`を`n_publication_entries`へ保存
8. 掲載行の`school_id`と`exam_id`へ、確認済みの1校・1試験を直接紐付ける。M109例外は同じMコードの2掲載行へ正規化する
9. 同じ年度内でNコードが重複した場合は常に取込エラーにする。Mコード重複は原則エラーとし、2025年度以降のM109かつNコード集合が完全に`{4551, 4751}`の場合だけ許可する
10. 未解決行が1件でもあれば警告を出し、件数を管理ファイルへ記録する。未解決行の掲載原文は保持するが、推測で学校FK・試験FKを付けない

### 7.2 JSON問題・解答

ファイル名 `{Nコード4桁}{年度4桁}__{Q|A}{Ko|Sa|Sh|Ri}.json` から次を解決する。

```text
Nコード＋年度 → n_exams
Q/A＋科目 → n_exam_documents
daimon_index → n_exam_daimons
```

学校名やMコードから問題を紐付けない。

### 7.3 例外対応表

自動解決不能な行はコードへハードコードせず、NSystem専用の設定JSONまたはDB解決記録へ置く。

例:

```text
2025 M109: 4551 / 4751（正式例外。2つの正規化掲載行へ分割）
2026 M106: 4331 → 4335
2026 M109: 4551 / 4751（正式例外。2つの正規化掲載行へ分割）
2024 import: 464F と学校リスト464Nの差
```

---

## 8. 旧テーブルからの移行

データは再生成可能だが、確認前に削除しない。次の段階移行を行う。

### Phase A: 退避

```text
n_schools            → n_legacy_schools
n_questions_daimon   → n_legacy_questions_daimon
n_answers_daimon     → n_legacy_answers_daimon
```

旧テーブルも`n_legacy_`でまとまり、SunBWork本体と混ざらない。

### Phase B: 新構造作成

- 新しい`n_*`テーブルを作成
- 2022～2026学校リストをインポート
- 利用可能な年度別JSONをインポート
- 件数と例外を検証

### Phase C: アプリ切り替え

- Controller、Service、検索、学校画面を新モデルへ切り替える
- URL互換性が必要な場合は旧IDではなく新しい`school_id`へ誘導する
- 検索結果に年度を表示し、年度フィルターを追加する
- 学校一覧の年度ボタンは、Mコード掲載行に紐付いた試験のうち問題文書が登録済みの年度だけを表示する
- 選択年度のカードは`n_publication_entries.mikuni_code`昇順とし、URLの`year`クエリに選択年度を保持する
- Mコード履歴自体は問題本文の有無にかかわらず5年度分をDBへ保持する

### Phase D: 旧テーブル削除

新旧件数と画面をユーザーが確認した後、別の明示承認を得て`n_legacy_*`を削除する。削除を同じ初期マイグレーション内で自動実行しない。

---

## 9. 変更予定ファイル

### 新規

```text
database/migrations/*_rename_n_tables_to_n_legacy.php
database/migrations/*_create_n_schools_table_v2.php
database/migrations/*_create_n_school_years_table.php
database/migrations/*_create_n_exam_series_table.php
database/migrations/*_create_n_exams_table.php
database/migrations/*_create_n_publication_tables.php
database/migrations/*_make_n_publication_entries_one_school_one_exam.php
database/migrations/*_create_n_exam_documents_and_daimons.php
database/migrations/*_create_n_import_audit_tables.php

app/Models/NSystem/NSchoolYear.php
app/Models/NSystem/NExamSeries.php
app/Models/NSystem/NExam.php
app/Models/NSystem/NPublicationEdition.php
app/Models/NSystem/NPublicationEntry.php
app/Models/NSystem/NExamDocument.php
app/Models/NSystem/NExamDaimon.php
app/Models/NSystem/NImportBatch.php
app/Models/NSystem/NSourceSchoolRow.php

app/Services/NSystem/NImportSchoolListService.php
app/Services/NSystem/NImportExamDocumentService.php
app/Console/Commands/NSystem/NSystemImportSchools.php
app/Console/Commands/NSystem/NSystemImportDocuments.php
```

### 変更

```text
app/Models/NSystem/NSchool.php
app/Http/Controllers/NSystem/NdemoController.php
app/Services/NSystem/NQuestionSearchService.php
app/Http/Requests/NSystem/NQuestionSearchRequest.php
resources/js/Pages/NSystem/Search.vue
resources/js/Components/NSystem/SearchFilters.vue
resources/js/Components/NSystem/SearchResultCard.vue
resources/views/n_system/demo/index.blade.php
resources/views/n_system/demo/school.blade.php
routes/nsystem.php
tests/Feature/NSystem/*
tests/Unit/NSystem/*
z_instructions/NSYSTEM_GUIDE.md
database/seeders/ChangelogSeeder.php
```

実装中に変更範囲が増える場合、コード変更前に本設計書と管理ファイルへ理由を追記する。

---

## 10. テスト計画

### DB・インポート

- 全テーブルが`n_`または`n_legacy_`で始まる
- 2022～2026年度の掲載行数が元Excelと一致する
- 各年度のMコードが重複しない
- 各掲載行が必ず1校・1試験へ紐付く
- 掲載行と学校の年度が`n_publication_editions.admission_year`と整合する
- 掲載行と試験の年度が`n_publication_editions.admission_year`と整合する
- 2022～2025と列位置の異なる2026を同じヘッダー解決規則で読み込める
- Nコード先頭3文字と学校マスターの対応が維持される
- 第1回・第2回を別`exam_series`として保持できる
- Nコード変更前後が同じ`exam_series_id`へ紐づく
- 名称変更前後が同じ`school_id`へ紐づく
- 2025年度以降のM109だけ、`4551`と`4751`のMコード共有を許可できる
- M109以外またはNコード集合が異なるMコード重複を取込エラーとして報告できる
- 各年度内で同じNコードが複数掲載行に現れた場合、対象年度・行・Mコード・学校名を報告できる
- 学校ごとに2022～2026のMコード履歴を取得できる
- 未解決・複合・変更表記がレポートに出る

### 検索

- 学校を指定して5年度横断検索できる
- 年度範囲フィルターが動作する
- 同じ学校の第1回・第2回をまとめて検索できる
- 特定の入試系列だけにも絞り込める
- 回答文書を既定検索へ含めない
- 一致箇所と遷移先ハイライトを維持する

### 回帰

- ゲスト認証
- 学校一覧
- 学校一覧で登録済み年度だけを選択でき、選択年度内はMコード昇順になる
- 5年度のMコード履歴を投入しても、問題文書が2024年度だけなら年度ボタンは2024だけになる
- 科目切り替え
- 問題・解答切り替え
- リアルタイム検索
- デモページ管理

---

## 11. 検証コマンド

```bash
docker compose exec laravel bash -lc "php artisan migrate:status"
docker compose exec laravel bash -lc "php artisan n-system:import-schools --years=2022,2023,2024,2025,2026"
docker compose exec laravel bash -lc "php artisan n-system:import-documents"
docker compose exec laravel bash -lc "php artisan test --testsuite=Feature --filter=NSystem"
docker compose exec laravel bash -lc "php artisan test --testsuite=Unit --filter=NSystem"
npm run build
```

コマンド名は既存の`n:import`から、所有範囲が明確な`n-system:*`へ移行する。移行期間中は旧コマンドを廃止予定として案内し、即時に無言削除しない。

---

## 12. 完了条件

- 5年度の学校リストが監査行を含めて登録される
- 学校、入試系列、年度試験、Mコード掲載が分離され、各掲載行が1校・1試験へ直接紐付く
- 問題が`n_exams`経由で正しい学校・年度・入試回へ紐づく
- 5年度横断検索と年度絞り込みが動作する
- NSystem外の業務テーブルに変更がない
- NSystem削除手順だけで全テーブル・ファイルを除去できる
- テスト、Lint、buildが成功する
- ChangelogSeederとNSYSTEM_GUIDEが更新される
- 完了後に本PLAN/MANAGER/PROMPTを`z_instructions/archived/`へ移動する

---

## 13. 今回対象外

- 15～20年分のデータ投入
- 外部検索エンジン導入
- 学校法人・キャンパス・系列校の詳細組織モデル
- 自動的な合併判定
- Mコードから学校性別を恒久属性として自動確定する処理
- NSystemのさくら本番デプロイ
- ユーザー承認前の旧テーブル削除
