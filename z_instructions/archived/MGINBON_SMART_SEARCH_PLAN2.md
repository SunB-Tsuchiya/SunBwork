# 銀本「便利な検索」機能 PLAN2

作成日: 2026-10-01
状態: 利用者確認待ち

## 1. 目的

既存のFileMaker型検索を基盤として、大量の銀本進行レコードから日常的な確認対象・遅延・不整合を短時間で見つけられる「便利な検索」メニューを追加する。

既存の自由検索を置き換えず、次の2経路を共存させる。

- FileMaker型検索: 各セルへ自由に条件・演算子を入力する。
- 便利な検索: 名前付きプリセット、相対日付、未入力、期限超過、異常検索などを選択して条件を組み立てる。

両経路は同じ検索条件JSONと `MGinbonLedgerSearch` を利用し、画面、件数、CSVで検索結果がずれないようにする。

## 2. 採用機能（提案10項目をすべて対象）

1. 保存済み検索（個人用・共有用）
2. 未入力・期限超過の専用検索
3. 相対日付検索
4. 現在結果内の再絞り込み
5. 検索条件の要約・解除チップ
6. 実行前の該当件数プレビュー
7. 検索履歴と復元
8. 一致セル・一致理由のハイライト
9. 現在検索結果だけのCSV出力
10. 異常・矛盾検索

## 3. UI仕様

### 3.1 起点

検索モードの上部バーへ `便利な検索` ボタンを追加する。押すと上部バー直下にドロップダウン型パネルを表示する。画面全体を覆うモーダルにはせず、検索箱と結果を確認しながら操作できる形にする。

パネルは次のタブを持つ。

- よく使う検索
- 日付・未入力
- 異常チェック
- 保存済み
- 履歴

### 3.2 よく使う検索

初期プリセット:

- 入稿日未入力
- 担当者未設定
- 指定日超過・工程未完了
- 前工程完了・次工程未着手
- 校了日未入力
- 今日の予定
- 今週の予定
- 今後7日
- 30日以上更新なし

選択時に対応条件を新しい検索箱として追加する。「現在の箱を置換」と「新しい箱へ追加」を選択可能にする。

### 3.3 相対日付

保存時に実日付へ固定せず、相対日付トークンで保持する。

- 今日 / 昨日 / 明日
- 今週 / 来週
- 過去7日 / 今後7日
- 今月
- 今日以前 / 今日以降
- 任意の「過去N日」「今後N日」

JSTで実行時に解決する。例: `relative:today`, `relative:this_week`, `relative:next_days:7`。

### 3.4 再絞り込み

- `新しい検索`: 既存同様、箱同士をORで追加。
- `結果内を絞り込む`: 現在の検索式全体と新しい条件群をANDで結合。
- 条件JSONを `version: 2` のグループ式へ拡張し、`all`（AND）、`any`（OR）、`not`（除外）を表現する。
- 旧形式の配列JSONは引き続きdecodeできる後方互換変換を置く。

### 3.5 条件要約

検索実行後、上部へ条件チップを表示する。

例: `問題` `国語` `入稿日 ≥ 2026/02/01` `初校組 = 伊藤`。

- チップ単位で条件を解除
- 条件全消去
- 検索条件編集へ戻る
- 含める・除外・AND・ORを色と接続語で区別

### 3.6 件数プレビュー

便利な検索パネルおよび検索モードで、条件変更後に該当件数を表示する。

- 400ms程度のdebounce
- `POST /mginbon/search/preview-count`
- CSRF meta tokenを利用
- 件数だけを返し、レコード内容は返さない
- 同一ユーザーへのrate limitを設定
- 条件が空なら全件数を表示

### 3.7 保存済み検索

- 名前、説明、個人用/共有用、条件、表示方法、並び順を保存
- 個人用は作成者のみ操作可能
- 共有用はCoordinator/Admin/SuperAdminが作成・更新・削除可能、閲覧権限のある利用者が実行可能
- 年度固有検索と全年度共通テンプレートを区別
- 名前重複は所有者・スコープ内で禁止
- 保存時の日付ではなく相対日付トークンを保持

### 3.8 履歴

- ユーザーごと・年度ごとに直近20件
- 条件、要約、件数、実行日時、表示方法を保存
- 同一条件の連続実行は1件へまとめる
- 履歴から条件復元・再実行・保存済み検索へ昇格
- 個人履歴は本人以外へ公開しない

### 3.9 ハイライト

- 一致したセルへ緑枠、除外に関係したセルへ赤系の補助表示
- レコード左端に「一致理由」ボタン
- 理由パネルへ一致条件を列挙
- 大量データ対策として、現在ページのレコードだけ理由を生成
- 単なる全文再評価をVueだけで行わず、検索サービスが正規化したmatch descriptorを返す

### 3.10 CSV

- 現在の高度検索条件、媒体優先順、通常フィルターをすべて反映
- 検索条件要約、実行日時、実行者をCSVの追加列として記録
- 既存列の順序は維持し、追加列は末尾へ置く
- Shift-JIS/UTF-8等、現行CSV仕様は壊さない
- 検索条件が長すぎる場合は安全な長さへ切り詰める

## 4. 異常・矛盾検索の定義

初期セット:

1. 校正UPより校正入が後
2. 初校戻りが初校出より前
3. 再校戻りが再校出より前
4. 校了済みだが必須途中工程が未入力
5. 担当者未設定の作業工程
6. 同一工程・教科に複数の有効担当
7. 同一年度・学校・媒体・教科の重複
8. 無効化済みユーザー/外注先が担当
9. 前工程完了済み・次工程未着手
10. 更新から指定日数以上経過
11. 指定日を過ぎても対応する完了日が未入力
12. 科目は存在するが工程タスクが欠落

異常ルールはコード定数と専用サービスへ集約し、UI文言、SQL、CSV理由が同じルールIDを参照する。

## 5. データ設計

銀本専用接続 `mginbon` に以下を追加する。実migrationは利用者の明示確認後に限る。

### 5.1 `mginbon_saved_searches`

- `id`
- `mginbon_project_id` nullable（nullは全年度テンプレート）
- `owner_user_id`（主DBのuser ID、DB間FKなし）
- `name`
- `description` nullable
- `scope`: `personal|shared`
- `criteria_version` default 2
- `criteria` JSON
- `display_mode`: `single|list|table`
- `sort_key` nullable
- `created_at`, `updated_at`
- unique相当: project/owner/scope/name（null年度の扱いはサービスでも検証）

### 5.2 `mginbon_search_histories`

- `id`
- `mginbon_project_id`
- `user_id`（主DBのuser ID、DB間FKなし）
- `criteria_version`
- `criteria` JSON
- `criteria_hash` indexed
- `summary`
- `result_count`
- `display_mode`
- `executed_at`
- ユーザー×年度で直近20件を維持

### 5.3 セキュリティ

- JSONはallowlistに基づきdecodeし、任意カラム名・任意SQLを受け付けない
- 条件数、グループ深度、文字数、相対日付Nを上限検証
- 保存検索の認可Policyまたは専用Access serviceを用意
- 件数プレビュー、履歴、保存APIはCoordinator配下の既存認証・認可を継承

## 6. サービス構成

- `MGinbonLedgerSearch`: 条件decode、正規化、SQL適用（既存拡張）
- `MGinbonSearchCriteria`: version 1→2変換、allowlist検証、canonical JSON/hash
- `MGinbonRelativeDateResolver`: JST相対日付解決
- `MGinbonConvenientSearchCatalog`: プリセットと表示文言
- `MGinbonAnomalySearch`: 異常ルールSQLと理由
- `MGinbonSearchSummary`: チップ・CSV・履歴用要約
- `MGinbonSearchMatchDescriptor`: 現在ページの一致理由

同じ条件エンジンを一覧、件数プレビュー、CSVで再利用する。

## 7. API・ルート案

Coordinator認証グループ内:

- `POST /mginbon/search/preview-count`
- `GET /mginbon/saved-searches`
- `POST /mginbon/saved-searches`
- `PATCH /mginbon/saved-searches/{savedSearch}`
- `DELETE /mginbon/saved-searches/{savedSearch}`
- `GET /mginbon/search-histories`
- `POST /mginbon/search-histories/{history}/restore` は不要（GET取得した条件を通常indexへ送る）
- `DELETE /mginbon/search-histories`

Inertia初期propsにはプリセットカタログ、保存済み検索の軽量一覧、直近履歴を含める。詳細criteriaは必要時取得も検討する。

## 8. 影響予定ファイル

5ファイルを超えるためLarge Work Protocolを適用する。

主な新規/変更:

- migrations 2件（`mginbon` connection明示）
- `app/Models/MGinbon/MGinbonSavedSearch.php`
- `app/Models/MGinbon/MGinbonSearchHistory.php`
- 上記検索サービス群
- `app/Http/Controllers/Coordinator/MGinbonLedgerController.php`
- `app/Http/Controllers/Coordinator/MGinbonSearchController.php`
- `app/Http/Controllers/Coordinator/MGinbonSavedSearchController.php`
- `app/Http/Controllers/Coordinator/MGinbonSearchHistoryController.php`
- `app/Http/Controllers/Coordinator/MGinbonCsvExportController.php`
- FormRequest/Policy（必要に応じて）
- `resources/js/Pages/Coordinator/MGinbon/LedgerIndex.vue`
- `resources/js/Components/MGinbon/LedgerFindLayout.vue`
- `resources/js/Components/MGinbon/ConvenientSearchMenu.vue`
- `resources/js/Components/MGinbon/SearchConditionChips.vue`
- `resources/js/Components/MGinbon/SearchMatchReasons.vue`
- `routes/web.php`
- Feature/Unit tests
- `database/seeders/ChangelogSeeder.php`
- `z_instructions/CONSOLIDATED_09_domain_rules.md`

## 9. 実装フェーズと完了条件

### Phase 1: 条件エンジンv2

- version 1後方互換
- AND/OR/NOTグループ
- 相対日付
- canonical JSON/hash
- SQL allowlistと上限
- Unit/Feature tests

### Phase 2: 便利な検索UI

- ボタン、パネル、5タブ
- プリセットから検索箱生成
- 再絞り込み
- 条件要約チップ
- レスポンシブ・キーボード操作

### Phase 3: 件数プレビュー

- endpoint、debounce、キャンセル、rate limit
- 空条件・0件・エラー表示
- 本文データ非返却のテスト

### Phase 4: 保存済み検索・履歴

- migration/model/controller/auth
- 個人/共有
- 直近20件・重複集約
- 復元・削除・保存への昇格

### Phase 5: 異常検索

- 12ルール
- 合成fixtureによる正常/異常境界テスト
- 一致理由の共通化

### Phase 6: ハイライト

- 現在ページだけmatch descriptor生成
- LIST/単票/表形式で一貫表示
- 除外条件は結果に残らないため、除外理由は条件要約側で表示

### Phase 7: CSV

- 一覧と同一条件・同一順序
- 条件要約列追加
- 文字コード・BOM・改行の既存仕様確認

### Phase 8: 統合検証・文書

- 全銀本テスト
- Vue build
- `git diff --check`
- migration dry-run相当のSQL確認
- 既存検索、演算子、表示方法localStorageの回帰確認
- ChangelogSeederとCONSOLIDATED更新
- 完了後PLAN/MANAGER/PROMPTをarchivedへ移動

各Phase完了ごとにMANAGER2へ検証結果と未解決事項を記録する。Phase 4以降へ進む前にPhase 1〜3の画面確認を依頼する。

## 10. 検証方針

- version 1/2 decode互換
- 条件内AND、箱間OR、結果内AND、除外NOT
- JST境界（0時、週開始、月末、うるう年）
- 個人/共有認可
- 履歴20件・重複hash
- 異常12ルールのpositive/negative fixture
- preview件数とindex件数とCSV件数の一致
- SQL query countと代表データ量での応答時間計測
- 長大・不正JSON、深すぎるグループ、未許可field/operator拒否
- `docker compose exec laravel bash -lc "php artisan test --filter=MGinbon"`
- `npm run build`
- `git diff --check`

## 11. 性能目標

- 通常検索一覧: 現状比で著しく悪化させない
- 件数プレビュー: 代表データで1秒以内を目標
- 保存検索/履歴一覧: 200ms程度を目標
- match descriptorはページ内レコードだけ
- 異常検索はEXISTS中心とし、必要なindexはmigration計画に含める
- EXPLAINはローカル合成データのみで確認し、本番データをSSH等で調査しない

## 12. 保護・禁止事項

- 既存未コミット変更を戻さない。
- `storage/**` を変更・コミットしない。
- 本番データ、特にsalesデータを調査しない。
- destructive DB操作を行わない。
- Sakura SSH・migration・seed・deployは、正確なコマンド提示と利用者の明示確認まで実行しない。
- ローカルmigrationも対象DB名を確認し、利用者の明示確認を得てから実行する。
- migration/seed/deployの実行はサブエージェントへ委譲しない。

## 13. 利用者確認事項

実装開始前に本PLAN2の承認を得る。

設計上の既定値:

- 履歴保持: ユーザー×年度20件
- 共有検索の編集: Coordinator/Admin/SuperAdmin
- 週の開始: 月曜日（JST）
- 相対日付: 実行時評価
- 件数プレビューdebounce: 400ms
- 異常検索の長期未更新: 初期値30日、変更可能
- 保存済み検索は年度固有/全年度テンプレートの両方
