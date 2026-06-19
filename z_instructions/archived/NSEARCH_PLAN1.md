# NSEARCH_PLAN1.md - NSystem検索改善 設計書

> 作成: 2026-06-19  
> 対象: クライアント提案用中学入試問題DBデモ `/n-demo`  
> 状態: 実装・自動検証完了（2026-06-19）

---

## 1. 背景と目的

NSystemは、SunBWork社内管理システムの業務機能ではなく、中学入試問題をDB化した場合の検索・閲覧体験をクライアントへ提案するための独立デモである。

現在の全文検索はMySQL ngram全文検索を自然言語モードで使用しているため、検索語の一部の2文字だけを含む問題も返す。たとえば「平安時代」は「平安」「安時」「時代」に分割され、「大正時代」なども候補になる。また、結果プレビューが本文先頭200文字固定のため、実際の一致箇所が見えず、誤ヒットに見える場合もある。

本改修では次を実現する。

- 入力した語が実際に本文に存在する、説明可能で正確な検索
- 入力中に結果が更新されるリアルタイム検索
- 一致箇所の前後を表示し、ヒット理由がすぐ分かる結果画面
- 科目・学校・カテゴリでの絞り込み
- クライアント提案に耐える、既存NSystemと統一されたレスポンシブUI
- NSystemの独立性を維持し、社内データや社内ナビゲーションを露出しない構成

---

## 2. 調査結果

### 2.1 誤ヒットの原因

`app/Http/Controllers/NSystem/NdemoController.php` の以下の処理が原因である。

```php
NQuestionsDaimon::whereFullText('body_text', $q)
```

`n_questions_daimon.body_text` のFULLTEXTインデックスはngram parserで作成されている。Laravelの `whereFullText()` は現状では自然言語モードを使うため、複数ngramのいずれかに一致した行も返る。

取り込み元JSONをファイル単位で確認した参考値:

| 検索語 | 完全な文字列を含むファイル | 2文字ngramの一部を含むファイル |
| --- | ---: | ---: |
| 平安時代 | 76 | 289 |
| 光合成 | 32 | 42 |
| 方程式 | 1 | 1 |

### 2.2 表示上の問題

`resources/views/n_system/demo/search.blade.php` は本文先頭200文字だけを表示している。一致箇所が後半にある場合、結果カード内に検索語が表示されない。

### 2.3 追加で解消する問題

- 全文検索例外時の `LIKE "%{$q}%"` では `%` と `_` がワイルドカードになる
- 例外を一括で捕捉しているため、DB障害とFULLTEXT非対応を区別できない
- 最大50件で打ち切られ、総件数や次ページが分からない
- 検索結果から学校ページへ移動しても対象大問へ直接移動しない
- NSystem検索の自動テストが存在しない

---

## 3. UI・レイアウト方針

### 3.1 `CONSOLIDATED_01_layout_and_ui.md` との関係

同資料は通常の社内向けInertiaページに `AppLayout` を必須としている。本改修はInertia/Vueを使用するが、NSystemには次の理由で社内用 `AppLayout` を使用しない。

- `AppLayout` はロール別ナビゲーション、社内通知、Echo購読、keep-alive等を含む
- ゲスト利用者にはSunBWorkの社内機能や構造を見せない必要がある
- NSystemは将来まとめて削除できる独立デモとして設計されている

したがって、NSystemを明示的な例外とし、専用の `NSystemDemoLayout.vue` を作る。これは一般的なレイアウトルールを無視するものではなく、次の規則は継承する。

- コンテンツ幅、余白、カード表現はレイアウト側で一元管理する
- ページ側で `main`、`py-12`、`max-w-*` の重複ラッパーを作らない
- Ziggyの `route()` は名前付きパラメータをオブジェクトで渡す
- `sm:` / `md:` / `lg:` を使ってレスポンシブ対応する
- コンポーネントとページの大文字小文字を統一する
- ユーザー入力を `v-html` で描画しない

### 3.2 デザイン

- 現在の紺色ヘッダー `#1a3a6b`、白いカード、カテゴリバッジを維持する
- 検索窓はデスクトップではヘッダー、モバイルではヘッダー下段に配置する
- 検索結果カードには学校、年度、科目、大問、カテゴリを表示する
- 一致箇所を中心に前後約100文字を表示し、一致部分を `<mark>` 相当で強調する
- ローディング、0件、通信エラーをそれぞれ明確に表示する
- 初回説明として検索モードの意味を短く表示する

---

## 4. 検索仕様

### 4.1 検索対象

- 対象テーブル: `n_questions_daimon`
- 対象カラム: `body_text`
- 解答 `n_answers_daimon` は今回の既定検索対象に含めない
- 将来の拡張用に検索サービス内で対象種別を分離する

### 4.2 検索モード

| モード | UI表示 | 仕様 |
| --- | --- | --- |
| `exact` | そのまま含む | 入力文字列が連続して本文中に存在する。既定値 |
| `all` | すべての語を含む | 空白区切りした全キーワードが本文中に存在する |
| `any` | いずれかの語を含む | 空白区切りしたキーワードの1つ以上が本文中に存在する |

`any` は意図的に広い検索であることをUIに表示する。現在のようにngramの一部が一致しただけの行を、利用者に説明なく返さない。

### 4.3 入力処理

- 前後空白を除去する
- 連続する半角・全角空白を1つへまとめる
- 最大100文字
- 空文字は検索しない
- 1文字検索は許可するが、件数が多くなり得ることを表示する
- LIKEのエスケープ文字 `!` と、ワイルドカード `%`、`_` をリテラル検索用にエスケープする
- SQL値は必ずバインドし、文字列連結した生SQLを作らない

Unicode NFKCによる全面的な全角半角変換は、数式・単位・記号の意味を変える可能性があるため第1段階では行わない。必要性を実データで確認して別フェーズとする。

### 4.4 DB検索方法

正確性を最終条件とし、以下の二段階を基本とする。

1. 2文字以上ではngram FULLTEXTのBoolean検索を候補抽出に使う
2. `exact` はリテラルLIKE、`all` は全語のリテラルLIKE、`any` はいずれかのリテラルLIKEで必ず再検証する

FULLTEXTは高速化と関連度算出にのみ使い、ヒット判定の最終根拠にはしない。短い語やFULLTEXTで安全に候補抽出できない入力は、LIKE条件のみを使用する。

### 4.5 並び順とページング

- 第1キー: 一致度
- 第2キー: 学校名
- 第3キー: 科目順（国語、算数、社会、理科）
- 第4キー: 大問番号
- 1ページ20件
- 総件数、現在ページ、最終ページを返す
- URLに `q`、`mode`、絞り込み、ページ番号を保持し、再読込・共有可能にする

---

## 5. リアルタイム検索仕様

- 検索画面本体はInertia/Vueページにする
- 検索結果取得は同じwebミドルウェア配下のJSON GETエンドポイントを使う
- 入力後300msで検索する
- 新しい入力があれば `AbortController` で古いリクエストを中止する
- IME変換中は検索せず、composition終了後に検索する
- Enterまたは検索ボタンでは即時実行する
- URLは検索成功後に更新する
- ブラウザ履歴から戻った場合はURLの条件を復元する
- 空文字ではリクエストせず、検索案内を表示する
- APIエラー時に既存結果を誤って0件へ置き換えない

Inertiaはページ表示と画面遷移を担当し、入力ごとの軽量な結果取得にはJSONを使う。これにより、Inertiaページ全体を毎回再取得するより通信量を抑える。

---

## 6. 一致箇所と遷移

### 6.1 スニペット

検索サービスがHTMLではなく、次の安全な構造を返す。

```json
{
  "before": "一致箇所より前の本文",
  "match": "検索語",
  "after": "一致箇所より後の本文",
  "leading_ellipsis": true,
  "trailing_ellipsis": true
}
```

Vue側では各値を通常のテキストとして描画し、`match` だけを `<mark>` に入れる。正規表現で生成したHTMLを `v-html` へ渡さない。

### 6.2 問題ページへの直接遷移

- 各大問ブロックに `id="daimon-{index}"` を付ける
- 結果リンクは対象学校、科目、問題モード、大問アンカーを含める
- 遷移後は対象大問が画面内に入り、短時間だけ背景を強調する
- 本番の `/members` ベースパスに対応するため、URLは `route()` から生成する

---

## 7. API仕様

### 7.1 画面

```text
GET /n-demo/search
route: n-demo.search
response: Inertia NSystem/Search
```

初期検索条件、学校一覧、カテゴリ一覧、科目ラベルをpropsで渡す。

### 7.2 結果API

```text
GET /n-demo/search/results
route: n-demo.search.results
middleware: GuestAuth:n-demo
response: JSON
```

パラメータ:

```text
q          string, max:100
mode       exact|all|any
subject    Ko|Sa|Sh|Ri|null
school_id  existing n_schools.id|null
category   共学|男子|女子|地方|null
page       integer|min:1
```

レスポンスには検索結果、スニペット、ページ情報、正規化後の条件を含める。`body_html` 全文は返さない。

---

## 8. サーバー構成

### 8.1 検索サービス

`app/Services/NSystem/NQuestionSearchService.php` を新設し、次をコントローラーから分離する。

- 入力語の分割とLIKEエスケープ
- モード別クエリ組み立て
- 科目・学校・カテゴリ絞り込み
- 並び順とページング
- 一致位置の特定とスニペット生成

### 8.2 バリデーション

`app/Http/Requests/NSystem/NQuestionSearchRequest.php` を新設し、画面とJSON APIで同じルールを使う。無効な絞り込み値を黙って検索条件へ入れない。

### 8.3 ログと例外

- 想定外例外を握りつぶしてLIKEへ切り替えない
- FULLTEXT候補抽出だけが利用不能な場合は、検索サービス内で明示的にLIKEへ切り替える
- 想定外エラーはログへ残し、JSON APIは一般的なエラーメッセージを返す
- DB構造やSQLをクライアントへ露出しない

---

## 9. Vue構成

```text
resources/js/
  layouts/
    NSystemDemoLayout.vue
  Pages/NSystem/
    Search.vue
  Components/NSystem/
    SearchFilters.vue
    SearchResultCard.vue
    SearchPagination.vue
```

コンポーネントを分割する理由は、検索条件、結果表示、ページングの責務を明確にし、テストと将来拡張を容易にするためである。

`NSystemDemoLayout.vue` は社内用 `AppLayout` をimportしない。ログアウトは既存の `n-guest.logout` を使い、CSRFはInertia/axiosの既存設定とmetaタグを利用する。

---

## 10. テスト計画

### 10.1 Featureテスト

`tests/Feature/NSystem/NQuestionSearchTest.php`

- 未認証利用者が結果APIへアクセスできない
- NSystemゲストセッションでアクセスできる
- `exact` では検索文字列を連続して含む問題だけ返る
- 「平安時代」で「大正時代」だけの問題を返さない
- `all` は全語を含む問題だけ返る
- `any` はいずれかの語を含む問題を返す
- `%` と `_` がワイルドカードにならない
- 科目、学校、カテゴリの絞り込み
- 20件ページング
- レスポンスに `body_html` を含めない

### 10.2 Unitテスト

`tests/Unit/NSystem/NQuestionSearchServiceTest.php`

- キーワード分割
- LIKEエスケープ
- 一致箇所を中心にしたスニペット
- 先頭・末尾の省略記号判定
- 日本語マルチバイト文字で位置がずれない

### 10.3 手動確認

- PC、タブレット、スマートフォン幅
- 日本語IME入力時に変換途中のリクエストが乱発しない
- 連続入力時に古い結果が新しい結果を上書きしない
- 結果リンクから対象大問へ移動する
- ゲストとSanctum認証済みスタッフの両方で利用できる
- ログアウトできる

---

## 11. 実装フェーズ

| Phase | 内容 | 完了条件 |
| --- | --- | --- |
| 1 | 検索サービス、Request、API、検索精度テスト | 誤ヒットを再現するテストが修正後に成功する |
| 2 | Inertia検索ページ、専用レイアウト、Vue部品 | リアルタイム検索と絞り込みが動作する |
| 3 | スニペット、ハイライト、大問直接遷移 | ヒット理由が結果と遷移先の両方で確認できる |
| 4 | レスポンシブ・アクセシビリティ・エラー表示 | PC/スマートフォンで操作可能 |
| 5 | 全テスト、build、ガイド・変更履歴更新 | 検証と関連文書更新が完了する |

各Phase終了時に `NSEARCH_MANAGER1.md` の進捗と作業ログを更新する。

---

## 12. 変更予定ファイル

### 新規

```text
app/Http/Requests/NSystem/NQuestionSearchRequest.php
app/Services/NSystem/NQuestionSearchService.php
resources/js/layouts/NSystemDemoLayout.vue
resources/js/Pages/NSystem/Search.vue
resources/js/Components/NSystem/SearchFilters.vue
resources/js/Components/NSystem/SearchResultCard.vue
resources/js/Components/NSystem/SearchPagination.vue
tests/Feature/NSystem/NQuestionSearchTest.php
tests/Unit/NSystem/NQuestionSearchServiceTest.php
```

### 変更

```text
app/Http/Controllers/NSystem/NdemoController.php
routes/nsystem.php
resources/views/n_system/demo/school.blade.php
z_instructions/NSYSTEM_GUIDE.md
database/seeders/ChangelogSeeder.php
```

実装中の調査で変更が必要になった場合は、先に本設計書と管理ファイルへ理由を追記する。

---

## 13. DB・既存機能への影響

- 新規テーブル・カラム・マイグレーションは予定しない
- 既存ngram FULLTEXTインデックスは候補抽出用として維持する
- インポートJSONと `n:import` の形式は変更しない
- 学校一覧、問題・解答表示、ゲスト認証、デモページ管理には原則影響しない
- NSystem関連名前空間とルート内に変更を限定する

---

## 14. 検証コマンド

```bash
docker compose exec laravel bash -lc "php artisan test --filter=NQuestionSearch"
npm run build
docker compose exec laravel bash -lc "php artisan route:list --name=n-demo"
```

Vue/JS変更後は必ずbuildする。`AGENTS.md` 記載のパス `/home/tchirosb/SunBWork` は現在のworkspace `/home/w229/SunBwork` と異なるため、実行時には実在するプロジェクトルートを使用し、その差異を管理ログへ記録する。

---

## 15. 完了時作業

- `database/seeders/ChangelogSeeder.php` に変更履歴を `updateOrCreate` 形式で追加
- `ChangelogSeeder` を実行
- `z_instructions/NSYSTEM_GUIDE.md` に新しい検索仕様とファイル構成を追記
- 本改修はNSystem固有のため、通常の社内UI規則そのものは変更しない。必要な場合のみ `CONSOLIDATED_01_layout_and_ui.md` にNSystem例外を追記する
- PLAN / MANAGER / PROMPTを `z_instructions/archived/` へ移動

---

## 16. 今回対象外

- 解答本文の横断検索
- OCR誤認識の自動補正
- Elasticsearch、Meilisearch等の外部検索エンジン導入
- 検索履歴・人気キーワード分析
- 学校一覧と問題表示ページの全面Inertia化
- NSystem以外の社内検索機能変更
