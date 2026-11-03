# マイジョブ入力・BOX統合 管理

更新: 2026-11-03 JST。設計正本: MYJOB_UNIFIED_BOX_PLAN1.md。親: USER_WORK_HUB_MANAGER1.md。

## 最新の現在地

本番アプリf7ff846da。先行入力/保存/分類/単位/青色の確認は記録済み。最新指示は、添付・依頼等の旧方式を残し、詳細/完了/続き/時間等の必須機能を漏れなく対応すること。チェックリストはPLAN末尾M01〜M12/L01〜L03/U01〜U04。これら新機能のコードは未実装、保存方式や完了伝播等の共通仕様を具体化する段階。一般公開とカレンダー入口再編は保留。

## 先行読取試用の状態（履歴）

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

## 2026-11-03 保存成功・候補/単位/色の要望

利用者「保存までは成功」と限定。作成画面を開く/保存成功を確認済みとする。BOXの全表示条件とカレンダー同日同時刻/リンク往復まで確認したとは扱わない。保存確認は再依頼しない。

追加要望: 分類が全体約1/3しかない、多いので大見出し＋トグル希望。単位がページ以外に出ない。ピンクをマイジョブ青へ統一したい、分ける合理的理由があるなら提案を求めた。調査では候補通常30件制限、text+datalistのページ初期値によるブラウザ絞込、旧簡易ピンクのサーバ/日報/凡例定数を特定。統合方針に従い別色を維持する業務上の必要はないと説明し、青へ揃える方針で修正。

旧/tmp消失のため保存f6346e8ddからfix/unified-input-picker-20261103、/tmp/sunbwork-unified-input-fix-20261103へ復元。共有Claudeソース/設定は未変更。現在の本番最終証拠ef32b4e13。今回まだSSH/DB読取/配備なし。

実装: 簡易入力の分類だけ全件/元順にし、parent_idを返す。既存候補メソッドの既定30件は他の呼出に保持。簡易フォームは共通treeRowsで開閉・検索文脈を利用、大/中の選択、検索自動展開、上部お気に入り近道を維持。単位は常時候補ボタン+自由入力。旧マイジョブ青にPHP/API・JS凡例/日報と保存元チップを揃え、職種/校正色は維持。保存・評価式・公開範囲の変更なし。

検証: 関連Unit30件316断言、Integration3件46断言の計33件362断言成功。全40候補/元順、親id、休止親子/別会社除外、既存30制限の互換、既存保存/本人guard/カレンダーAPI青・白字を確認。実Vue script/SSRで42候補の根だけ初期表示→＋全展開→閉じる→末尾中分類検索で親+中→選択、常時6単位ボタンを確認、既存保存/エラー/日付処理も成功。共通tree helperの既存テスト成功、対象ESLint成功。最終ビルドと変更Seeder構文を確認してから固定差分配備を依頼する。

次の一手: 修正の最終検証後、固定元ef32b4e13と今回だけのソース許可一覧を持つSSHスクリプトを提示し承認を得る。未配備/未画面確認。過去の保存チェックや初回一覧チェックを繰り返さない。次の新画面確認は分類全件/＋開閉・検索、単位選択、カレンダー青色を中心にする。

2026-11-03 最終検証: PHP変更6ファイル構文、対象ESLint、実Vue script/SSR、共通treeテスト、33件362断言成功。members build成功、manifest493参照欠落0/membersパスを確認。既存資産は削除パスのみ復元して保持。新migration/route/依存変更なし。次は /tmp/unified-picker-deploy-20261103.sh を用意し、`ssh -T silverlamb759@silverlamb759.sakura.ne.jp 'sh -s' < /tmp/unified-picker-deploy-20261103.sh` の承認を得る。固定元ef32b4e13/main/追跡変更なし/今回許可ソースと固定取得SHA/削除・依存・migration変更なしを検証してff-only。独立UnifiedMyJobBoxChangelogSeederだけ--database=mysql --force、cache/config/route/view clear。今回の配備/新UI画面確認はまだ。

## 2026-11-03 分類/単位/色の限定配備成功

利用者は正確なSSHコマンドにyes。同じ/tmp/unified-picker-deploy-20261103.shを実行しexit0。元ef32b4e13/main/追跡変更なし、取得f7ff846daf90983c1cc6d47400e317b75b9ac3d0、16ソース一致、削除/依存/migration/route/Ziggy変更なし、PHP6構文を検証後ff-only更新。既定mysql名silverlamb759_sunbworkを設定値だけ照合。manifest全資産存在/membersパス、独立UnifiedMyJobBoxChangelogSeeder --database=mysql --force、config/cache/route/view clear成功。最後も追跡変更なし/既存未追跡5件保持。sales/業務記録調査・作業登録なし。

今回のコード検証と限定配備は完了、修正UIの利用者画面確認は未実施。一般公開なし。利用者が既に成功と述べた保存は繰り返さない。色は保存済み記録にも表示時に適用され、作り直し不要。

次の一手: 情報出版SuperAdminで次の3点を確認してもらう。
1. 再読み込み後、作成画面の「案件を探す」で大見出しの＋/−が開閉し、以前見つからなかった分類を検索して選べる。
2. 単位の「ファイル」「件」などが常時表示され、押すと単位欄へ反映される（保存不要）。
3. カレンダーを再読み込みし、先ほど保存済みの作業が青で表示される。
全部問題なければ「成功」、違えば番号と結果。全分類の実データ件数照合・全単位の保存・日報/部署/分析の全画面配色・カレンダー往復の日時照合までは確認済みにしない。

## 2026-11-03 分類/単位/色の利用者画面確認成功

利用者「成功」は直前の全3項目を確認した回答。①大見出し＋/−開閉と以前欠けた分類の検索選択、②ファイル/件など常時単位候補から単位欄への反映（保存不要）、③先に保存済みの作業が再読込したカレンダーで青表示、を確認済みに変更。「修正UI未確認/3点回答待ち」を置換し、この修正のコード・配備・依頼した実画面確認は完了。

前の「保存までは成功」と今回の成功は別範囲。全分類の実データ件数照合、単位/分類を変更した再保存、全日時の一致やカレンダーリンク往復、日報/部署/分析の全配色、別本人実画面、上長レビューまでは未確認。確認済みの保存/分類/単位/青色を再依頼しない。一般公開は未承認のまま。

次の一手はPLANの残る入力導線（試用カレンダーの入口を同じマイジョブ作成へ揃える範囲）を利用者と相談すること。一般Userの旧入口削除・本番公開まで進めない。現在の試用コードは本番f7ff846da、資料更新だけでは再配備しない。

## 2026-11-03 詳細/続きと機能同等性を優先して調査

利用者は詳細不足と続き登録への対応を指摘。「簡易とマイジョブは入力のしやすさ以外、同じ機能と役割」を確定要件とする。直前の「次はカレンダー入口統一」を保留へ置換。既存の入力/保存/分類/単位/青色の成功は維持するが、完全統合の成功としない。

コード調査結果はMYJOB_FEATURE_PARITY_AUDIT1.md。現在は簡易クリックで編集しか開けず、独立entry行の詳細URLなし、続き参照/完了/ファイル情報等の保存機能なし。旧はsource_assignmentの続き/系列、supersedesの依頼置換、完了時祖先/表/校正連携がある。昼控除と昼前後の続き登録は別。昼専用自動分割ボタンは未特定で、確認できた一般の過去流用→続き操作を説明する。旧の複数eventsと続き別assignmentの意味も区別する。

調査対象: UserCalendar/QuickWorkEntryModal、UnifiedMyJobBox/UnifiedPreview、PersonalWorkEntryと関連migration、DepartmentWorkController、MyJobCreateModal/MyJobSourcePicker/Create_Job、共通AssignmentForm/AssignmentDetailCard、MyProjectJobController/ProjectJobAssignmentController、MyJobBox/ShowとEvents/Show、EventControllerの系列/時間、PersonalWorkSchedule/WorkloadTrialAnalysis。根拠はローカル保存cecc7c9c5（アプリf7ff846da）。手元origin/mainはb1d57e1ddで、実リモート最新を再取得していない。SSH/実DB/新画面試験なし、アプリコード変更なし。

次の一手: 詳細・続きの対応を中心に、完了/添付/依頼/表/校正/時間と数量の同等性を満たす共通仕事仕様をPLANへ具体化する。assignment本体へ統合するか両保存元を共通操作にするかは未決。分類/仮名だけで登録できる簡単さと旧ID/履歴/日報/集計を保護し、画面だけ同じにした別機能や無条件コピーを避ける。一般公開はしない。

## 2026-11-03 必須機能の対応管理

利用者の最新指示により、添付/依頼等の専用UIを簡易側に全新設する方針は採らず、旧方式を維持。詳細/完了/続き/時間等をPLAN末尾の必須M01〜M12へ列挙。調査表の全同等化案をこの分担で更新した。以下は今回新たに対応する必須項目の状態（先行BOX/作成等の成功を転用しない）。

| ID | 対象 | 実装 | コード検証 | 限定配備 | 利用者画面確認 |
| --- | --- | --- | --- | --- | --- |
| M01 | 詳細ページ | 未 | 未 | 未 | 未 |
| M02 | 詳細から編集 | 未 | 未 | 未 | 未 |
| M03 | 明示完了と状態 | 未 | 未 | 未 | 未 |
| M04 | 明示元から続き作成 | 未 | 未 | 未 | 未 |
| M05 | 系列と前後/合計 | 未 | 未 | 未 | 未 |
| M06 | 昼前後/翌日再開 | 未 | 未 | 未 | 未 |
| M07 | 時間/控除の整合 | 共通化未 | 未 | 未 | 未 |
| M08 | 変更削除/系列日報保護 | 新系列対応未 | 未 | 未 | 未 |
| M09 | 旧新互換/既存系列保持 | 未 | 未 | 未 | 未 |
| M10 | 数量単位/状態継承 | 続き対応未 | 未 | 未 | 未 |
| M11 | 旧経路と同一仕事の受渡し | 共通化未 | 未 | 未 | 未 |
| M12 | 新操作の本人/所属/SA認可 | 新操作未 | 未 | 未 | 未 |

各IDの完了条件はPLANを正本とし、コード/試験/配備/利用者結果の証拠を付けて状態更新する。既存の独立編集/既存計算/認可をすべて未実装と扱う意味ではなく、新しい共通機能としての対応状態。

L01添付/ファイル情報、L02依頼ジョブ関連、L03表/校正専用操作は旧方式を残す。既存機能の存続と新仕事からの受渡し（M11）は別状態で確認する。U01保存構造、U02完了伝播/解除、U03削除/日報/関連保護、U04昼の実操作を設計前に具体化する。祖先を完了にする旧処理を、未完の兄弟まで閉じる要求に拡張しない。

次の一手: U01〜U03と各Mの共通仕様・保存/認可/時間の方式を具体化する。今回は一覧化と資料整備のみ、アプリ/DB/配備変更なし。既確認の保存/分類/単位/青色再試験は不要、一般公開なし。

## 2026-11-03 共通機能の具体設計を作成

利用者「続けましょう」でU01〜U03と各Mを具体化。MYJOB_CORE_DESIGN1.mdを新規作成し、PLANへ参照を追加。方式の比較・nullable項目案・型つき元参照/本人認可・旧新混在系列・軽い編集・完了の選択+祖先反映/兄弟子維持・子あり削除拒否・時間ledger共通化・冪等/数量非複製・旧方式受渡し残・段階と検証を記載。

追加コード調査: WorkloadTrialAnalysis::minuteLedgerは分単位で休憩除外/優先/同優先均等配分。一方EventController::computeChainSeriesは直接Carbon parse/ユーザー既定休憩の独自計算、PersonalWorkSchedule::plannedMinutes(hour)は枠長を保存する。現状画面の同名「時間」を無条件に同じ値とみなせない。予定と現在までの記録を分け、同条件照合と旧分析の不変を合成検証する設計とした。

設計の各業務ルールは**提案**。U01〜U03は案ができたが利用者確定前。既存コード/DBは未変更、M01〜M12の実装状態はまだ未。一般公開なし、保存/分類/単位/青色等の既確認を繰り返さない。根拠版cf222f928、今回SSH/実DB読取なし。

次の一手: 具体設計案を利用者へ説明し、保存元保持/選択+祖先完了/兄弟子維持/子あり削除拒否/初回完了解除なしの範囲で実装するか確認を得る。AGENTS.mdの大規模実装前のPLAN/MANAGER/PROMPT確認に従う。実装承認後もSSH配備は別の固定差分・正確なコマンド承認が必要。M11の既存簡易から旧方式への同一仕事受渡しは後段の設計・確認が残り、全必須完了とは報告しない。

## 2026-11-03 具体設計承認・段階1コード検証

利用者yesで設計案を承認。以前の「実装前確認待ち」を置換。ブランチfeature/myjob-core-20261103（同じ隔離ツリー/tmp/sunbwork-unified-input-fix-20261103）で段階1実装。共有Claudeソース・設定は触らない。本番最新証拠f7ff846da、今回まだSSHなし。

実装: WorkJobReader/WorkJobDetailController/WorkDetail.vue、BOX/カレンダーから同じentry詳細、本人/会社部署/context/SA guard、軽い編集、保護付き削除と専用deletedイベント。新migrationはentry.completed_at/source_entry_id/source_assignment_idとPJA.source_personal_work_entry_idの4nullable追加/FKだけ。既存行コピーなし、新fieldsは通常フォームvalidationに含めず改ざん書込不可。共通ledger抽出、analyzeに任意記録別出力、詳細の予定/昼休憩/記録値分離。一般UserのCalendarには新detail許可propを返さず従来モーダル経路を維持。

検証: 関連Unit88件579断言、Integration6件78断言、計94件657断言成功。既存V2/V3分析の全出力不変（任意内訳を除く）、均等配分/会議優先/日跨ぎ/proof UTC/日別休憩、未来予定の算入なし、詳細GET全SELECT、ID保持編集/状態と元参照の改ざん無視、混在祖先読取/循環・二重親・他人/context拒否、FK削除保護/日報・子あり422/末尾削除を確認。実Vue script/SSRで詳細エスケープ/項目/時間、edit先/削除先と削除後戻り、既存Calendar saved互換を確認。対象ESLint、PHP13構文、実Ziggy3定義一致成功。ブラウザ画面確認ではない。

各Mの段階1状態: M01独立簡易詳細、M02同一記録編集は実装/合成検証済み・未配備/画面未確認。M07/M08/M09/M12は共通時間・削除保護・混在参照・認可の基盤を実装した部分対応。M03/M04/M05/M06/M10の操作、M09旧フォーム相互受渡し、M11旧方式切替は未完。全必須完了と報告しない。

次の一手: 最終members build/manifest/既存資産保持を確認し、固定元f7ff846daから段階1だけを反映するSSHスクリプトを提示して承認を得る。新migrationだけ--database=mysql --path/--realpath --force、前後の件数/新列NULL保持を確認。対象migration前の別のmigrationを一括実行しない。一般公開なし、既確認の保存/分類/単位/青色の再確認は不要。

段階1最終検証: 最終members build成功、manifest494資産参照欠落0、WorkDetailページと/members/build/を確認。既存削除資産は削除パスのみ復元して保持。実Vue script/SSRと対象ESLint成功、3新ルートは実Ziggy生成と一致。PHP13ファイルphp -l成功、routesは実Artisan起動/生成で構文確認済み（配備前lintにも含める）。ソースdiff --check成功。

限定配備案: 固定元f7ff846da/main/追跡変更なし/取得固定SHAと今回ソース許可一覧を照合し、依存・削除なしを確認。新migration2026_11_03_120001_add_work_job_core_fieldsだけを固定版から私有stageへ抽出して--database=mysql --path/--realpath --force。前後のentry/PJA件数、4新列NULL維持と存在を照合し、既存行コピー/バックフィルなしを確認後ff-only。失敗時は停止し自動巻戻し/他migration実行をしない。独立UnifiedMyJobBoxChangelogSeederだけ--database=mysql --force、cache/config/route/view clear、3新ルート確認。既存未追跡5件保持。正確な実行コマンドは `ssh -T silverlamb759@silverlamb759.sakura.ne.jp 'sh -s' < /tmp/myjob-core-stage1-deploy-20261103.sh`。利用者承認前には実行しない。

## 2026-11-03 段階1の限定配備完了・新詳細画面の確認待ち

確定: 利用者の再開指示で保存b71ce41caを再確認し、正確なSSHコマンドへのyesを得て /tmp/myjob-core-stage1-deploy-20261103.sh を実行、exit0。固定元f7ff846da/main/追跡変更なし、取得固定b71ce41caa328530af120900ed43fc906d1ebdc7、27ソース許可一覧/削除なし/依存変更なし/PHP14構文を照合。既定mysqlとDB名silverlamb759_sunbworkを確認後、新migration2026_11_03_120001_add_work_job_core_fieldsだけを--database=mysql --path/--realpath --forceで実行。4新列NULL、entry/PJA/events/diariesの既存ID範囲の件数保持を照合（並行する新規高ID登録は許容）。他migration/一括移行なし。

ff-onlyでb71へ更新、manifest全参照/members base/WorkDetailを確認。独立UnifiedMyJobBoxChangelogSeederのみ--database=mysql --force、config/cache/route/view clear成功。GET/PUT/DELETEの3新ルート登録を確認。最終追跡変更なし、未追跡5件は配備前後完全一致。sales接続/業務内容出力/作業試験登録なし。共有Claude作業は未変更。

状態: M01/M02の段階1経路とM07/M08/M09/M12の基盤はコード検証・限定配備済み、今回の利用者画面確認は未実施。M03/M04等の完了・続き操作、全子孫系列、旧フォーム相互継続/M11は未完。一般公開・カレンダー入口再編は保留。既確認の保存/分類/単位/青色を再依頼しない。

次の一手: 情報出版SuperAdminで次の新規3点を画面確認してもらう。
1. 統合BOX（試用）の簡易入力由来の既存行で「詳細」を開き、作業名・日付時刻・数量等と「時間の内訳」が表示される。未来の予定の記録時間は0、予定枠とは別表示である。
2. その詳細の「編集」から進み・結果など1項目を変更保存し、詳細へ戻って反映される。BOXでも同じ行が更新され、余分な行が増えない。
3. カレンダーで同じ作業をクリックすると詳細が開き、「戻る」でその日のカレンダーへ戻れる。
全項目問題なければ「成功」、違いがあれば番号と結果。削除/続き/完了/系列/別アカウント/全評価比較/上長レビューは今回の依頼に含めない。
