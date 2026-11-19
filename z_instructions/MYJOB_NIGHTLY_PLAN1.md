# 新マイジョブ 深夜自動完了の互換修正 設計1
2026-11-19 JST。基準dbacf8381（休暇予定確認済みの保存版）、直近本番証拠7e2652606。worktree /tmp/sunbwork-myjob-nightly-20261119、branch feature/myjob-nightly-completion-20261119。共有主作業985e6fffb/Claude/ziggyの変更は保護。現本番は後で正確SSH事前承認により確認し、別作業を含む最新版を保持して固定差分を作る。

## 指示・調査
利用者「旧マイジョブは夜0時に完了、新はならない。調査し従来と同じに」。TODO繰り返しの画面4条件の前に本件を優先する。休暇予定4条件は確認済み、反復しない。
AGENTS/JOB_EVALUATION_CONTROL1/ROADMAP1、MYJOB_UNIFIED_BOX資料、PersonalWorkEntry/WorkJobMutation/WorkJobLegacyCompletion/WorkJobInput/UnifiedMyJobBox/PersonalWorkSchedule、CONSOLIDATED_05、AutoCompleteMyJobs/routes console/config appを参照。AutoCompleteMyJobsはproject_job_assignmentsの自己割当だけを対象とし、personal_work_entriesを参照しない。旧対象0件なら早期returnするため、単純な末尾追加では新形式だけある日に動かない。原因は新保存先の対象漏れ。
実行はroutes/console.phpの毎日00:05で、0:00ちょうどではない。日付の境界はJST当日00:00。旧はscheduled_atを優先し、未指定時desired_end_date<今日を使う。完了フラグ/ステータス3と紐付イベントタイトルを更新するが、通常の手動完了通知/外部連携は呼ばない。
新はBOX/詳細/カレンダーがcompleted_atを参照しており、そこを更新すれば既存表示も揃う。UI/ビルド/新DBは不要。手動WorkJobMutationをバッチで呼ぶと系列祖先や依頼/校正/通知/外部DBを巻き込むため、今回の互換修正には使わない。

## 修正仕様（確認案）
同じ既存auto-complete:my-jobsコマンドへ、新形式の「本人の作業日が昨日以前、completed_at=NULL、source_event_id=NULL」の対象を追加。公開対象サン・ブレーン情報出版の同社同部署の本人行に限定し、会社/部署/利用者IDの不整合や他社/他部署行は除外。source_event_id付きの会議/通常予定からの派生記録、当日/未来、日付なし/完了済み、private TODO/休暇予定は対象外。
続きの関係はたどらず、各行の作業日で独立判定。昨日までの親だけ完了、当日以降の続きは未完了。新形式から参照した旧元の未来の作業や他の依頼/校正へ伝播しない。
旧対象の条件/ステータス/紐付イベントの既存処理を維持。新対象だけの場合も処理するよう早期returnを両方0件のときに変更。新旧の件数を分けて出力。--dry-runは両方の件数だけで一切更新しない。新形式の更新は対象条件を実更新時にも再照合し、完了済み時刻を上書きしない。更新値はcompleted_atとupdated_atだけ。数量/actual_minutes/メモ/日報/版/予定時刻を変更しない。再実行しても対象0で二重完了にならない。
JSTをコマンド内とスケジュールへ明示し、時刻00:05は維持。既存cronや新しいcronを追加しない。手動でまとめて業務データを補正せず、次の既存定期実行で昨日以前の未完了もまとめて追いつく。配備後は許可を得たコマンド内で--dry-run/登録スケジュールを確認するだけで、実更新コマンドは手動実行しない。

## 変更候補と検証
AutoCompleteMyJobs.php（対象追加/early return/件数）、routes/console.php（timezone明示）、合成Integrationテスト、ChangelogSeederのidempotent記録（実行なし）、CONSOLIDATED_05/管理/再開/台帳。Vue/JS/routes web/DB/migration/画面資産は変更しない。
合成SQLite memory（破壊的DBリセットなし）で修正前の新形式対象漏れを再現し、旧0件/新だけ、混在、JST境界、当日/未来/完了済み/元イベント/異会社部署除外、続き親のみ、raw完了表示DTO、保存値維持、dryrun、再実行、旧scheduled優先/desired fallback/日付なし/受けた依頼除外/タイトルprefix一度だけを検証。実行するDBはmemoryとテスト冒頭で明示確認。既存手動完了/続き/校正通知の合成回帰も実施する。
配備は本番版と作業中ファイルを正確SSHコマンドで事前承認後に確認し、固定差分のみ。PHP構文とmanifest byte同一を検査してから反映。migration/Seeder/本番実更新コマンドは実行せず、修正後dryrunとschedule一覧で確認。実装/コード検証/配備/次回定期実行/利用者表示確認は分けて記録する。

次の1手: 具体設計を確認後に実装。TODO繰り返し4条件の確認は本件が一区切り後へ保留。
