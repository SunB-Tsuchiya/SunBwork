# マイジョブ入力・BOX統合 管理

更新: 2026-11-02 JST。設計正本: MYJOB_UNIFIED_BOX_PLAN1.md。親: USER_WORK_HUB_MANAGER1.md。

## 現在地

| 状態 | 証拠 |
| --- | --- |
| 統合設計を進める | 利用者了承済み。「一般Userにはまだ公開しないので慎重に」と再確認 |
| 一般公開 | 未承認。INFO SuperAdmin試用と分類管理許可を分離する |
| 現行実装調査 | 保存70ca0778eのコードを確認。旧BOXは締切/登録月基準、独立簡易記録は別保存、proof実行枠は旧BOX除外 |
| 設計資料 | 3回答と初回読取範囲の承認を反映 |
| 3確認への回答 | 利用者が全て選択済み。未予定保持/従来ジョブ1行で複数日/簡易otherを含めると確定 |
| 統合一覧コード/保存処理 | 読取試用のみ実装済み。保存処理は変更なし |
| コード検証 | 合成29テスト308断言、実route/controller10組、実Vue SSR、対象ESLint、membersビルド成功。PHP7ファイル構文成功、manifest491参照欠落0と/members/を確認 |
| 限定配備 | 2026-11-02 承認済みSSHで7f2868a06へ反映成功 |
| 統合画面確認 | 2026-11-02 利用者「成功」で新ボタン表示/一覧表示/全期間での作業名検索・クリアの3点を確認済み |

## 工程案

1. 質問回答を反映し、日付/行/対象と入口の設計を確定する。
2. SuperAdminだけの読取統合BOXを合成テストとともに実装する範囲を確認する。
3. コード検証→固定差分の限定配備→新しい読取一覧だけ画面確認。
4. 簡易入力を通常作成へ接続。旧詳細/依頼転換/続き/添付を既存経路へ保護。
5. BOX/カレンダー/日報/集計の整合と新しい入力経路を確認する。
6. 一般公開は別判断まで待つ。

## 決定・未決の管理

- 確定: 会議/外出などの通常予定はBOXへ入れない。既存データの一括移行を前提にしない。新一覧を開く権限はINFO SuperAdmin本人だけ。
- 確定: Q1旧未予定を残す、Q2従来ジョブは1行複数日、Q3簡易作業区分otherを含める。
- 提案: 最初は別の試用入口から読取一覧を確認し、通常入口への接続を次にする。新しいDBテーブルは初回不要。
- 確定: 初回試用の未予定は旧BOX互換の締切/登録月、予定ありは実作業月。
- 保留: 簡易記録に完了を設ける必要性、入力元間の追加の続き管理、一般公開日。
- 重複対策: source_event_id/assignment/event/supersedesの明示IDを用いる。title一致は不可。校正のcoordinator_assignment_idと続きのsource_assignment_idは別物。

## 引継ぎ・保護

専用ブランチfeature/project-broad-link-20261102、/tmp/sunbwork-project-broad-link-20261102で資料を作成。共有/home/w229/SunBworkのClaude変更は未編集。本番最終確認5462a2b09で、今回の統合設計作業ではSSH/DB読取/配備なし。既存のお気に入り/分類/機密/休止の利用者確認を再依頼しない。

今回読んだコードはMyProjectJobController::index、ProjectJobAssignmentByMyself、PersonalWorkEntry、MyJobBox/Index、MyJobCreateModal、PersonalWorkSchedule、DepartmentWorkController、CalculatesEventTime、EventControllerの依頼/校正処理。過去会話の未読記録を読んだとは扱わない。日付・表示形式の曖昧さは実装前に確定する。

次の一手は、PLAN末尾の初回読取試用（SA専用ボタン/本人一覧/日付検索/元詳細リンクだけ、保存・公開変更なし）の実装範囲を利用者に確認すること。Q1〜Q3の再質問は不要。

2026-11-02 Q1〜Q3回答反映: 利用者は未予定を残す/従来ジョブは1行で複数日/簡易otherを含めるを選択。PLANとPROMPTへ確定記録。初回はSuperAdmin専用の閲覧プレビューを現行BOXの別ボタンから開き、予定ありは作業月、未予定は従来の締切/登録月とする具体案を提示する。アプリ実装・配備・一般公開はまだ行っていない。次は初回実装範囲の承認。

## 2026-11-02 初回実装の承認と検証

利用者のyesは直前の読取限定プレビューの実装許可。旧「次は実装範囲の承認」を置換。新作業先はfeature/unified-box-preview-20261102、/tmp/sunbwork-unified-box-20261102、保存ce396f6bd基点。以前の/tmpは消失しており、Git保存版を復元。共有Claude作業は未編集。

UnifiedMyJobBoxTestを合成SQLiteメモリで追加。既存4関連テストと合わせ29件308断言成功。本人/他人/会社部署/各role/一般会社/別会社コンテキスト、同数字ID/同名、未予定/複数日、簡易other/補足除外、通常予定除外、proof UTC/月境界/終了排他、依頼置換/曖昧置換/続き/校正、表の由来、検索/全期間/ページング、全クエリSELECTのみを検証。

一時検証で実Laravelのrouteとcontrollerを起動し、5role×2会社コンテキスト10組の403/許可と許可時Inertia JSONを確認。実Vue SFCのSSRで未予定・3予定の行内展開・簡易行・リンク・空結果・ページ・エスケープを確認。Ziggyを実生成し追加定義の一致を照合。これらはブラウザ画面確認ではない。

次の一手: 検証済み固定版と許可ファイル一覧で限定配備スクリプトを準備し、正確なSSHコマンドを提示し承認を得る。一般公開しない。入力工程へはまだ進まない。

最終検証: 対象Vue ESLint成功、PHP7ファイルphp -l成功、最終membersビルド成功、manifest491資産参照の欠落0、appの/members/build/と新ページを確認。ビルドによる既存7793資産の削除は削除パスだけGitから復元して保持。共有.env/ビルドには触れていない。SSRと実route/controllerは一時検証（/tmp/unified-box-verification）、永続テストはtests/Unit/UnifiedMyJobBoxTest.php。本番SSH/DB読取/配備は未実施。既存の分類やお気に入り等の利用者画面確認は繰り返していない。

配備予定コマンドは `ssh -T silverlamb759@silverlamb759.sakura.ne.jp 'sh -s' < /tmp/unified-box-preview-deploy-20261102.sh`。本番の固定元5462a2b09/main/追跡変更なし、取得版固定、今回ソース許可リスト一致、依存変更・削除なし、PHP構文を全て確認後にff-only。migrationなし、独立UnifiedMyJobBoxChangelogSeederだけ--force、cache/config/route/view clear。既存未追跡ファイルは操作しない。版不一致時は反映前停止し、他作業を取り込まない。実行は正確なコマンドの利用者承認後。

## 2026-11-02 限定配備完了・初回画面確認待ち

利用者は提示した正確なSSHコマンドにyes。変更せず同スクリプトを実行しexit0。元5462a2b09/main/追跡変更なし、取得7f2868a06e6f4b55ce6e6c406c5414886d2df4ab、19ソース許可リスト一致、依存/migration変更なし、削除なしを確認後にff-only更新。PHP7構文、既定mysql名silverlamb759_sunbworkの設定値照合、manifest資産欠落なしとmembersパス、新試用ルートGET|HEAD/認証middleware確認成功。独立UnifiedMyJobBoxChangelogSeederだけ--database=mysql --force成功、config/cache/route/view clear成功。業務記録の読取調査・変更やsales接続なし。最後は追跡変更なし、既存未追跡5件を保持。

実装/コード検証/限定配備は完了。実ブラウザ画面確認/上長レビュー/一般公開は未完了。一般Userの通常画面/保存/日報/評価と公開制限を維持。

次の一手は情報出版SuperAdminで以下の新しい3点を確認してもらうこと。
1. マイジョブBOXを再読み込みし「統合BOX（試用）」ボタンが表示される。
2. ボタンからエラーなく統合一覧を開ける（件数0でも開ければこの条件は可）。
3. 「全期間」を選び、表示された作業名の一部で検索・クリアできる。全期間にも対象行がなければその旨を回答し、この条件は保留にする。
すべて問題なければ「成功」、違い/対象なしなら項目番号と結果。未予定・複数日の実例/個々の日時/各リンク往復/別アカウント画面は今回の成功から確認済みにしない。既確認の分類/星/機密/休止画面は繰り返さない。

## 2026-11-02 初回3点の利用者画面確認成功

利用者は直前に依頼した3点へ「成功」と回答。情報出版SuperAdminで現行BOXの新ボタン表示、試用一覧をエラーなく開く、全期間で表示作業名の一部を検索してクリアする操作を確認済みに変更。未予定/複数日の実例・全日時の照合・詳細やカレンダー往復・別アカウント・上長レビューは未確認。既確認項目の再依頼は不要。一般公開は未承認。

次の一手（提案、未実装）: 統合BOX試用に「マイジョブ作成」を設け既存QuickWorkEntryModalを開き、既存の簡易入力保存APIを使用する。作業日/開始/終了/作業名、任意案件・分類・数量を入力し、保存後はその作業月のBOXへ表示。既存の個人カレンダーにも同じ記録を表示し、assignment/eventsへの複製や日報・評価の新計算はしない。従来の詳細入力・依頼から登録・添付は旧経路を維持。実所属INFO SuperAdmin/本人/会社コンテキストの境界を維持し、一般UserにはボタンもAPI利用権限も追加しない。初回読取範囲を拡張するため、この次段階の仕様確認後に実装する。

## 2026-11-02 作成接続の承認・実装・コード検証

利用者yesにより前段末尾の作成接続案を確定。「提案/未実装」をこの工程について置換。SuperAdmin限定試用BOXへマイジョブ作成ボタンとQuickWorkEntryModal（myjob文脈）を接続。JST今日/時刻空欄で開き、日付変更可。既存のDepartmentWorkController::storeへ、厳しい統合BOXguardを持つ専用POSTから委譲する。旧カレンダーの既存POST/PUT・表示文言を維持。保存成功で作業日+IDを受け、検索/完了除外を解除して保存月と対象行ページへ移動、「今回登録」を表示。詳細入力/依頼/添付は既存BOXへ戻る案内を追加。新DB/複製/編集削除完了の統合なし。

コード検証: 既存関連29テスト311断言と新Integration3テスト44断言、計32件355断言成功。IntegrationはTests/TestCaseやRefreshDatabaseを使わず、DB_CONNECTION=sqlite/DB_DATABASE=:memory:の明示条件でのみ起動。専用POSTのrole/一般会社/INFO外/別会社コンテキスト403、既存検証の時刻必須/終了順/数量単位/不正日付/別会社分類拒否、本人所属ID固定・不正source/assignment ID無視、1簡易記録だけ保存とBOX/既存mine APIの同じ日付時刻/分類、events/assignments/projects件数不変を確認。created_entryも本人行だけをページ表示し、不明IDは無視する。

実Vue script/SSRの永続tests/js/unifiedMyJobInput.test.cjs成功。BOX/カレンダーのheading/説明、作成/編集POST/PUT先、必須時刻、開き直しリセット、保存失敗の内容保持、保存成功日付、JST境界、対象月とIDへの移動、旧BOXへの案内を確認。これはブラウザ実画面確認ではない。対象ESLintと変更PHP6構文成功、実Ziggy生成済み。最終ビルド/manifest/差分確認を行う。

限定配備・作成の利用者画面確認は未実施。現在の本番7f2868a06（読取試用）と、初回3点の画面成功は保持し繰り返さない。次は固定差分の正確なSSHコマンドを準備して承認を得る。一般公開なし、カレンダー入口再編や編集削除完了統合は後工程。

最終確認: 実Vue script/SSR成功、対象ESLint成功、PHP6構文/実Ziggy GET+POST一致、members最終ビルド成功、manifest492参照欠落0と/members/build/確認。ビルドによる既存資産の削除は削除パスだけ復元し全保持。ソースdiff --check成功。共有Claudeソース/.env/ビルドは未編集。旧読取試用の画面確認は繰り返していない。

配備準備: 固定元7f2868a06から今回の作成接続版だけを、ソース許可一覧/取得SHA/追跡変更なし/main/削除なし/依存・migration変更なし/PHP構文を照合してff-only。独立UnifiedMyJobBoxChangelogSeederだけ--database=mysql --force、config/cache/route/view clear、試用GET/POST登録確認を行うスクリプトを /tmp/unified-box-input-deploy-20261102.sh に用意する。正確な実行コマンドは `ssh -T silverlamb759@silverlamb759.sakura.ne.jp 'sh -s' < /tmp/unified-box-input-deploy-20261102.sh`。実行は利用者承認後。現時点で本番配備/作成画面確認は未実施。

## 2026-11-02 作成接続の限定配備成功

利用者は提示済みの正確なSSHコマンドにyes。同じ/tmp/unified-box-input-deploy-20261102.shを実行してexit0。元7f2868a06/main/追跡変更なし、取得ef32b4e1352a3fd79ed6f375bcc24fb94b530260、15ソース一致、削除なし、依存/migration変更なし、PHP6構文を通過後ff-only。既定mysql名silverlamb759_sunbworkを設定値だけで照合。manifestの全資産存在/membersパス確認成功。独立UnifiedMyJobBoxChangelogSeederだけ--database=mysql --force成功、config/cache/route/view clear成功。GET|HEAD unified_previewとPOST unified_storeの登録/認証middlewareを確認。最後は追跡変更なし、既存未追跡5件保持。sales接続/業務記録調査/作業の試験登録は実施していない。

作成接続の実装/コード検証/限定配備は完了、利用者の作成画面・保存・カレンダー表示確認はまだ。旧読取3点の成功は維持。一般公開/上長レビュー/編集削除完了統合は未実施。

次の一手は情報出版SuperAdminで以下の3点を確認してもらうこと。
1. 統合BOXを再読み込みし「マイジョブ作成」を押すと入力モーダルが開く。
2. 確認用または実際の作業名・日付・開始/終了時刻を入れて保存でき、同じ内容の行と「今回登録」がBOXに表示される。
3. その行の「カレンダー」から、入力した日・時刻に同じ作業が表示される。
すべて問題なければ「成功」、違いがあれば番号と結果。数量/分類/詳細経路/既存カレンダー側での編集削除/別アカウント/評価値の変化は今回の成功から確認済みにしない。確認用作業の削除は依頼していないため、削除済みと推定しない。
