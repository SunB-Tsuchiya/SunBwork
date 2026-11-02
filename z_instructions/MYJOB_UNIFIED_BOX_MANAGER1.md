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
| 限定配備/統合画面確認 | 未実施。コード検証成功から推定しない |

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
