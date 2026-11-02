# マイジョブ入力・BOX統合 管理

更新: 2026-11-02 JST。設計正本: MYJOB_UNIFIED_BOX_PLAN1.md。親: USER_WORK_HUB_MANAGER1.md。

## 現在地

| 状態 | 証拠 |
| --- | --- |
| 統合設計を進める | 利用者了承済み。「一般Userにはまだ公開しないので慎重に」と再確認 |
| 一般公開 | 未承認。INFO SuperAdmin試用と分類管理許可を分離する |
| 現行実装調査 | 保存70ca0778eのコードを確認。旧BOXは締切/登録月基準、独立簡易記録は別保存、proof実行枠は旧BOX除外 |
| 設計資料 | PLAN/MANAGER/PROMPTを作成。アプリ変更なし |
| 3確認への回答 | 利用者が全て選択済み。未予定保持/従来ジョブ1行で複数日/簡易otherを含めると確定 |
| 統合一覧コード/保存処理 | 未着手。設計承認と区別 |
| コード検証/限定配備/統合画面確認 | 未実施 |

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
- 保留: 未予定の月所属、簡易記録に完了を設ける必要性、入力元間の追加の続き管理、一般公開日。
- 重複対策: source_event_id/assignment/event/supersedesの明示IDを用いる。title一致は不可。校正のcoordinator_assignment_idと続きのsource_assignment_idは別物。

## 引継ぎ・保護

専用ブランチfeature/project-broad-link-20261102、/tmp/sunbwork-project-broad-link-20261102で資料を作成。共有/home/w229/SunBworkのClaude変更は未編集。本番最終確認5462a2b09で、今回の統合設計作業ではSSH/DB読取/配備なし。既存のお気に入り/分類/機密/休止の利用者確認を再依頼しない。

今回読んだコードはMyProjectJobController::index、ProjectJobAssignmentByMyself、PersonalWorkEntry、MyJobBox/Index、MyJobCreateModal、PersonalWorkSchedule、DepartmentWorkController、CalculatesEventTime、EventControllerの依頼/校正処理。過去会話の未読記録を読んだとは扱わない。日付・表示形式の曖昧さは実装前に確定する。

次の一手は、PLAN末尾の初回読取試用（SA専用ボタン/本人一覧/日付検索/元詳細リンクだけ、保存・公開変更なし）の実装範囲を利用者に確認すること。Q1〜Q3の再質問は不要。

2026-11-02 Q1〜Q3回答反映: 利用者は未予定を残す/従来ジョブは1行で複数日/簡易otherを含めるを選択。PLANとPROMPTへ確定記録。初回はSuperAdmin専用の閲覧プレビューを現行BOXの別ボタンから開き、予定ありは作業月、未予定は従来の締切/登録月とする具体案を提示する。アプリ実装・配備・一般公開はまだ行っていない。次は初回実装範囲の承認。
