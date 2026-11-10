# 簡易ジョブ校正依頼：進捗管理

2026-11-10 JST。

- 利用者要望: 進行表・銀本は旧式で対応、校正だけ簡易ジョブから渡す試用を希望。
- 調査: 入口/元簡易ジョブ参照とも現状なし。旧式は既存ProofRequestModal経由。
- 設計: 利用者yesで具体設計承認済み。
- 実装/合成検証: 完了（下記91件1022断言）。配備: 成功（4e83575c9）。利用者試用/窓口レビュー: 未確認。
- 一般公開: 未承認。共有Claude作業未編集。本番通知の試験送信なし。

次の一手: 元参照を保持した試用入口追加・まず受け渡しまで（元の自動完了は追加しない）の具体案を利用者に確認する。承認後に影響する既存校正画面と通知のtransaction/commit動作を精査し実装する。


## 2026-11-10 設計承認・簡易から校正受け渡しを実装、合成検証成功（未配備）

確定: 利用者yesでQUICK_JOB_PROOF_PLAN1の具体案を承認。まず校正窓口への受け渡しまで、元簡易ジョブの自動完了は追加しない。進行表・銀本は旧形式対応を維持、新式の一般公開は未承認。

実装: 未完了の試用本人詳細から既存ProofRequestModalへ接続。専用POSTとQuickWorkProofRequestは本人/実会社部署/INFO SuperAdmin/sunbrain/contextを認可し、元行lock後に再検証。案件/依頼者/元参照はサーバで確定、旧割当/セル/依頼者/任意案件等の混在注入は拒否。新規依頼と同会社・部署の校正窓口全員へのDB通知を同一transactionで保存（既存通知方式はDBのみで放送なし）。窓口なしは入力エラー、再操作は既存依頼を返し通知を再作成しない。

proof_requests.source_personal_work_entry_idのnullable/index migration1件を追加。新規元付き依頼だけ実会社部署/contextでqueryを限定、旧依頼の既存query条件は維持。受信一覧/割当ページ/本人の校正状況で元作業名・分類を必要範囲だけ表示し、結果本文/ファイル/数量/予定等の生モデルは隠す。校正側から本人専用詳細への無権限リンクを作らない。実ProjectJobに既存管理表があっても、この新規元付き依頼は元情報の見える受信割当ページへ進める（旧依頼の管理表リダイレクトは維持）。

元参照保持のため、依頼が残る元の削除は完了後も拒否。pendingキャンセルで依頼を削除した後は再依頼可。既存校正完了は元簡易ジョブを完了させない。モーダルは送信中の二重クリック/閉じるを抑止し、失敗時入力保持とエラー表示、成功時だけ閉じる。旧送信payloadは維持。小画面は日付行を折り返す。Changelogは専用quick-proof-20261110（30字以内）、共通Seederへ呼出し1行、統合カレンダー資料更新。

検証: SQLite :memory:のみでIntegration91件1022断言成功（既存81件+新10件）。元/案件有無/タイトル/UTC締切/所属と本人/context/任意ID注入/窓口なし/完了済み拒否、重複なし、2人目通知失敗の全rollback、キャンセル再送、元削除保護、旧POSTへの参照注入拒否、校正完了で元未完了保持、旧依頼の可視条件保持を確認。実並行MySQLの負荷試験は行わず、元行lockによる直列化設計と再操作合成検証を区別する。

実Vueの新旧送信先/payload・JST→UTC・送信中二重操作・エラー入力保持・成功終了・6変更template compileが成功。既存のunifiedMyJobInput/workHubNavigation/workJobDetailsも成功。PHP10構文成功。対象ESLintエラー0、既存Assign.vueのupdatedSlots未使用警告1件は無関係のため維持。/members本番build成功、manifest482/参照資産513/欠落0、既存ソースentry欠落0、旧hash資産保持・tracked資産削除0。manifest SHA256 5d4e9dff430add8269bd0fd2f218ec4c95272f349f95b0dc706b0275aeb18db4。Ziggy実生成と専用POST1件の定義を照合し静的定義へ追加、他の定義を維持。

環境補正の記録: 初回buildは隔離vendor参照不足で失敗、読取用symlinkを整えて最終build成功。Ziggy初回はvendorの相対autoload先不一致で停止、標準/work/vendor読取mountへ戻して生成成功。生成先の絶対パスがbase_pathで二重になり、隔離work/storage/appに一時出力が残った。権限により移動できないため未追跡一時出力としてコミット対象外。共有vendor/環境変数/本番/業務DBは変更していない。中間失敗を最終成功の証拠にしない。

状態: 設計承認・実装・コード検証・本番build完了。配備/追加migration実行/変更履歴seed/実通知受信/窓口レビューは未実施。既成功画面は反復しない。次の一手は保存した今回差分を基準に、正確な読取SSH承認で本番最新HEAD/作業中ファイルを確認し、他作業を保持した固定配備手順を作る。新式一般公開はしない。試験通知や校正依頼をCodexから本番作成しない。


## 2026-11-10 本番最新版54d1c68e6を保持した統合版検証成功・未配備

利用者yesで提示済みread-only SSHを承認し、同一コマンドを実行。HEAD54d1c68e62580ec8311c3a6a1fad3eb15a1b2bc8/main、追跡対象変更なし、既知未追跡5件とmanifest未変更を確認。旧最終6ef後の別作業「営業ダッシュボード/カレンダー・部署別カレンダー分離」が追加されていた。ローカル同SHA objectで差分を照合し、隔離ブランチの校正試用39c1e102eへ取り込んだ。共有作業ツリー/CLAUDE.md/本番sales DBは未編集・未照会。

統合競合はmanifestと1行Ziggyのみ。Ziggyは54d版の全ルートを保持し、生成照合済みの校正POST1件だけを追加。manifestは統合後の全ソースを/membersで再build。ChangelogSeederは自動mergeで他作業を保持し、54d比は専用Seeder呼出し1行のみ。営業/Clerk/共通AppLayout/HandleInertiaRequests/routes.suna/依存/config/bootstrap/AGENTS/CLAUDEのソース差分0。

統合後にIntegration91件1022断言・実Vue校正新旧/入力/詳細/ナビの4検証・PHP10構文が再成功。members build成功、manifest484 entry/515資産/欠落0、54dのソースentryと旧hash資産を保持。静的Ziggyは54d版全定義一致+新規1件だけ。最終manifest SHA256 51a14e0dc78a75fe178225d962bd3ef440edb1bc55f54a8b59f7730c9b5cc519。自動chunkキーの変更はbundle再構成によるもので、既存page sourceの欠落ではない。

次の一手: この統合版を保存し、固定元54d/固定先・source許可一覧/削除なし/既知未追跡5件/本番用manifestを照合する/tmp/quick-work-proof-deploy-20261110.shを準備。追加列migration1件だけを旧コードの段階で--database=mysql --realpath --path=<対象ファイル> --forceにより先行追加し、元参照列がない新コードをWebへ出す時間を避ける。その後ff-onlyし、QuickWorkProofChangelogSeederだけ--database=mysql --force、route/view cache clear・最終作業状態確認。他作業のmigration/seedは実行しない。正確なSSHとスクリプトを提示して承認後に配備する。現時点は配備/migration/実受信未実施。一般公開未承認・Codexによる試験送信なし。


## 2026-11-10 固定配備手順を準備・承認待ち

統合版4e83575c97cd216124ee46def506f3d8cb317406を保存。固定元54d1c68e62580ec8311c3a6a1fad3eb15a1b2bc8、固定先上記、branch feature/myjob-request-checks-20261110の限定配備手順を/tmp/quick-work-proof-deploy-20261110.shへ作成。sh -n成功。SHA256 3a0527484c3e47b778b4aaebca064b336de7a594fc9d73e6cf4dbb0d0ee81108。同一コピーはstorage/app/private/codex-quick-proof-deploy-20261110/に保管。

手順はmain/HEAD/追跡変更なし/既知未追跡5件/既存manifest66efc751501fabb8849bb2771adc852c8d852915cdbc7f76ba333658e63d2911を確認してfetch。祖先/ソース33許可一覧/削除なし/他作業の営業・Clerk・AppLayout等ソース不変/新規migration1件/PHP10構文/target manifest484・515資産/members baseを照合する。既定mysql DB名を確認後、追加列1件だけを旧アプリ中に--database=mysql --realpath --path=<固定migration> --forceで適用し、ff-only。route:clear、配備資産検査、QuickWorkProofChangelogSeederだけ--database=mysql --force、view:clear、追跡変更なしと未追跡5件保持を確認。既存営業カレンダーのmigration/seedや本番sales DBは操作しない。途中停止しても追加列を自動削除しない。

実行候補（まだ未承認・未実行）:
ssh -T silverlamb759@silverlamb759.sakura.ne.jp 'sh -s' < /tmp/quick-work-proof-deploy-20261110.sh

次の一手: 同じスクリプトを利用者へ示し、この正確なSSHの承認を得る。一般公開はせず、本番校正依頼/試験通知は作らない。配備後は新たな校正入口/窓口への受け渡しの確認だけを依頼する。既に成功したモバイルBOX/旧未予定・複数日/通常依頼の登録・完了・通知を再試験しない。


## 2026-11-10 簡易ジョブ校正依頼の限定配備成功・利用者試用待ち

確定: 利用者yesで提示済みSSHと固定スクリプトを承認。同一SHA256 3a0527484c3e47b778b4aaebca064b336de7a594fc9d73e6cf4dbb0d0ee81108の/tmp/quick-work-proof-deploy-20261110.shを実行しexit0。固定元54d/main/追跡変更なし/既知未追跡5件/旧manifest、祖先とsource33許可一覧/削除なし/営業・Clerk等ソース保持/追加migration1件/PHP10構文を照合。既定mysqlとsilverlamb759_sunbworkを確認後、2026_11_10_180001だけを--database=mysql --path=<固定抽出ファイル> --realpath --forceで先行適用成功。4e83575c97cd216124ee46def506f3d8cb317406へff-only、route:clear、manifest484/515資産/欠落0/members base、QuickWorkProofChangelogSeederだけ--database=mysql --force、view:clear成功。最終追跡変更なし・既知未追跡5件維持。実行scratch /tmp/sb-quick-proof.ApsURGを配備証拠として残す。

実装/合成検証/本番限定配備は完了。実画面/実受信/窓口レビューは未確認。新式一般公開は未承認のまま。既存営業カレンダーのmigration・その他seed、本番sales DB、仕事の作成/完了、校正依頼の試験送信、過去通知の再送は行っていない。

次の一手: 情報出版SuperAdmin本人の仕事・案件→新しいマイジョブBOX→未完了の簡易形式ジョブ詳細を再読込し、「校正依頼」で試用と分かる件名を確認して利用者自身で送信してもらう。送信すると同会社・部署の校正窓口へ実際の通知が届くことを先に明示する。今回依頼する確認は(1) 元詳細が校正依頼済みとなり、元ジョブは未完了のまま、本人の「校正状況」に依頼1件がある、(2) 校正窓口の受信BOXに同じ依頼1件と元作業名・案件/分類が表示される、の2点。両方問題なければ「成功」、違えば番号と結果、窓口画面が見られなければ「2は確認不可」と回答を依頼。窓口未登録エラーならその文言を報告してもらい、勝手に別部署へ送信先を広げない。まだ依頼2点の成功と記録しない。

元ジョブや校正依頼を完了する試験、旧進行表/銀本の再試験、成功済みのモバイル/旧未予定・複数日/通常依頼の登録・完了・通知は依頼しない。


## 2026-11-10 利用者の表示指摘・詳細上部操作と統合メニューの復旧を先行

確定: 利用者は簡易詳細が縦長で下部操作に気づかないと指摘。旧マイジョブ同様のコンパクトな上部情報/操作配置を希望。また「仕事・案件」が消え従来タブへ戻ったため、SuperAdmin限定の統合表示への復旧を依頼。直前の校正送信/受信2点への成功回答ではなく、両方未確認のまま表示修正を先行する。

調査根拠: 保存562c339ed/配備4e83575c9のコードを確認。54d1c68e6のHandleInertiaRequests変更でuserWorkHub共有propとauth.canManageBroadProjectsが削除されている（ac363比diffで確認）。UserNavigationTabsの統合表示コードは残っているため共有prop欠落が従来タブへ戻る原因。役割制限を撤廃して直すことはしない。

実施方針（利用者の修正依頼の範囲内、主なソース変更2件）: Middlewareに既存サービスの共有propを復元し、実会社context一致/INFO SuperAdmin/一般会社除外を保持。営業用clerkCalendarRoutePrefix等の他作業は保持。同時に欠落した既存分類管理フラグも従前の認可サービスで復元。WorkDetailは旧Events/Showの作業名→日時→操作と同じ順の上部カードへ再配置し、詳細/時間内訳/系列は下部に保持。旧マイジョブの既存操作を変えずに簡易側の配置を合わせる。元詳細の保存/完了/校正等の処理は変更しない。検証は共有propを実Middlewareから取得する合成確認と、実Vue上部操作順/新旧送信退行。UI修正とDB/業務処理変更を混同しない。

次の一手: 上記2ソースの修正と回帰検証。今回の修正配備は別途、最新本番保持と正確なSSH承認後。一般公開未承認・本番通知試験なし。


## 2026-11-10 詳細上部操作・限定統合メニュー復旧を実装、検証成功（未配備）

主なソース変更はHandleInertiaRequestsとWorkDetailの2件。54dで落ちたuserWorkHubを既存UserWorkHub.navigationから共有し、明示会社contextが実所属と違う場合はenabled=falseを返す。INFO SuperAdmin/非一般会社の制限と、営業用clerkCalendarRoutePrefix/ポートフォリオ等の新しい共有データを保持。同時に落ちていたcanManageBroadProjectsは既存BroadProjectTree.canManageで復元（統合メニューの試用許可とは別判定）。

簡易詳細を旧Events/Showに合わせ作業名/分類・日時・予定枠・操作の上部カードに再配置。校正依頼/詳細入力/続き/カレンダー/編集/完了を1か所だけに置く。完了確認と詳細編集もカード直下に置き、上で押して下部の確認に気づかない問題を避ける。案件/作業詳細/時間内訳/系列の値は保持し余白を縮め、時間計算の説明だけ折りたたむ。旧マイジョブの画面・既存保存/完了/通知処理は変更しない。全端末で1画面に収まると断定する変更ではなく、主要操作を詳細情報より先に出す。

検証: 実Middleware.shareを通る新規合成1件34断言で、本人INFO SAのenabled/未登録件数、一般role/別部署/一般会社/context切替/guestの無効化、同一context有効化、営業用prefix保持と既存分類管理フラグを確認。従来のフロントprop注入だけでは共有項目欠落を検知できなかった点を補った。fixtureにteamsが不足した初回エラーは試験データの補完で解消。全Integration92件1056断言成功。実Vue入力/詳細SSRで全操作が最初の概要カードにあり案件情報/時間内訳より先に出ることを追加確認。見出し変更（スケジュール→日時）に旧assertを追随し、数量整数・エスケープ・時間値・既存系列等の検査は維持。校正モーダル新旧/ナビ/ファイル説明のJS検証も成功。対象ESLintエラー/警告0、変更PHP2構文成功、ソースdiff --check成功。

members build成功。manifest484 entry/515参照/欠落0、既存ソースentry欠落0・旧hash資産保持・tracked資産削除0。最終manifest SHA256 6f60dfb818eaa375b1e764da1b500753b15f8d6e49ba2a65642cb626f609a8b3。DB/migration/seed/routes/静的Ziggyの変更なし。共有Claude作業は未編集、営業/部署別カレンダーの変更を保持。

次の一手: 保存後、直前配備の4e83575c97cd216124ee46def506f3d8cb317406を固定元に、HEAD/作業状態が違えば変更前停止する表示修正だけの配備手順を準備して正確なSSH承認を得る。まだ本番の表示修正は未配備/利用者画面未確認。校正依頼の送信/本人状況/窓口実受信の前回2点も未確認のまま保持し、先に今回UIの確認へ進む。新式一般公開は未承認。


## 2026-11-10 表示修正の固定配備手順を準備（未配備）

保存版7280253d0e64f565fc32528e2dedd96129782a2d。固定元は直前に配備成功した4e83575c97cd216124ee46def506f3d8cb317406、今回新たなSSH再読取はしていない。/tmp/quick-work-ui-deploy-20261110.shを準備、sh -n成功、SHA256 d1b96ea17f3823ead5d7a6658a05027ca6f5f15e3ee8e510d52054c0136b3085。storage/app/private/codex-quick-work-ui-deploy-20261110/へ同一コピー保管。

手順は固定HEAD/main/追跡変更なし/既知未追跡5件/旧manifest51a14e...を検証してfetch。source12許可一覧/削除なし/database・routes・依存・設定等の不変/PHP2構文/新manifest484・515/members baseを照合してff-only。配備資産確認とview:clear、最終追跡/未追跡状態保持を確認する。DB照会/変更・migration/seed・校正試験通知は含まない。HEAD等が違えば変更前に停止し、勝手に最新コードを上書きしない。

実行候補（まだ未承認）:
ssh -T silverlamb759@silverlamb759.sakura.ne.jp 'sh -s' < /tmp/quick-work-ui-deploy-20261110.sh

次の一手はこの正確なSSHの承認。配備後には、仕事・案件の限定統合メニュー、簡易詳細上部の日時/操作、校正モーダルを開いてキャンセルできることの今回表示確認を依頼する。校正送信/実受信2点はまだ未確認の別確認として保持。一般公開未承認、共有Claude作業未編集。


## 2026-11-10 表示修正の旧固定配備を変更前停止・最新e279を統合

利用者yesで旧/tmp/quick-work-ui-deploy-20261110.sh（d1b96ea...）を承認。同一hashで実行したが最初のHEAD照合でSTOP: production HEAD differs、exit1。fetch/merge/cache clear/アプリ/DB更新より前に停止し、表示修正はまだ未配備。この旧固定手順は現在の再開先から除外し、繰り返さない。

以前承認済みの同一read-only SSHを再提示して実行し、HEAD e27916cbc58b520874d30e553dac3d0437861175/main/追跡変更なし/既知未追跡5件/manifest未変更を確認。別作業のSunA営業分析画面（段階2）が追加済み。ローカル同SHA objectを照合し隔離ブランチへ統合。競合はmanifestのみ、統合後ソースからmembers再buildで解消。営業分析controller/service/画面/部品・ChangelogSeeder・Ziggy・routes.suna・依存・設定等はe279比差分0。営業分析の実データ/本番sales DBは照会しない。

統合後Integration92件1056断言と実Vue入力/詳細上部配置/校正新旧/ナビ/ファイル説明の検証を再成功。members buildも成功、manifest488 entry/519参照/欠落0、最新e279のsource entryと旧hash資産保持、tracked資産削除0。manifest SHA256 b83a47754f4dbc1d29f7060a4623db59c280f4aade052f56970611251c6dffc4。

次の一手: この最新版保持版を保存し、固定元e279の別名/tmp/quick-work-ui-deploy-20261110-v2.shを準備。今回も実行コード差分は共有Middlewareと簡易詳細の2件だけ、DB/migration/seedなし。旧手順の承認を新版へ拡張せず、正確な新SSHを提示して承認を得る。表示修正/校正試用実受信は未確認、一般公開未承認、共有Claude作業は未編集。


## 2026-11-10 e279保持版と表示修正v2手順を保存・承認待ち

検証済み統合版705a8e6da473222b6b081ff4fd9dd06ba408de36を保存。固定元e27916cbc58b520874d30e553dac3d0437861175→同固定先の/tmp/quick-work-ui-deploy-20261110-v2.shを作成、sh -n成功。SHA256 a571417de9876869d873467985905878dc691459030c102316c908f1804c4500。同一コピーはstorage/app/private/codex-quick-work-ui-deploy-20261110-v2/。

固定HEAD/main/追跡変更なし/既知未追跡5件/本番manifest ffef87fca59e10f96adbd926a37685b03f4d0d67717e0495da17e63dcf5280ccを照合。source12許可一覧/削除なし/database・routes・依存・設定不変/PHP2構文/488 entry・519資産/members baseを確認してff-only。配備資産とview:clear、最終変更/未追跡状態保持を検査。DB照会/更新・migration/seed・試験通知は含めない。最新営業分析のcontroller/service/画面/部品・ルート・Ziggy・変更履歴はe279の内容を維持する。

実行候補（v2はまだ未承認・未実行）:
ssh -T silverlamb759@silverlamb759.sakura.ne.jp 'sh -s' < /tmp/quick-work-ui-deploy-20261110-v2.sh

次の一手: この新しい正確なSSHの承認。旧v1の承認は新版へ拡張しない。表示修正の実配備/利用者画面と、校正試用の実受信2点は未実施/未確認。新式一般公開未承認。
