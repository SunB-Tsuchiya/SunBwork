# 日報閲覧 新マイジョブ反映 設計1

2026-11-27 JST。利用者「修正しましょう」により反映漏れの修正着手を承認。以下の具体設計3点は利用者が承認済み。実装/検証の状態はMANAGERを参照する。調査資料DIARY_REVIEW_TIMELINE_AUDIT_20261127.md、AGENTS/JOB_EVALUATION_CONTROL1/ROADMAP1/CONSOLIDATED_01/05を参照する。
基準aac8f2b6d（調査資料）、本番同日の承認済み読取3630c79ab/main・追跡clean・既知未追跡6件。origin/main b1d57e1ddと主作業985e6fffbは本番と異なる。隔離/tmp/sunbwork-diary-view-audit-20261127を使用し、Claude/ziggy/売上作業は保護する。

## 問題と対象
Admin/Leader/SuperAdminが他人の日報を開く場合、新形式が本人限定の旧試用判定で落ちる。日報管理者にも必要propがない。旧eventsだけが見え、欠落がエラーにならない。mineを単にONにすると閲覧者本人の予定が混ざることを合成再現済み。
対象は既存日報閲覧の詳細タイムテーブル。既存の一覧/日付別/本文/既読/コメント/勤務記録の保存は維持する。本文未作成かつ新形式だけの日を一覧へ追加すること、TODO/休暇を新たに日報へ公開すること、時間集計や評価・完了・通知の変更は対象外。一般Userの入力画面や個人カレンダーの挙動も変えない。

## 取得方法（新API・migrationなし）
日報のshowが持つ日報ID・所有者・日付と閲覧許可を基準に、新形式の表示用イベント配列をInertia propsで渡す。別途の本人mine APIを有効にする案を置換し、新しいURLや対象user_idを自由指定するAPIは作らない。
共通の読取Serviceで、対象Userの現在の会社/部署とINFOの一般公開条件を確認し、対象日・本人保存のPersonalWorkEntryだけを読む。別会社/別部署の不整合な関連、元イベント派生、旧ジョブとの置換で二重になる記録は既存のPersonalWorkEntry/UnifiedMyJobBox/PersonalWorkScheduleの規則で除外する。題名の一致で結合しない。SELECTのみで、日報への紐付け/作業のコピー/完了/再計算/通知をしない。
既存eventsの取得・描画は維持し、今回の新形式配列を共通タイムテーブルへ合成する。対象日報本人と閲覧者が異なってもmineを呼ばず、閲覧者自身の作業を取得しない。取得処理が失敗した場合に正常な空配列と混同しないよう、エラーを明示する。既存eventsが失敗する場合のエラー表示も回帰確認する。

## 認可・情報の扱い
- Admin/Leader共通のrequireAdminPermission/requireLeaderPermissionとbuildPermittedUserIds、日報管理者のbuildPermittedUserIdsを維持し、許可された日報の人だけを新形式取得へ渡す。Adminの既存showの例外を新形式取得の無条件な全社権限へ拡張しない。
- Adminは所属会社、Leaderは既存のcan_read_diary付き部署/ユニット/サブリーダー範囲、日報管理者は設定済みの会社/部署許可を尊重する。SuperAdminは会社context選択を尊重し、グローバルモードも既存の日報閲覧対象だけを扱う。
- 非公開の作業や機密案件は既存PersonalWorkScheduleの閲覧用投影を基本にする。本人/案件関係者として既存の権利がある場合以外は名称・案件・メモ等を伏せ、予定枠だけを表示する。会社contextを越える名称漏えいは防ぐ。日報本文の閲覧権限だけで非公開作業の詳細権限を新たに付けない。
- 「部署の作業の表示対象」スイッチは部署一覧用。日報閲覧の権限判定へ無断流用しない。本人のmine APIの認可は維持する。
- 新propsは描画に必要な識別子（personal-work-等の型付きID）、表示用題名、開始/終了、色、readOnlyだけを渡す。編集バージョン/入力用オブジェクト/本文や添付情報を載せない。閲覧だけで既読やデータを書き換えない（明示的な既読操作は既存どおり）。

## 時刻とUI
日付はdiary.dateのY-m-dを使い、JSTとして開始/終了を作る。toISOStringや重複+9時間変換を今回の新形式配列に使わない。旧午前/午後/終日形式は対象本人の勤務枠で既存slotIntervalに従う。新形式と旧形式を同じ青系で表示し、readOnlyとしてクリックから編集へ進ませない。昼前/早朝/夜間の予定が切れないよう既存表示時間帯調整を適用する。AppLayout/戻る/編集削除など既存のレイアウトを維持し、新しい入力操作やメニューを足さない。

## 変更候補と検証
候補: 日報用読取Service（新規）、Diaries/DiaryInteractionControllerとDiaryManager/DiaryInteractionController、Interactions/Show.vue（必要なら共有helper最小限）、合成PHP/実Vueテスト、CONSOLIDATED_01/05、ChangelogSeeder、設計/管理/再開資料。既存認可の判定を複製する場合は権限変化に強い共通化を検討するが、無関係な通常eventsの全面改修はしない。
合成SQLite memoryのみでAdmin/Leader/日報管理者/SuperAdmin context、非許可/別会社/別部署、閲覧者本人の混入なし、非公開/機密の伏せ字、対象日/JST/勤務枠、旧新の二重表示、本文・既読・勤務実績・完了・通知の不変を検証。実Vueで旧予定と新作業が一緒に出る、readOnly/時間枠/読み込み失敗を確認。実DBのリセット/売上参照/実データの無断読取はしない。
本番は配備前に版/作業中ファイルを再照合し、他作業を含めない固定差分と/members buildで配備。正確SSHとSHA256の事前承認を受ける。migration/Seederは今回実行しない。実装/コード検証/配備/利用者画面は別状態として記録し、既確認のマイジョブ・校正・工程確定は繰り返さない。

次の1手: propsによる対象者本人の新形式取得、既存日報認可の維持、非公開/機密の既存表示保護という具体設計を利用者に確認し、承認後に実装する。
