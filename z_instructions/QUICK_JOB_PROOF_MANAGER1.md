# 簡易ジョブ校正依頼：進捗管理

2026-11-10 JST。

- 利用者要望: 進行表・銀本は旧式で対応、校正だけ簡易ジョブから渡す試用を希望。
- 調査: 入口/元簡易ジョブ参照とも現状なし。旧式は既存ProofRequestModal経由。
- 設計: 利用者yesで具体設計承認済み。
- 実装/合成検証: 完了（下記91件1022断言）。配備/利用者試用/窓口レビュー: 未実施。
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
