<?php

namespace Database\Seeders;

use App\Models\Changelog;
use Illuminate\Database\Seeder;

class ChangelogSeeder extends Seeder
{
    public function run(): void
    {
        $entries = [
            [
                'version'      => 'tzfix-1',
                'title'        => '日付・時刻のずれを全体点検し、カレンダー／工数集計／日付表示の不具合をまとめて修正',
                'released_at'  => '2026-08-13',
                'summary'      => '校正ジョブの時刻が9時間ずれる不具合の修正をきっかけに、日付・時刻の扱いをシステム全体で点検し、見つかった不具合をまとめて修正しました。主なものは、①朝9時より前に始まる校正予定がカレンダーに表示されず工数集計からも漏れていた問題（8時シフトの方が対象）、②校正コーディネーターの割当画面で、校正者の夕方（15時以降）の予定が「空き」に見えてしまい二重割当を招く恐れがあった問題、③下版日・入稿日・交通費の日付などが1日前に表示されていた問題、④校正カレンダーの「前日／翌日」ボタンが2日単位で移動していた問題、⑤進行表カレンダーのメモが保存できなかった問題です。あわせて、深夜0時から朝9時の間に画面を開くと「今日」が前日として扱われる箇所を全体で洗い出して直しました。',
                'design_files' => [],
                'claude_notes' => 'events テーブルが proof=UTC / 通常=JST の混在保存であることに起因する不具合を、読み書き・期間フィルタ・フロントの日付生成の全レイヤーで是正した作業。z_instructions/TZFIX_PLAN1.md / TZFIX_MANAGER1.md に全記録あり（完了後 archived へ移動）。' . "\n\n" . 'フェーズ1（期間フィルタ）: EventController::index / DashboardController / Leader\\WorkloadAnalyzerController(3箇所) / ProofCoordinator\\CalendarController::pickerData の期間絞り込みが DB の文字列比較のみで完結しており、proof(UTC保存)が範囲から漏れていた。CalendarEventsController::range() と同じ「±9hバッファ取得 → resolveJstCarbon() で JST 判定」方式に統一。8時シフト相当（JST 08:00 = UTC 前日23:00）で検証し、修正前は当日に出ず前日に誤混入していたのが解消。工数集計は event:0 → event:4 に是正。デグレ確認として既存データの集計を md5 比較し修正前後で完全一致を確認。' . "\n\n" . 'ProofCoordinator\\CalendarController では検証により別の2件を発見: (a) 日境界を JST→UTC 変換して starts_at(JST保存) と比較していたため、proof 以前に通常イベントの JST 15:00 以降が当日から欠落していた（割当モーダルで校正者の夕方の予定が見えず二重割当を招く状態） (b) 返却値の $e->starts_at->utc() が datetime キャスト(JST解釈)を経るため proof で二重変換になっていた。' . "\n\n" . 'フェーズ2（date キャスト）: 11モデル17箇所の \'date\' を \'date:Y-m-d\' に変更。migration を確認し全カラムが date() 型であることを確認済み。本番で修正前の JSON がすべて前日15:00 UTC になっていることを実データで確認した（例: plate_down_date DB=2026-05-29 → JSON=2026-05-28T15:00:00Z）。画面実害は SuperAdmin/Billing/Transport/Index.vue が slice(0,10) で切っており日付が1日前に表示、かつ occurrence_date は明細のソートキーでもあった。Changelog.released_at は Vue 側が new Date() 経由のため元から実害なし。' . "\n\n" . 'フェーズ2 の派生対応: ProjectScheduleComment の date キャストを削除しようとして、モデル・Controller が実テーブル(comment / comment_date)と全面的に食い違っており、コメント投稿が実装当初から一度も動いていなかったことが判明（本番レコード0件）。Unknown column \'body\' で INSERT 失敗、存在しないルート coordinator.project_schedules.show へのリダイレクト、Policy 不在による常時403、axios 呼び出しに redirect を返す、の4件を修正。Event モデルの start<->starts_at と同じアクセサ／ミューテタ方式で body<->comment / date<->comment_date を吸収し、Vue は無変更で済ませた。' . "\n\n" . 'フェーズ3（Vue の日付生成）: codex が挙げたのは11箇所だったが実際には47箇所あり、1件ずつ用途を確認して43箇所を toLocaleDateString(\'sv-SE\') に修正。allDay の ±1日計算4箇所（日付のみ入力で UTC 一貫）と、`+09:00` を明示して UTC タイムスタンプを作る正しい用法5箇所は意図的に据え置いた。特に Proof/Calendar.vue の前日/翌日は \'T00:00:00\' でローカル解釈した Date を toISOString() しており常に1日余分にずれていた。Calendar.vue の eventResize/eventDrop は date が UTC・startHour がローカルという不整合だった。本番稼働中のため保存値に影響する箇所（team_meeting_minutes.held_at / transport_expenses.billing_date）を本番DBで調査し、JST 0〜8時台に作成されたレコードが0件でずれの実データが存在しないことを確認（データ補正不要）。' . "\n\n" . 'あわせて残骸だった app/Models/UserDailyWorktype.php を削除（テーブルは 2026-03-28 の migration で user_monthly_schedules に置換済み、参照ゼロ・削除後の動作検証済み）。再発防止として CLAUDE.md に「UTC/JST 混在ルール ⑥期間フィルタ ⑦Vue の日付生成」を追記し、CONSOLIDATED_05 にも反映した。' . "\n\n" . '追加点検（フェーズ4 完了後）: /proof-coordinator/calendar のページ本体 ProofCoordinator\\CalendarController::getSchedulesForDate() にも pickerData と同じ2件のバグを確認し修正。(1) UTC 化した日境界で events.starts_at を比較しており JST 15:00 以降が当日から漏れる (2) 返却値の $ev->starts_at->utc() が datetime キャスト(JST解釈)を経るため proof で9時間ずれる。前提として pja101 には job_type=proof（UTC保存）と NULL（JST保存）が混在していることを本番データで確認済みで、片方だけの対応では不十分なため両方を resolveJstCarbon() 経由に統一した。3ケース（proof 01:15 / NULL 13:30 / NULL 16:00）で検証し、修正前は proof が 2026-04-23T16:15:00Z と9時間ずれ、NULL 16:00 は取得できず漏れていたのが解消。なお getMonthEvents() は proof_requests.deadline / proof_reservations を UTC 前提で正しく扱っており問題なし。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <ul>
    <li>朝9時より前に始まる校正予定が、カレンダーに表示されず工数集計からも漏れていた（8時シフトの方が対象）</li>
    <li>校正コーディネーターの割当画面で、校正者の夕方（15時以降）の予定が表示されず、空いているように見えていた。そのまま割り当てると予定が重なる恐れがあった</li>
    <li>下版日・入稿日・案件メモの日付・交通費の請求日などが、実際より1日前に表示されていた。交通費明細は並び順も狂っていた</li>
    <li>校正カレンダーの「前日」「翌日」ボタンが2日単位で移動していた</li>
    <li>進行表カレンダーのメモが保存できず、エラーになっていた</li>
    <li>深夜0時から朝9時の間に画面を開くと、「今日」が前日として扱われる箇所が多数あった</li>
  </ul>
</section>

<section class="cl-fix">
  <h3>修正・改善内容</h3>
  <ul>
    <li>朝9時より前に始まる校正予定が、カレンダー・ダッシュボード・工数分析に正しく表示・集計されるようにした</li>
    <li>割当画面のタイムラインに、校正者の夕方以降の予定も正しく表示されるようにした</li>
    <li>下版日・入稿日・案件メモ・進行表・管理シート・交通費・派遣契約期間などの日付が、登録したとおりに表示されるようにした（交通費明細の並び順も修正）</li>
    <li>校正カレンダーの「前日」「翌日」ボタンが1日ずつ移動するようにした</li>
    <li>案件カレンダーの初期表示月が、月初・月末で1日ずれないようにした</li>
    <li>進行表カレンダーのメモを保存・編集できるようにした</li>
    <li>校正カレンダー（校正コーディネーター）で、校正者がセットした作業予定の時刻が9時間ずれて表示される問題と、夕方（15時以降）の予定が当日に表示されない問題を修正した</li>
    <li>深夜〜早朝に画面を開いたときに「今日」が前日になる箇所を全体で洗い出して修正した</li>
  </ul>
</section>
HTML,
            ],
            [
                'version'      => 'proof-event-timezone-fix-1',
                'title'        => 'カレンダー：校正ジョブの時刻が9時間ずれる不具合と、修正ページに古い時刻が出る不具合を修正',
                'released_at'  => '2026-08-13',
                'summary'      => 'カレンダーでジョブの時間をドラッグで動かした後にジョブ修正ページを開くと、動かす前の古い時刻が表示される問題を修正しました。あわせて、校正（proof）ジョブの予定を保存するたびに時刻が9時間後ろにずれていく不具合も修正しています。校正ジョブの予定は保存の仕方が通常の予定と異なるにもかかわらず、カレンダーのドラッグ・予定編集・ジョブ修正ページのいずれから保存しても通常の予定と同じ形式で書き込まれていたため、開いて保存するたびに9時間ずつ進んでいました。すでにずれていた本番の予定7件についても正しい時刻に補正済みです。',
                'design_files' => [],
                'claude_notes' => 'events テーブルは job_type=proof の校正ジョブが UTC 保存、通常イベントが JST 保存という混在形式になっている。読み出し側（CalculatesEventTime::resolveJstCarbon / CalendarEventsController / ProofRequestController）はこの規則に従っていたが、書き込み側が常に JST 文字列を書いていたため、proof イベントは保存のたびに +9 時間ずれていた（開く→保存を繰り返すと9時間ずつ累積する）。' . "\n\n" . 'CalculatesEventTime トレイトに書き込み用の逆変換を追加: eventStorageTimezone()（保存TZ判定）/ toEventStorageString()（JST日時→保存形式の文字列）/ rawToJstCarbon()（生値→JST Carbon）。resolveJstCarbon() は rawToJstCarbon() へ委譲する形に整理し、recalcInterruptionMinutes() の旧時刻（$oldStart/$oldEnd）も Carbon::parse による JST 決め打ちからイベントの保存形式に従う解釈へ修正した。' . "\n\n" . '書き込み側は EventController の store() / update() / update_from_calendar() と User\\ProjectJobAssignmentController::update() の4経路すべてを toEventStorageString() 経由に変更。update_from_calendar() は Components/Calendar.vue のドラッグと ProofCoordinator/Assignments/Edit.vue の時刻保存も通る共通経路のため、これで校正コーディネーター画面のずれも解消する。User\\ProjectJobAssignmentController::update() にあった独自の重複時間計算（starts_at を全て JST として比較し increment で累積する方式）は proof で誤差が出るうえ再計算にならないため、共通トレイトの recalcInterruptionMinutes() に委譲した。' . "\n\n" . 'ジョブ修正ページに古い時刻が出る件は別原因。カレンダーのドラッグ（update_from_calendar）が events だけを更新し project_job_assignments を更新していなかったのに対し、修正ページ（AssignmentForm.vue の _isSelfEdit 分岐）は assignment.start_time / desired_time を event より優先して復元していたため。EventController::update() と同じ同期処理を update_from_calendar() にも追加した。ユーザーモードでは start_time=作業開始・desired_time=作業終了として使われている一方、Coordinator 割当では desired_time が締め切り時刻を意味するため、desired_time の同期は自己割当（sender_id = user_id）のときのみに限定し、desired_end_date（締め切り日）は変更しない。' . "\n\n" . '検証はローカルDBに proof イベントが0件だったため、トランザクション内で一時的に job_type=proof に変えて4経路すべてを往復させ、DB生値が UTC・画面表示が JST になること、再保存してもずれないこと、通常イベントの保存形式が変わらないことを確認してロールバックした。本番調査では job_type=proof のイベント11件のうち4件（ev=2802/2840/3073/3718）のずれを検出。判定は project_job_assignments.start_time / desired_time（以前から JST 同期されていた）との一致と、proof_schedules に残っていた作成時の正しい UTC 値（event_id=NULL のレコード id=20 が ev=3718 の元値 01:00〜01:30 を保持）との突合で行った。events 4件と proof_schedules 3件（id=5/8/21）を -9時間 補正し、補正後は全11件が業務時間内（JST 09:00〜17:42）に収まることを確認済み。ev=2840 のみ start_time が NULL で機械的に判定できなかったため、18時以降の校正作業は行わないというユーザーの業務判断で JST 09:00〜13:00 に確定した。なお proof_schedules が指すイベントのうち3件（ev=3059/3315/3811）は assignment が削除済みの孤立イベントで、job_type を引けず JST 解釈にフォールバックするが、生値が JST として自然なため補正対象外とした。PHP のみの変更のため npm run build は不要。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <ul>
    <li>カレンダー上でジョブの時間をドラッグで動かした後にジョブ修正ページを開くと、動かす前の古い時刻が表示されていた。そのまま保存すると、ドラッグした結果が消えて元の時刻に戻ってしまっていた</li>
    <li>校正ジョブの予定は、カレンダーのドラッグ・予定の編集・ジョブ修正ページのいずれから保存しても、時刻が9時間後ろにずれていた。開いて保存し直すたびに9時間ずつ進んでいくため、17時の予定が翌日の午前2時になるといった状態になっていた</li>
    <li>校正コーディネーターの割当編集画面で時刻を直す操作でも同じずれが発生していた</li>
  </ul>
</section>

<section class="cl-fix">
  <h3>修正・改善内容</h3>
  <ul>
    <li>カレンダーでジョブの時間を動かしたとき、ジョブ側の作業開始・終了時刻も一緒に更新されるようにした。これにより修正ページを開いても動かした後の時刻が正しく表示される</li>
    <li>校正ジョブの予定について、保存時に正しい形式へ変換するようにし、保存のたびに9時間ずれる問題を解消した（カレンダーのドラッグ・予定の編集・新規作成・ジョブ修正ページ・校正コーディネーターの割当編集画面すべてが対象）</li>
    <li>すでにずれていた既存の予定7件（予定4件・校正スケジュール3件）を正しい時刻に補正した</li>
    <li>作業時間の重なりによる中断時間の計算も、校正ジョブの時刻を正しく解釈するようにした</li>
  </ul>
</section>
HTML,
            ],
            [
                'version'      => 'diary-comment-notify-1',
                'title'        => '日報：コメントが付いたら「お知らせ」で通知するように',
                'released_at'  => '2026-08-04',
                'summary'      => 'これまで日報にコメントが付いても、日報の作成者本人には何も通知されず、自分から日報を開いて確認するまで気づけませんでした。今回、管理者・リーダー・日報管理者が日報にコメントを書き込むと、コメントを書いた本人以外の日報作成者宛に「お知らせ」（/members/announcements）が自動送信されるようにしました。誰がいつどんなコメントを書いたかがお知らせ一覧・未読バッジに反映されます。',
                'design_files' => [],
                'claude_notes' => 'Diary::addComment() の末尾に notifyCommentToOwner() を追加し、DiaryComment 作成後にコメント投稿者(user_id)と日報作成者(diary->user_id)が異なる場合のみ Announcement(target_type=individual) + AnnouncementRecipient を作成する方式を採用。コメント投稿経路は app/Http/Controllers/Diaries/DiaryInteractionController::markRead() と app/Http/Controllers/DiaryManager/DiaryInteractionController::markRead() の2つが存在するが、いずれも Diary::addComment() を共通で呼んでいるためモデル側1箇所の実装で両経路をカバーできると判断した。既存の Announcement 機能（お知らせ一覧・未読バッジ・AnnouncementRecipient.read_at）をそのまま流用し、新規テーブル・マイグレーションは追加していない。お知らせ本文はプレーンテキスト表示（Show.vue が {{ }} でエスケープ表示）のため、日報詳細ページへのクリック可能なリンクは含めない方針をユーザーに確認済み（本文はコメント投稿者名・日報の日付・コメント本文のみ）。Vue/JS の変更は無いため npm run build は不要、さくらへは PHP ファイルのみ git pull で反映した。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <ul>
    <li>管理者・リーダーが日報にコメントを書いても、日報を書いた本人には通知が届かず、自分から日報を開いて確認するまで気づけなかった</li>
  </ul>
</section>

<section class="cl-feature">
  <h3>追加した機能</h3>
  <ul>
    <li>日報にコメントが付くと、日報の作成者宛に「お知らせ」（/members/announcements）が自動送信されるようにした</li>
    <li>お知らせには、誰がいつどの日付の日報にどんなコメントを書いたかが表示される</li>
    <li>既存のお知らせ未読バッジにもそのまま反映される</li>
  </ul>
</section>
HTML,
            ],
            [
                'version'      => 'prepress-stage-check-1',
                'title'        => '製版伝票ボード：作業チェックを初校/再校/三校/下版の4工程に対応',
                'released_at'  => '2026-07-06',
                'summary'      => '製版伝票ボードの詳細モーダルにある「作業チェック」を、これまでの単一チェックリストから初校・再校・三校・下版の4工程に分けました。各工程で同じ7項目（仕上がりサイズ・トンボ・面付・色数・線数・Nマークのトラップ処理・色調補正）をチェックできるほか、工程ごとに担当した製版部署の作業者を選択できるようになりました。既存のチェック内容はすべて初校工程に引き継がれています。',
                'design_files' => ['STAGECHECK_PLAN1.md', 'STAGECHECK_MANAGER1.md'],
                'claude_notes' => 'prepress_ticket_stage_checks テーブルを新設（prepress_ticket_id + stage enum のunique）し、旧 prepress_tickets.check_* 7カラムはデータを初校行へ移行後に削除。indesign_version/illustrator_version/check_memoは工程共通のためprepress_ticketsに残置。工程行は初回チェック時にfirstOrCreateで遅延作成し、事前に4行を一括生成しない方針。BoardController::updateChecks()をupdateMeta()（indesign/illustrator/memo）とupdateStageCheck()（7チェック+作業者user_id、stage別）に分割。作業者セレクターは既存の「担当色変更」パネルと同じprepressUsers（department.name===\'製版\'）を再利用。codexレビューで3件検出し修正: ①保存成功時にlocalStageChecksのみ更新しticket.stage_checksに反映していなかったためモーダル再オープン時に表示が戻る不具合→syncStageCheckToTicket()で同期、②同一工程への初回チェックが同時に飛ぶとfirstOrCreateがunique制約で競合する可能性→QueryExceptionを捕捉して再取得するリトライを追加、③migration rollback時に旧カラムをfalseで復元し既存チェック値を失う問題→down()内でprepress_ticket_stage_checksの初校行（この時点では未削除）から値を復元するよう修正し、実際にrollback→migrateのラウンドトリップでデータ保持を確認。さくら本番デプロイ時も移行前後でチェック件数（23件）が完全一致することを確認済み。あわせて詳細モーダルのラベル/値間隔をw-32固定幅からgap-2に変更し「1文字アキ」程度に詰め、作成日・入稿日・下版日を1行にまとめた。419（CSRFトークン期限切れ）検知時はwindow.location.reload()で再試行を促すガードも追加。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <ul>
    <li>製版伝票ボードの「作業チェック」は工程の区別がなく、初校〜下版までの作業状況を1セットのチェックボックスでしか記録できなかった</li>
    <li>誰がその工程を担当したかを記録する手段がなかった</li>
  </ul>
</section>

<section class="cl-feature">
  <h3>追加した機能</h3>
  <ul>
    <li>作業チェックを「初校」「再校」「三校」「下版」の4工程に分割し、それぞれ同じ7項目をチェックできるようにした</li>
    <li>工程ごとに、担当した製版部署の作業者を選択できるようにした</li>
    <li>既存のチェック済みデータはすべて「初校」工程に引き継がれ、消えることはない</li>
    <li>詳細モーダルのラベルと値の間隔を詰め、作成日・入稿日・下版日を1行にまとめて見やすくした</li>
  </ul>
</section>
HTML,
            ],
            [
                'version'      => 'actual-copy-1',
                'title'        => 'カレンダー：招待された会議を「実績」として自分の予定にコピーできるように',
                'released_at'  => '2026-07-03',
                'summary'      => '他の人が主催する会議に招待された場合、これまではカレンダー・予定表のどちらからも編集・削除ができませんでした。今回、招待された会議の詳細画面に「実績として記録する」ボタンを追加し、押すとその会議の内容をコピーした自分専用の予定を作成できるようにしました。以後は普通のカレンダー予定として自由に時刻や内容を編集・削除でき、元の会議が主催者側で変更・削除されても影響を受けません。',
                'design_files' => ['ACTUALCOPY_PLAN1.md', 'ACTUALCOPY_MANAGER1.md'],
                'claude_notes' => 'events テーブルに source_schedule_event_id（nullable, onDelete set null）と is_materialized_copy（bool, 独立フラグ）を追加し、ScheduleEventController::materialize() で自分名義の events 行を複製する方式を採用。当初検討した「別テーブルでの表示オーバーレイ方式」は、WorkloadAnalyzerController が Event::where(user_id, ...) で自分名義のイベントのみ集計する実装のため、複製方式でないと工数分析に反映されないと判明し不採用とした。range() 側で自分が既にコピー済みの元イベントを attendeeEvents から除外し重複表示を防止。生成トリガーはユーザーの希望により手動ボタンのみ（自動生成なし）。ユーザー報告の具体事例（本番DB調査）はDB上の孤児レコードではなく、フロント側にスケジュール変更のリアルタイム反映機構が無いことによる表示遅延の可能性が高いと判断し、今回のスコープ外とした。' . "\n\n" . 'カレンダー/スケジュール全体の多角的レビュー（Codex複数回・計6ラウンド）で以下の不具合も発見し修正: ①ScheduleCalendar.vue に materialized イベントハンドラ未実装で /schedule 側の表示が更新されない、②materialize() が meeting_definition_id/destination をコピーしていなかった、③event_item_type_id が null な複製が /schedule に出ない、④authorizeView() が private 可視性の招待イベントを弾いていた（attendee チェックのみに変更）、⑤materialize() で recalcInterruptionMinutes() を呼んでおらず重複時間が反映されなかった、⑥【重要・既存バグ】CalculatesEventTime トレイトの recalcInterruptionMinutes()/EventController・ScheduleEventController の destroy() 内候補クエリが user_id を SELECT していなかったため、重複相手側イベントの interruption_minutes 再計算が常に 0 にリセットされてしまう不具合が本機能と無関係に既存していた（3箇所で get([\'id\',\'starts_at\',\'ends_at\',\'project_job_assignment_id\']) に user_id を追加して修正）、⑦UserCalendar.vue の allEvents で companyEvents と personalEvents の id 重複除去がなく、event_item_type_id 付きの個人予定が /calendar で二重表示される既存バグも発見し dedup ロジックを追加、⑧source_schedule_event_id が nullOnDelete で消えると複製判定ができなくなる問題を is_materialized_copy の独立カラムで解消、⑨materialize() の同時リクエストでの unique 制約違反(500)を catch して 422 に変換、⑩辞退済み(status=declined)の attendee が materialize できてしまう穴を修正。また EventController::update()/destroy()/update_from_calendar() に room_reservation_id の編集不可ガードが無く、ScheduleEventController 側にしか無かった不整合も発見し同様のガードを追加した。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <ul>
    <li>カレンダーは自分の1日の作業タイムテーブルを記録するものだが、他人が主催する会議に招待された予定は編集・削除ができず、実際は30分で終わったのに60分の会議として表示され続けるなど、実態と合わない状態になっていた</li>
    <li>主催者が予定を変更・削除しない限り、招待された側では一切手を出せなかった</li>
  </ul>
</section>

<section class="cl-feature">
  <h3>追加した機能</h3>
  <ul>
    <li>招待された会議の詳細画面に「実績として記録する」ボタンを追加（隣の？アイコンで説明を表示）</li>
    <li>押すと、その会議の内容をコピーした自分専用の予定が作成される</li>
    <li>以後はコピーした予定を通常のカレンダー予定として自由に時刻・内容を編集・削除できる</li>
    <li>元の会議（スケジュール側）が後で変更・削除されても、既にコピーした自分の実績には影響しない</li>
    <li>コピー済みの会議は工数分析・レポートにも自分の作業時間として正しく反映される</li>
  </ul>
</section>
HTML,
            ],
            [
                'version'      => 'iruka-board-fix-1',
                'title'        => '在籍ボード：ステータス反映不具合・モバイル会社切替・更新時刻表示を改善',
                'released_at'  => '2026-07-03',
                'summary'      => '在籍ボードのステータス変更が、カレンダー予定によって直後に上書きされてしまう不具合を修正しました。またモバイル(iOS Safari)でステータス保存に失敗した際に無反応で閉じていた問題にエラー表示を追加。SuperAdminがモバイルから会社コンテキストを切り替えられず部署タブが表示されない問題も修正しました。あわせて更新時刻を「〇時間前」から実際の時刻表示に変更し、視認性を改善しています。',
                'design_files' => [],
                'claude_notes' => 'UserPresenceController::syncCalendarStatus() の手動優先チェックを status_changed_at (新規カラム) で判定するよう修正。当初 updated_at で判定していたが、PresenceBoardSettingsController::update() の並び替え保存でも updated_at が進んでしまい誤判定するとCodexレビューで指摘され修正。IrukaMobileStatusButton.vue の楽観的クローズ(先にモーダルを閉じる)はiOS Safari対策として過去コミット(85965f9c3)で意図的に入れられていたものと判明したため維持し、失敗時のトースト表示のみ追加。SuperAdminContextSwitcher をAppLayout.vueのモバイル用レスポンシブナビゲーションメニューにも追加。IrukaBoard.vue の formatTime() を相対時間表示から絶対時刻表示(当日はHH:mm、それ以前はMM/DD HH:mm)に変更し、文字色を text-gray-300 から text-black/80 に変更。グループヘッダーに「最終更新」ラベルを追加。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <ul>
    <li>在籍ボードのステータス更新モーダルで、会議・来客対応などカレンダー予定が入っている時間帯にステータスを手動変更しても、直後の自動同期でカレンダー由来の値に戻されてしまい「押しても切り替わらない」ように見えていた</li>
    <li>モバイル(iOS Safari)でステータス保存に失敗した場合、エラー表示がないままモーダルだけが閉じ、実際には更新されていないことに気づけなかった</li>
    <li>SuperAdmin がモバイル画面から会社コンテキストを切り替える手段がなく、部署タブが表示されず全メンバーが1グループにまとめて表示されていた</li>
    <li>更新時刻が「1時間前」のような相対表示で、実際に何時に更新されたのか分かりにくかった</li>
  </ul>
</section>

<section class="cl-fix">
  <h3>修正・改善内容</h3>
  <ul>
    <li>進行中のカレンダー予定開始後に手動でステータス変更した場合は、その予定が終わるまで手動設定を優先するようにした</li>
    <li>モバイルでステータス保存に失敗した場合、エラーメッセージ（トースト）を表示するようにした</li>
    <li>モバイルのハンバーガーメニューから会社コンテキスト（表示する会社）を切り替えられるようにした（SuperAdmin向け）</li>
    <li>在籍ボードの更新時刻を、当日分は実際の時刻（例: 14:32）、前日以前は日付+時刻で表示するように変更した</li>
    <li>更新時刻の文字色を薄いグレーから濃いグレー（黒に近い色）に変更し、見やすくした</li>
    <li>「出社中」グループのヘッダー右端に「最終更新」ラベルを追加し、右側の数字が更新時刻であることを分かりやすくした</li>
  </ul>
</section>
HTML,
            ],
            [
                'version'      => 'management-template-1',
                'title'        => '管理シート：テンプレート管理機能を追加',
                'released_at'  => '2026-06-30',
                'summary'      => '管理シート用テンプレートを一覧・新規作成・編集・削除できる専用画面を追加しました。案件詳細の管理シートタブから開き、保存した列構成を新しい管理シートへ適用できます。',
                'design_files' => ['MANAGEMENT_TEMPLATE_PLAN1.md', 'MANAGEMENT_TEMPLATE_MANAGER1.md'],
                'claude_notes' => 'ProgressTemplateのsheet_type=managementを正として専用ManagementTemplateControllerとInertia画面を追加。旧WorkflowTemplateは互換性維持のため存置し、新機能には接続しない。進行管理用と管理シート用のテンプレート一覧・作成モーダルを種別分離し、本人作成または共有テンプレートのみ参照可能、編集削除は本人またはAdmin/SuperAdminに限定。',
                'body'         => <<<'HTML'
<section class="cl-feature">
  <h3>管理シートテンプレート管理</h3>
  <ul>
    <li>管理シートタブの「テンプレート管理」から専用一覧を開けるようになった</li>
    <li>テンプレート名・説明・共有設定・列とステージの構成を新規作成・編集できる</li>
    <li>不要になったテンプレートを一覧から削除できる</li>
    <li>作成したテンプレートを管理シートの新規作成時に選択して列構成を引き継げる</li>
    <li>進行管理表用テンプレートと管理シート用テンプレートを分けて表示するようになった</li>
  </ul>
</section>
HTML,
            ],
            // NSystem normalized database - 2026-06-19
            [
                'version'      => 'nsystem-schema-1',
                'title'        => '入試問題DBデモ：5年度対応のデータベース構造へ更新',
                'released_at'  => '2026-06-19',
                'summary'      => '学校、年度、試験、Nコード、問題・解答を分離したNSystem専用テーブルへ移行しました。既存の問題は2024年度として登録し、将来5年度分を同じ学校・試験系列で比較できる構造にしました。',
                'design_files' => ['NDBSCHEMA_PLAN1.md', 'NDBSCHEMA_MANAGER1.md'],
                'claude_notes' => 'NSystemテーブルをn_プレフィックスの11テーブルへ正規化。学校は内部IDとNコード先頭3文字、試験は年度とNコード全体、問題・解答はdocument_typeで管理。旧3テーブルはn_legacy_*へ退避。2024年の有効158試験、問題2244件、解答2376件を移行。仮コード464Fは464Nへ誤統合せず監査テーブルで未解決として保持。検索・一覧・大問表示を新構造へ切替。',
                'body'         => <<<'HTML'
<section class="cl-fix">
  <h3>変更内容</h3>
  <ul>
    <li>学校マスターと年度別学校名を分離し、名称変更を履歴として保存可能にした</li>
    <li>Nコード全体を年度別試験へ登録し、問題・解答を試験へ紐付けた</li>
    <li>2024年度の既存データを新テーブルへ移行した</li>
    <li>旧データは検証用のlegacyテーブルへ保持した</li>
    <li>取込元の不明コードを監査テーブルで追跡できるようにした</li>
  </ul>
</section>
HTML,
            ],
            // NSystem client demo search - 2026-06-19
            [
                'version'      => 'nsystem-search-1',
                'title'        => '入試問題DBデモ：検索精度とリアルタイム検索を改善',
                'released_at'  => '2026-06-19',
                'summary'      => 'クライアント提案用の入試問題DBデモで、入力語を含まない問題まで表示される全文検索の誤ヒットを解消しました。入力中のリアルタイム検索、一致箇所の前後表示、検索方法・科目・学校・カテゴリの絞り込み、対象大問への直接移動を追加しました。',
                'design_files' => ['NSEARCH_PLAN1.md', 'NSEARCH_MANAGER1.md'],
                'claude_notes' => 'NQuestionSearchServiceでMySQL ngram FULLTEXTを候補抽出に限定し、エスケープ済みリテラルLIKEで最終一致を保証。exact/all/anyの3モード、20件ページング、安全なbefore/match/afterスニペットを実装。検索画面をNSystem専用レイアウトのInertia/Vueページへ移行し、300ms debounce、IME composition、AbortController、URL状態保持を追加。社内AppLayoutは外部ゲスト向けデモには使用していない。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>日本語全文検索のngram分割により、「平安時代」の検索で「大正時代」など、入力した文字列そのものを含まない問題も表示されていました。また、結果には本文先頭部分しか表示されず、どこが一致したのか確認しにくい状態でした。</p>
</section>
<section class="cl-fix">
  <h3>改善内容</h3>
  <ul>
    <li>入力した文字列が実際に問題本文へ存在することを確認してから結果を表示</li>
    <li>入力中に結果を更新するリアルタイム検索と20件ごとのページ表示</li>
    <li>「そのまま含む」「すべての語」「いずれかの語」の3検索方法</li>
    <li>科目・学校・カテゴリによる絞り込み</li>
    <li>一致語を中心に前後の本文を表示し、該当箇所を強調</li>
    <li>検索結果から該当する学校・科目・大問へ直接移動</li>
  </ul>
</section>
HTML,
            ],
            [
                'version'      => 'nsystem-schema-2',
                'title'        => '入試問題DBデモ：年度別Mコードと学校一覧の年度切替に対応',
                'released_at'  => '2026-06-19',
                'summary'      => '5年度分のMコード掲載順を年度ごとに取り込み、学校一覧を選択年度のMコード順で表示するよう改善しました。問題文書がある年度だけ切り替えボタンを表示するため、現状のデモでは2024年度のみ選択できます。',
                'design_files' => ['NDBSCHEMA_PLAN1.md', 'NDBSCHEMA_MANAGER1.md'],
                'claude_notes' => 'n_publication_entriesへschool_idとexam_idを直接追加し、n_publication_entry_examsを廃止。NPublicationCatalogImportServiceで2022-2026 Excelをヘッダー名で読込み、2025/2026 M109は4551/4751の2行へ分割、2026 M106 4331→4335は監査注記を残して4331で登録。/n-demo一覧は選択年度のmikuni_code昇順、年度ボタンはpublicationEntries.exam.documentsが存在する年度のみ表示。',
                'body'         => <<<'HTML'
<section class="cl-fix">
  <h3>追加内容</h3>
  <ul>
    <li>2022年度から2026年度までのMコード掲載順を年度別に取り込めるようにした</li>
    <li>学校一覧は選択年度のMコード順で並ぶようになった</li>
    <li>年度ボタンは問題文書が登録済みの年度だけを表示するため、現状は2024年度のみ表示する</li>
    <li>2025年以降の開智中学校と開智所沢中等教育学校のM109共有を正式例外として保持する</li>
    <li>2026年の江戸川学園取手の表記変更は監査用に残しつつ、現状運用に合わせて4331で扱う</li>
  </ul>
</section>
HTML,
            ],
            // ─────────────────────────────────────────────────────────────
            // 0h. ANNOUNCEMENT-DRAFT-1 — 2026-06-01
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'announcement-draft-1',
                'title'        => 'お知らせ通知：Leader 追加・下書き機能の追加',
                'released_at'  => '2026-06-01',
                'summary'      => 'Leader ロールでもお知らせ通知を送信できるようになりました。また、Clerk・Leader 両方でお知らせを「下書き」として保存し、確認後に送信できる機能を追加しました。下書き中は受信者には表示されません。',
                'design_files' => [],
                'claude_notes' => 'announcements テーブルに status カラム追加（draft/sent、デフォルト sent）。Leader\\AnnouncementController を新規作成（LeaderMiddleware = 全 Leader + Admin + SuperAdmin が対象、部署リーダー限定なし）。ClerkMiddleware は部署リーダーのみ許可のまま。受信者側 AnnouncementController に status=sent フィルタを追加（下書きは受信者の受信箱に表示されない）。下書き保存時も recipients テーブルに保存済みなので詳細画面で「送信予定 N 人」を事前確認できる。下書き Edit フォームは宛先変更も可能。送信は詳細画面の POST .../send で status を sent に更新するだけ。routes/web.php の leader グループと clerk グループ両方に announcements.send ルートを追加。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>お知らせ通知機能は Clerk ロール以上にのみ存在し、Leader はお知らせを送信できませんでした。また、作成したお知らせは即時送信しかできず、事前確認してから送信するワークフローに対応していませんでした。</p>
</section>

<section class="cl-fix">
  <h3>追加内容</h3>
  <ul>
    <li>Leader ロール（部署リーダーでなくても全 Leader）でお知らせ通知を送信できるようになった（leader/announcements）</li>
    <li>作成フォームに「下書き保存」ボタンを追加。送信せずに内容を保存しておける</li>
    <li>下書きは受信者の受信箱には表示されない。送信後に初めて相手に届く</li>
    <li>一覧画面が「下書き」「送信済み」の2テーブルに分割された</li>
    <li>下書き詳細画面から「送信する」ボタンで即時配信できる</li>
    <li>下書き編集時は宛先（全員・社員のみ・個別選択）も変更可能</li>
    <li>送信済み一覧にタイトル/内容での検索・年月フィルタを追加</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 0g. CLIENT-EDIT-1 — 2026-05-31
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'client-edit-1',
                'title'        => '案件編集画面：クライアントを変更できるように改善',
                'released_at'  => '2026-05-31',
                'summary'      => '案件編集画面（案件編集）でクライアントを変更できるようになりました。仮登録クライアントを後から正式クライアントへ差し替えたい場合など、統合・削除をせずにクライアントを付け替えられます。',
                'design_files' => [],
                'claude_notes' => 'Edit.vue のクライアント欄を readonly から Create.vue と同等のオートコンプリートUIに変更。clientCodeInput（コード検索）・client_name（名前検索）の2入力 + サジェストドロップダウン。coordinator.clients.json エンドポイントを利用。form.client_id は既存の useForm に含まれており、update() の validation にも client_id:required が存在するため、バックエンド変更なし。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>案件作成後にクライアントを変更したい場合（例：仮登録クライアントで作成した案件を後から正式クライアントへ差し替えたい）、クライアントの統合・削除をしないと変更できませんでした。仮クライアントを他の案件でも使用している場合は身動きが取れない状態でした。</p>
</section>

<section class="cl-fix">
  <h3>改善内容</h3>
  <ul>
    <li>案件編集画面のクライアント欄が編集できるようになった（従来は表示のみで変更不可）</li>
    <li>クライアントコード（Client ID）または名前のいずれかを入力するとオートコンプリートで候補が表示される</li>
    <li>キーボード操作（↑↓Enter）でも候補を選択可能</li>
    <li>クライアントを変更して「更新」ボタンを押すと案件に反映される</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 0f. CLIENT-UPDATE-1 — 2026-05-26
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'client-update-1',
                'title'        => 'クライアント管理：全ロール統一・任意統合・重複検知精度向上・登録エラー修正',
                'released_at'  => '2026-05-26',
                'summary'      => 'Leader/Coordinator のクライアント管理画面を SuperAdmin と同じ構成に統一しました。重複チェックページに「任意のクライアントを選んで統合」機能を追加。「その他」と「そのほか」など漢字・ひらがなの表記ゆれも重複として検知するよう改善。Leader がクライアントIDを保存できなかった問題も解消しました。',
                'design_files' => [],
                'claude_notes' => 'ClientController: store()/update() の client_code unique バリデーションを非admin時は where(company_id) スコープに変更（Leader が他社と同コードを使えなかった問題の解消）。normalizeClientName() の mb_convert_kana フラグを h→H に修正（半角カタカナ→全角に統一してから c で全角カタカナ→ひらがなの順に変更）し、str_replace("他","ほか") を追加。Edit.vue/Create.vue からロール別分岐を削除して全ロール共通の全部署選択UIに統一。DuplicateCheck.vue に任意選択マージ機能（検索→選択→残す指定→統合）を追加。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <ul>
    <li>Leader・Coordinator がクライアント管理を開くと SuperAdmin と異なるレイアウト（制限付きビュー）が表示されており、一部のボタンや編集項目が使えない状態だった</li>
    <li>「その他」と「そのほか」のように漢字とひらがなで書き方が違うだけの同一クライアントが、重複として検知されず二重登録されることがあった。また半角カタカナと全角カタカナの違いも検知できていなかった</li>
    <li>Leader がクライアントIDを入力・保存しても反映されない（エラーも出ずに無視される）という報告があった</li>
    <li>重複チェックは自動検出のみで、手動で「このクライアントとこのクライアントを統合したい」という操作ができなかった</li>
  </ul>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li><strong>全ロール画面統一：</strong>Leader・Coordinator・Clerk のクライアント管理画面が SuperAdmin と同じ構成になった。テーブル表示・編集ボタン・削除ボタン・部署の複数選択がすべて使えるようになった</li>
    <li><strong>重複検知の精度向上：</strong>「その他」と「そのほか」（漢字・ひらがなの違い）を同一として検知するようになった。半角カタカナと全角カタカナの違いも吸収して比較するよう改善した</li>
    <li><strong>クライアントID保存エラーの解消：</strong>Leader が他社で使われているクライアントIDと同じ値を入力するとエラーになっていた（他社の情報は見えないため原因不明に見えていた）。自社内のみでの重複チェックに変更し、正常に保存できるようになった</li>
    <li><strong>任意統合機能の追加：</strong>重複チェックページに「任意のクライアントを選んで統合」セクションを追加。名前やIDで検索してクライアントを複数選び、残すクライアントをラジオボタンで指定して統合できる。自動検出リストに出てこない組み合わせでも使用可能</li>
  </ul>
</section>

<section class="cl-note">
  <h3>補足</h3>
  <ul>
    <li>任意統合は「重複チェック」ページ内の折りたたみセクションに配置されています（初期表示は開いた状態）</li>
    <li>統合すると、削除されるクライアントに紐づいていた案件がすべて残すクライアントへ移動します。この操作は取り消せないため、統合前に確認ダイアログが表示されます</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 0e. DEDUP-1 — 2026-05-24
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'dedup-1',
                'title'        => 'クライアント管理：重複チェック機能追加・部署フィルターボタン追加',
                'released_at'  => '2026-05-24',
                'summary'      => 'クライアント一覧に「重複チェック」ボタンを追加。伝票番号重複・コード欠損同名・名前類似（カタカナ/全角半角差異）の3ルールで疑わしいペアを一覧表示し、残すクライアントを選んで一括統合できます。admin/leader/coordinator の全ロール対応。クライアント管理とユーザー管理の部署フィルターボタン並び順も「情報出版→製版→オンデマンド」に固定しました。',
                'design_files' => ['DEDUP_PLAN1.md', 'DEDUP_MANAGER1.md'],
                'claude_notes' => 'ClientController に duplicateCheckPage()・batchMerge() を追加。normalizeClientName() を拡張（mb_convert_kana h/c で半角・全角カタカナをひらがなに統一）。routes/web.php の admin/leader/coordinator 3グループに clients/duplicate-check と clients/batch-merge を追加（Resource より前に定義）。Clients/DuplicateCheck.vue 新規作成（使い方ガイド折りたたみ付き）。Clients/Index.vue・Admin/Users/Index.vue に部署フィルターボタンと固定ソート順を追加。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <ul>
    <li>クライアントが名前表記のゆれ（カタカナ/ひらがな・全角/半角・空白有無など）や伝票番号の入力漏れにより、実質同一のクライアントが重複登録されることがあった</li>
    <li>重複に気づかず案件が別々のクライアントに紐づくと、集計・検索・統合の際に支障が出ていた</li>
    <li>クライアント管理の部署フィルターボタンの並び順が五十音順になっており、実際の業務部署の並びと異なっていた</li>
  </ul>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li>クライアント一覧に「重複チェック」ボタンを追加。ページに遷移してDBを全件スキャンし、疑わしいペアを一覧表示する</li>
    <li>重複の検出ルールは3種類：①伝票番号の完全一致（コード重複）、②片方にコードなし・名前完全一致（コード欠損）、③名前を正規化後に一致（名前類似 — 空白・カタカナ/ひらがな・全角半角差異を吸収）</li>
    <li>各ペアにはクライアント名・伝票番号・案件数・登録日を並べて表示。案件数が多い方をデフォルトで「残す」に自動選択</li>
    <li>チェックボックスで複数ペアを選択し、「選択した〇件を統合」ボタンで一括統合できる。統合は既存の安全なDB処理（トランザクション）を使用</li>
    <li>使い方ガイドを折りたたみ式で掲載（初期表示は開いた状態）</li>
    <li>クライアント管理・ユーザー管理の部署フィルターボタンの並び順を「情報出版→製版→オンデマンド」の固定順に統一</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 0d. MISC-FIX-1 — 2026-05-24
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'misc-fix-1',
                'title'        => '役職称号の全ロール対応・クライアント管理403修正・在席ボード名前幅拡張',
                'released_at'  => '2026-05-24',
                'summary'      => '役職称号（係長・主任）を admin/leader に限らず全ロールのユーザーに設定できるようにしました。クライアント管理で新規作成直後に「編集」が403になる問題を leader/coordinator/clerk を含む全ロールで修正。在席ボードのメンバー名が5文字で切れていたのを8文字程度まで表示できるよう拡張しました。',
                'design_files' => [],
                'claude_notes' => 'PositionTitlesSeeder に主任(sort_order=9)を追加。Admin/UserController・Leader/UserManagementController の positionTitles 渡しを全件統合し role フィルター廃止。CSV インポートの leader 限定制限も削除。ClientPolicy::view()/update() を superadmin/admin は無条件許可、leader/coordinator/clerk は company_id 一致または null の場合は通すよう変更（delete は update に委譲なので自動対応）。IrukaBoard.vue の名前 span を w-16 → w-28 に変更。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <ul>
    <li>役職称号（係長など）が admin/leader ロールのユーザーにしか設定できず、coordinator や user など他のロールには表示されなかった</li>
    <li>クライアント管理でユーザーが新規作成したクライアントを即座に「編集」しようとすると403エラーが発生していた（company_id が null の場合にポリシーが弾いていた）。leader・coordinator でも同様の問題が起きうる状態だった</li>
    <li>在席ボードのメンバー名が約5文字で省略されており、名前が長いメンバーのフルネームが確認できなかった</li>
  </ul>
</section>

<section class="cl-fix">
  <h3>修正内容</h3>
  <ul>
    <li>役職称号に「主任」を新規追加（係長の次）。全ロールのユーザー作成・編集フォームで選択できるようになった</li>
    <li>クライアント管理の認可ポリシーを修正。superadmin/admin は全クライアントを管理可能、leader/coordinator/clerk は同一会社のクライアントを管理可能（company_id 未設定の場合も許可）</li>
    <li>在席ボードのメンバー名表示幅を拡張し、7〜8文字程度まで省略なく表示されるようになった</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 0c. PJOB1 — 2026-05-23
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'pjob-manager-1',
                'title'        => '案件管理強化：CSV一括登録・担当営業・セッション維持・重複チェック',
                'released_at'  => '2026-05-23',
                'summary'      => 'coordinator の案件新規作成フォームに担当営業・製版入稿日・下版日を追加。案件一覧から CSV 一括登録（No列自動判定・Shift-JIS対応・クライアント/営業担当インラインマッチング）が可能になりました。製版ボードにもサンプル CSV ダウンロードボタンを追加。長時間放置後の操作不能問題を解消し、受注番号の重複登録防止チェックも追加しています。',
                'design_files' => ['PJOB_PLAN1.md', 'PJOB_MANAGER1.md'],
                'claude_notes' => '詳細は z_instructions/archived/PJOB_PLAN1.md および PJOB_MANAGER1.md を参照。DB: project_jobs に sales_rep/sales_rep_id/plate_submission_date/plate_down_date 追加（migration 済み）。coordinator CSV ルート・コントローラーを新設（prepress とは完全分離）。No列の有無を header[0] で自動判定（detectNoColumnOffset）。Keep-Alive Ping は AppLayout.vue に 10 分間隔。coordinator store/update に jobcode 重複チェック追加（prepress は実装済み）。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>coordinator の案件登録フォームに担当営業や製版日程を入力する手段がなく、大量の案件を CSV から一括登録する機能もありませんでした。長時間ページを開いたままにするとセッションが切れて操作できなくなる問題もありました。</p>
</section>

<section class="cl-fix">
  <h3>追加・改善内容</h3>
  <ul>
    <li>coordinator 案件新規作成フォームに「担当営業」（dropdown + フリーテキスト）・「製版入稿日」・「下版日」を追加した</li>
    <li>coordinator 案件一覧から CSV 一括登録ができるようになった（会社既存の受注 CSV をそのままアップロード可能）</li>
    <li>CSV のクライアント名・営業担当名はファジーマッチングで自動解決。未マッチ時はその場で新規登録ができる</li>
    <li>CSV の「No（行番号）」列はあってもなくても自動判定して正しく読み込む</li>
    <li>Shift-JIS（Excel 保存形式）の CSV にも対応している</li>
    <li>製版ボード（prepress）の CSV 確認画面にサンプル CSV ダウンロードボタンを追加した</li>
    <li>長時間ページを開いたままにしてもセッションが切れなくなった（10 分ごとに自動的に接続を維持）</li>
    <li>coordinator で案件を登録・編集する際、受注番号（伝票番号）が既存案件と重複していると警告が表示されるようになった</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 0a. CLIENTCODE — 2026-05-21
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'client-code',
                'title'        => 'クライアントIDの追加と重複チェックの改善',
                'released_at'  => '2026-05-21',
                'summary'      => 'クライアントに業務用の識別コード（クライアントID）を設定できるようになりました。また、クライアント名の重複チェックのロジックも見直し、より正確に判定されるようになっています。',
                'design_files' => ['CLIENTCODE_PLAN1.md', 'CLIENTCODE_MANAGER1.md'],
                'claude_notes' => '詳細は z_instructions/archived/CLIENTCODE_PLAN1.md および CLIENTCODE_MANAGER1.md を参照。client_code カラム追加（マイグレーション済み）、重複チェックは client_code の有無で3パターン分岐。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>クライアントの管理をシステム内部のID番号だけで行っていたため、業務で使い慣れた独自のコードで検索・管理する手段がありませんでした。また、同じ名前のクライアントが重複登録されてしまうことがありました。</p>
</section>

<section class="cl-fix">
  <h3>追加・改善内容</h3>
  <ul>
    <li>クライアントに業務用の「クライアントID」を設定できるようになった（登録・編集・一覧すべての画面で対応）</li>
    <li>クライアントID が設定されている場合は、IDが一致しないと同名でも別クライアントとして扱えるようになった</li>
    <li>クライアント名の重複チェックがより正確になった（ID有無・名前の組み合わせで3パターン判定）</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 0b. IRUKA — 2026-05-15
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'iruka-board',
                'title'        => 'イルカボード（在籍管理）の新設',
                'released_at'  => '2026-05-15',
                'summary'      => 'メンバー全員の在籍状況をリアルタイムで確認・変更できる「イルカボード」を追加しました。18種類のステータスから選べ、ヘッダーに常時表示されます。退社ステータス設定時の自動日報作成にも対応しています。',
                'design_files' => ['IRUKA_PLAN1.md', 'IRUKA_MANAGER1.md'],
                'claude_notes' => '詳細は z_instructions/archived/IRUKA_PLAN1.md および IRUKA_MANAGER1.md を参照。Phase 1〜9 完了。user_presence_statuses テーブル。30秒ポーリング。ヘッダーの IrukaStatusBadge・IrukaStatusModal が中核コンポーネント。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>社内にいるメンバーが今どういう状況なのか（在席・外出・テレワーク等）をすぐに確認する手段がありませんでした。電話やチャットで確認する必要があり、手間がかかっていました。</p>
</section>

<section class="cl-fix">
  <h3>追加内容</h3>
  <ul>
    <li>全ページのヘッダーに自分のステータスが常時表示されるようになった。クリックするとステータスを変更できる</li>
    <li>「在席」「外出」「テレワーク」「有給休暇」「離席」など18種類のステータスから選択可能</li>
    <li>コメント（一言メモ）を添えてステータスを設定できる</li>
    <li>ダッシュボードに「イルカボード」として全メンバーの状況が一覧表示されるようになった（30秒ごとに自動更新）</li>
    <li>部署ごとのフィルター表示に対応（情報出版・製版・オンデマンド・全部署）</li>
    <li>他のメンバーの名前をクリックして、そのメンバーのステータスを変更することもできる</li>
    <li>「退社」ステータスを設定した際に、その日の日報がまだ作成されていない場合は自動で日報が作成されるようになった</li>
    <li>Admin・Leaderがイルカボードのメンバー表示順・表示/非表示を管理できるようになった</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 0c. SCRIPTS — 2026-05-16
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'scripts',
                'title'        => 'スクリプトツールの追加',
                'released_at'  => '2026-05-16',
                'summary'      => '業務効率化のための「スクリプトツール」セクションをヘッダーに追加しました。Admin・Leader（権限設定次第）がアクセスでき、各種業務ツールをここから呼び出せるようになっています。',
                'design_files' => ['SCRIPT_PLAN1.md', 'SCRIPT_MANAGER1.md'],
                'claude_notes' => '詳細は z_instructions/archived/SCRIPT_PLAN1.md および SCRIPT_MANAGER1.md を参照。scripts テーブル（slug, title, description, component_key）。コンポーネントは resources/js/Components/Scripts/ に配置。SCRIPTS_SECTION_GUIDELINES.md（z_instructions/ に現存）も参照。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>業務で繰り返し行う作業（データ整理・一括処理など）をシステム上で実行できる仕組みがなく、手作業や外部ツールに頼る必要がありました。</p>
</section>

<section class="cl-fix">
  <h3>追加内容</h3>
  <ul>
    <li>ヘッダーに「スクリプト」ボタンを追加。クリックするとスクリプトツールの一覧ページへアクセスできる</li>
    <li>各スクリプトのページでは、ボタン操作や簡単なフォーム入力で業務処理を実行できる</li>
    <li>アクセス権限はAdmin・SuperAdmin、および権限設定されたLeaderのみ（一般ユーザーには非表示）</li>
    <li>今後、新しい業務ツールはここに追加されていく予定</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 0d. WORKFLOW + PROCESS — 2026-05-14
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'workflow-sheet',
                'title'        => '工程シートの新設',
                'released_at'  => '2026-05-14',
                'summary'      => '案件に「工程シート」を追加できるようになりました。進行管理表と同様に担当者を登録・完了管理でき、カレンダーのイベントから作業時間を自動集計します。また、案件詳細に「項目リスト」タブが追加され、作業項目の登録・確認が一元管理できます。',
                'design_files' => ['PROCESS_PLAN1.md', 'PROCESS_MANAGER1.md', 'WORKFLOW_V2_PLAN1.md', 'WORKFLOW_V2_PLAN2.md', 'WORKFLOW_V2_PLAN3.md', 'WORKFLOW_V2_MANAGER1.md', 'WORKFLOW_V2_MANAGER2.md', 'WORKFLOW_V2_MANAGER3.md'],
                'claude_notes' => '詳細は z_instructions/archived/PROCESS_PLAN1.md / WORKFLOW_V2_PLAN*.md / WORKFLOW_V2_MANAGER*.md を参照。workflow_sheets / workflow_rows / workflow_cells / workflow_templates テーブル。工程シートは Coordinator/WorkflowSheets/Show.vue。進行表との違い: 工程シートは時間軸ベースで担当者1名/セル。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>案件の作業工程を管理する専用の仕組みがなく、進行管理表と予定管理が分離していました。担当者を設定しても実際の作業時間がシステムに反映されず、工数集計を手作業で行う必要がありました。</p>
</section>

<section class="cl-fix">
  <h3>追加内容</h3>
  <ul>
    <li>案件に「工程シート」を追加できるようになった。テンプレートから工程の雛形を作成し、担当者を登録・完了管理できる</li>
    <li>工程セルに担当者を登録すると、カレンダーのイベントから作業時間が自動で集計されて表示される</li>
    <li>担当者の完了操作・未完了への差し戻しが工程シート上から直接できる</li>
    <li>案件詳細に「項目リスト」タブを追加。作業項目（組版・校正・修正など）を一覧登録・管理できるようになった</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 0e. GHOST — 2026-05-13
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'ghost-user',
                'title'        => 'テストユーザー（ゴーストユーザー）機能の追加',
                'released_at'  => '2026-05-13',
                'summary'      => 'Coordinatorが仮のテストユーザーを作成し、ジョブ割り当ての流れを実際のユーザー操作で確認できるようになりました。テストユーザーは14日間で自動削除され、正規のユーザーリストには影響しません。',
                'design_files' => ['GHOST_PLAN1.md', 'GHOST_MANAGER1.md'],
                'claude_notes' => '詳細は z_instructions/archived/GHOST_PLAN1.md および GHOST_MANAGER1.md を参照。users テーブルに is_ghost / ghost_owner_id / ghost_expires_at カラム追加済み。GhostUserController でセッション切り替え。GlobalScope でゴーストユーザーをリストから除外。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>Coordinatorがジョブの割り当てから完了までの流れを実際の画面で確認しようとすると、テスト用に別のユーザーアカウントが必要でした。テスト用アカウントを作ると正規のユーザーリストが汚れてしまうという問題がありました。</p>
</section>

<section class="cl-fix">
  <h3>追加内容</h3>
  <ul>
    <li>Coordinatorのダッシュボードから「テストユーザー」を1つ作成できるようになった</li>
    <li>テストユーザーのボタンをクリックするだけで、そのユーザーとしてサイトを操作できる（別ログイン不要）</li>
    <li>テストユーザーはジョブ受信・マイジョブBOX・進行表からの自己割当・完了操作が確認できる</li>
    <li>テストユーザーは自分の作成したCoordinatorにのみ見える（ユーザー一覧・進行表・メンバー選択には表示されない）</li>
    <li>作成から14日後に自動で削除される</li>
    <li>ジョブ割り当て画面では「[テスト]」ラベル付きで末尾に表示される</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 1. REPAIR5 — 2026-05-23
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'repair-5',
                'title'        => '案件・ジョブ・日報の不具合修正と機能改善',
                'released_at'  => '2026-05-23',
                'summary'      => 'ユーザーから寄せられた不具合・使い勝手の問題16項目を修正しました。日報のタイムライン操作、案件一覧の表示カスタマイズ、ジョブ重複防止など多方面を改善しています。',
                'design_files' => ['REPAIR_PLAN5.md', 'REPAIR_MANAGER5.md'],
                'claude_notes' => '詳細は z_instructions/archived/REPAIR_PLAN5.md および REPAIR_MANAGER5.md を参照。R5-01〜R5-16 の16タスク。R5-16は後から追加（日報タイムライン編集・カレンダー連動）。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>日常業務で気になっていた「小さな不具合」や「もう少し便利にしてほしい」という要望を16項目にまとめ、一括して修正・改善しました。</p>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li>通知ページの時間表記が「15時：40」のように表示されていたのを「15:40」に修正</li>
    <li>案件一覧のお気に入り星をクリックしても反応しない問題を修正（進行表では動いていたが案件一覧では未実装だった）</li>
    <li>案件スケジュールパネルでCSVボタンが2つ重複して表示されていた問題を解消</li>
    <li>進行管理表ジョブのタイトル表記が全角ハイフン「ー」とアンダーバー「_」で不統一だったのをアンダーバーに統一</li>
    <li>ジョブ編集時に開始時刻が常に現在時刻に上書きされてしまう問題を修正（編集時は元の時刻を保持するように）</li>
    <li>案件一覧に伝票番号のカラムを追加。表示する列（登録日・伝票番号・クライアント名・ステータス）を各自カスタマイズできるようになった</li>
    <li>スケジュールCSVや案件CSVのインポートがShift-JIS + CRLF（Excelで保存した形式）でも正しく読み込めるようになった</li>
    <li>進行管理表からジョブを割り当てても担当者名が進行表に表示されない問題を修正</li>
    <li>進行管理表から「完了」「未完了に戻す」を操作しても反映されないことがあった問題を修正。操作後にモーダルが自動で閉じるようになった</li>
    <li>伝票画像を登録・削除してもモーダルを閉じるまで画面に反映されない問題を修正（登録・削除直後に画面が更新されるようになった）</li>
    <li>マイジョブBOXで「予定をセット」を何度も押すと同じジョブが際限なく増える問題を修正。既に登録済みのジョブには「登録済」バッジを表示するようになった</li>
    <li>日報の作成・編集ページに、当日のスケジュール（タイムライン）を表示するようになった</li>
    <li>日報ページのタイムライン上で、カレンダーの予定をドラッグやリサイズして直接時間変更できるようになった</li>
    <li>日報の入力欄（Quillエディター）で箇条書きや番号付きリストが正しく表示・動作しない問題を修正</li>
    <li>日報入力欄の各ボタンにカーソルを合わせると機能名（「太字」「箇条書き」等）がポップアップ表示されるようになった</li>
    <li>案件一括作成ページに「テンプレートから作成」タブを追加。テンプレートを選ぶと項目が自動入力された状態でフォームが表示される</li>
    <li>案件一括作成ページで伝票番号を入力できるようになった（空欄でもOK）</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 2. REPAIR4 — 2026-05-12
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'repair-4',
                'title'        => 'スマートフォン・タブレット対応',
                'released_at'  => '2026-05-12',
                'summary'      => 'スマートフォンやタブレットでサイトを使用した際のレイアウト崩れ・操作しにくさを全面的に改善しました。ナビゲーション、フォーム、テーブルなどがモバイル画面に対応しています。',
                'design_files' => ['REPAIR_PLAN4.md', 'REPAIR_MANAGER4.md'],
                'claude_notes' => '詳細は z_instructions/archived/REPAIR_PLAN4.md および REPAIR_MANAGER4.md を参照。R-01〜R-11 の11タスク（レスポンシブ対応）。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>スマートフォンやタブレットでアクセスした際に、ナビゲーションが使いにくかったり、フォームが画面からはみ出したり、テーブルが横に切れるなど、モバイルでの操作に支障がありました。</p>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li>ナビゲーションタブがスマートフォン画面でも使いやすいドロップダウン形式になった</li>
    <li>案件作成フォームがスマートフォン画面に対応（縦1列レイアウトに）</li>
    <li>ジョブ割り振りフォームがスマートフォン画面に対応</li>
    <li>カレンダーイベント登録フォームがスマートフォン画面に対応</li>
    <li>スマートフォン表示時のカレンダーデフォルトビューを見やすい形式に変更</li>
    <li>ハンバーガーメニューとサブタブの整合性を修正</li>
    <li>ヘッダーのレイアウトをスマートフォンに対応（要素が折り返しても崩れないように）</li>
    <li>「戻る」ボタンのテキストが折り返さないように修正</li>
    <li>テーブルが横にはみ出す場合に横スクロールで表示できるように対応</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 3. REPAIR3 — 2026-05-09
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'repair-3',
                'title'        => '工数・時間計算の精度改善',
                'released_at'  => '2026-05-09',
                'summary'      => 'カレンダーの予定が重複する場合の工数計算や、昼休憩時間の扱いに不正確な部分がありました。計算ロジックを全体で統一し、工数レポートの精度を向上させました。',
                'design_files' => ['REPAIR_PLAN3.md', 'REPAIR_MANAGER3.md'],
                'claude_notes' => '詳細は z_instructions/archived/REPAIR_PLAN3.md および REPAIR_MANAGER3.md を参照。Q-01〜Q-07 の7タスク。CalculatesEventTime トレイト（app/Http/Controllers/Concerns/CalculatesEventTime.php）が中核。CLAUDE.md の UTC/JST 混在ルールも参照のこと。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>カレンダーの予定が重なっている場合の工数計算（重複除外）が正確でなく、予定を編集・削除した後も古い計算結果が残ることがありました。また、個人設定の昼休憩時間が一部のページで正しく適用されていないケースもありました。</p>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li>カレンダーの予定を編集・削除した際に、重複計算が正しく再計算されるようになった（以前は古い値が残ることがあった）</li>
    <li>個人設定で設定した昼休憩時間が全ページで正しく適用されるようになった（共通ロジックに統一）</li>
    <li>校正ジョブのイベント（UTC形式で保存）と通常イベント（JST形式で保存）が混在する場合に9時間ずれる問題を修正</li>
    <li>「重複除算」の用語表記を「中断」に統一し、ページ間で表示が一致するようになった</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 4. REPAIR2 — 2026-04-26
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'repair-2',
                'title'        => '案件・ジョブ・UIの改善（第2版）',
                'released_at'  => '2026-04-26',
                'summary'      => '第1版リリース後に見つかった不具合と使い勝手の問題を引き続き改善しました。カレンダー削除エラー、ジョブのステータス表示不統一、案件タブ構成の見直しなど12項目を対応しました。',
                'design_files' => ['REPAIR_PLAN2.md', 'REPAIR_MANAGER2.md'],
                'claude_notes' => '詳細は z_instructions/archived/REPAIR_PLAN2.md および REPAIR_MANAGER2.md を参照。N-01〜N-12 の12タスク。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>第1版のリリース後も引き続き「カレンダーが壊れる」「戻るボタンが効かない」「ジョブの表示がページによって違う」など、日常操作で気になる問題が報告されていました。</p>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li>カレンダーの予定削除時に500エラーが出ることがあった問題を修正</li>
    <li>ジョブを削除した後のリダイレクト先が「ジョブ一覧」になっていたのを、前の画面に戻るように変更</li>
    <li>ジョブのステータス表示がページによって異なっていた問題を統一</li>
    <li>「戻る」ボタンが機能しないページを追加修正</li>
    <li>ジョブ履歴の初期表示を展開済みに変更（開くと一覧がすぐ見える）</li>
    <li>ジョブ割り振り時の開始時刻初期値を現在時刻（5分刻み）に設定するようになった</li>
    <li>案件詳細のスケジュールをタブとして独立させた（以前は概要タブに混在していた）</li>
    <li>案件カレンダーのCSV出力ファイル名に案件名が含まれるようになった</li>
    <li>進行管理表の行をクリックで展開・折りたたみできるようになった</li>
    <li>ジョブタイトルの命名規則をアンダーバー区切りに統一</li>
    <li>案件詳細への戻りリンクで進行管理表タブが開いた状態に戻れるようになった</li>
    <li>案件ジョブ一覧の表示モード（リスト/グループ）がページをまたいでも記憶されるようになった</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 5. PROOF-JOBS — 2026-04-30
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'proof-jobs',
                'title'        => '校正管理者のジョブ管理を一本化',
                'released_at'  => '2026-04-30',
                'summary'      => '校正管理者（Proof Co.）が使う「割り振り管理」と「校正履歴」の画面を統合し、1つの「ジョブ管理」ページで進行中・完了を切り替えて管理できるようになりました。',
                'design_files' => ['PROOF_JOBS_PLAN.md', 'PROOF_JOBS_MANAGER.md'],
                'claude_notes' => '詳細は z_instructions/archived/PROOF_JOBS_PLAN.md および PROOF_JOBS_MANAGER.md を参照。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>校正管理者は「割り振り管理」と「案件校正履歴」を別々のページで確認する必要があり、進行中のジョブと完了済みのジョブを横断して把握するのが不便でした。また、一度「完了」にしたジョブを未完了に戻す手段がありませんでした。</p>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li>「割り振り管理」と「案件校正履歴」を「ジョブ管理」ページに統合。1画面で全ジョブを確認できるようになった</li>
    <li>進行中・完了済みをタブで切り替えて確認できるようになった</li>
    <li>完了済みのジョブを「未完了に戻す」操作ができるようになった</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 6. UI-STATE — 2026-04-29
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'ui-state',
                'title'        => '画面の状態が自動的に記憶されるようになった',
                'released_at'  => '2026-04-29',
                'summary'      => 'ページをリロードしたり他のページから戻ってきた際に、フィルターやタブの選択状態がリセットされてしまう問題を解消しました。最後に操作した状態が自動的に復元されます。',
                'design_files' => ['UI_STATE_PERSIST_PLAN.md', 'UI_STATE_PERSIST_MANAGER.md'],
                'claude_notes' => '詳細は z_instructions/archived/UI_STATE_PERSIST_PLAN.md および UI_STATE_PERSIST_MANAGER.md を参照。useUIState コンポーザブル（resources/js/Composables/useUIState.js）が中核。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>「完了を表示しない」フィルターをONにして作業していても、ページをリロードするとOFFに戻ってしまうなど、操作した設定がリセットされてしまうことがありました。</p>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li>ジョブ一覧の「完了を表示しない」フィルターがページをまたいでも保持されるようになった</li>
    <li>ジョブ一覧の表示モード（リスト形式 / グループ形式）が保持されるようになった</li>
    <li>案件一覧のソート順が保持されるようになった</li>
    <li>各種タブの選択状態（どのタブを開いていたか）が保持されるようになった</li>
    <li>案件一覧の表示列設定（伝票番号・クライアント名等の表示/非表示）が保持されるようになった</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 7. PREPRESS — 2026-04-28
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'prepress',
                'title'        => '製版部署専用エリアの新設',
                'released_at'  => '2026-04-28',
                'summary'      => '製版担当者が伝票をカード形式で視覚的に管理できる専用エリアを新設しました。ドラッグ＆ドロップで状態を移動したり、伝票画像からOCRで情報を自動抽出する機能も含まれています。',
                'design_files' => ['PREPRESS_PLAN.md', 'PREPRESS_MANAGER.md', 'PREPRESS_PLAN2.md', 'PREPRESS_MANAGER2.md', 'PREPRESS_BOARD_V2_DESIGN.md'],
                'claude_notes' => '詳細は z_instructions/archived/PREPRESS_PLAN.md / PREPRESS_MANAGER.md / PREPRESS_PLAN2.md / PREPRESS_MANAGER2.md を参照。OCR機能は CONSOLIDATED_10_ocr_local_tesseract.md（z_instructions/ に現存）も参照。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>製版担当者が伝票情報を管理する専用の場所がなく、他部署のシステムと混在していました。また、伝票情報の入力は手作業で、画像から情報を読み取る仕組みがありませんでした。</p>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li>製版担当者専用の「Prepressボード」エリアを新設</li>
    <li>伝票ボード：伝票を「準備中」「作業中」「完了」のカード形式で管理できる。ドラッグ＆ドロップで状態を移動可能</li>
    <li>伝票一覧：登録した伝票を一覧表示・絞り込みできる</li>
    <li>OCR伝票読み取り：伝票画像をアップロードすると、受注番号・クライアント名・品目名を自動抽出。クライアントのデータベース照合と新規登録も同画面で完結できる</li>
    <li>営業担当の設定・管理機能を追加</li>
    <li>CSVによる伝票一括登録（クライアントIDや部署情報を含む形式に対応）</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 8. PROGRESS-V2 — 2026-04-27
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'progress-v2',
                'title'        => '進行管理表の全面刷新',
                'released_at'  => '2026-04-27',
                'summary'      => '進行管理表を全面的に作り直しました。担当者管理・締め切り管理・ジョブ完了を1つのセルで完結させられるようになり、色分けアラート・完了率表示・共有URL・印刷など多数の機能が追加されています。',
                'design_files' => ['PROGRESS_SHEET_V2_DESIGN.md', 'PROGRESS_SHEET_V2_MANAGER1.md', 'PROGRESS_SHEET_V2_MANAGER2.md', 'PROGRESS_SHEET_V2_MANAGER3.md'],
                'claude_notes' => '詳細は z_instructions/archived/PROGRESS_SHEET_V2_DESIGN.md および PROGRESS_SHEET_V2_MANAGER1〜3.md を参照。進行表関連は CONSOLIDATED_05_calendar_and_jobbox.md も参照。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>進行管理表で担当者の設定、締め切り管理、ジョブの登録がそれぞれ別々の操作で行う必要があり、操作が煩雑でした。期日が過ぎていても一目でわかる仕組みがなく、全体の進捗状況を把握するのも困難でした。</p>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li>担当者の設定・締め切り・ジョブ完了の管理を1つのセルで完結できるようになった</li>
    <li>締め切りアラートの色分け：期日超過は赤、3日以内は黄、完了は緑で一目でわかる</li>
    <li>各行・シート全体の完了率をパーセントで表示するバッジを追加</li>
    <li>セルにメモを残せるようになった（ポップアップで確認可能）</li>
    <li>閲覧専用の共有URLを発行して社外の人にも安全に共有できるようになった</li>
    <li>複数案件の進捗を横断的に一覧確認できる「進行レポート」機能を追加（Coordinator向け）</li>
    <li>スケジュールの項目を選ぶだけで進行表の行と日付が自動生成されるようになった</li>
    <li>進行表を印刷できるようになった</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 9. REPAIR1 — 2026-04-24
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'repair-1',
                'title'        => 'バグ修正・機能改善（第1版）',
                'released_at'  => '2026-04-24',
                'summary'      => '日常業務で発見されたバグ7件を修正し、ジョブステータスの4段階整理・台割行の強化・週間プランナー追加など10項目の機能改善、さらにスケジュール連動・案件複製などの大規模機能2件を実装しました。',
                'design_files' => ['REPAIR_PLAN.md', 'REPAIR_MANAGER.md', 'LAYOUT_REPAIR_PLAN.md', 'LAYOUT_REPAIR_MANAGER.md', 'G01_ITEM_DESIGN.md'],
                'claude_notes' => '詳細は z_instructions/archived/REPAIR_PLAN.md および REPAIR_MANAGER.md を参照。B-01〜B-07（バグ修正）、L-01〜L-02（レイアウト）、F-01〜F-10（機能改善）、G-01〜G-02（大規模機能）の計21タスク。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>カレンダーの予定が削除できない、日付がずれるなど日常的な操作で「おかしいな」と感じていた不具合が複数ありました。同時に、進行管理表・スケジュール・案件複製など改善要望が多かった機能の大幅強化も実施しました。</p>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li>カレンダーの予定が削除できない問題を修正</li>
    <li>カレンダーの日付が1日ずれる問題を修正</li>
    <li>スケジュール編集後に同じ予定が2つ表示される問題を修正</li>
    <li>進行管理表の「ジョブ詳細を開く」ボタンが反応しない問題を修正</li>
    <li>「未完了にする」操作がジョブ一覧に反映されない問題を修正</li>
    <li>ジョブ一覧「完了を表示しない」フィルターが機能しない問題を修正</li>
    <li>案件内割り当て一覧→ジョブ一覧が空になる問題を修正</li>
    <li>ジョブのステータスを「未読・確認済み・セット済み・完了」の4段階に整理し、全ページで統一</li>
    <li>台割行への追加・編集・削除・複製・並び替えを強化。Enterキーで保存できるようになった</li>
    <li>スケジュールをカレンダー以外からも直接入力できるインライン編集モードを追加（追加・編集・削除・並び替え・CSV出力取込）</li>
    <li>案件詳細への「戻る」リンクで、進行管理表タブが開いた状態に戻れるようになった</li>
    <li>カレンダーに週間プランナービューを追加。定例会議の自動登録・多段スレッド掲示板・ロールカラー表示に対応</li>
    <li>スケジュールと進行管理表が連動するようになった（項目を紐づけると双方に反映）</li>
    <li>案件複製機能を強化：スケジュール・進行管理表の構造（担当者情報は除く）も一緒にコピーされるようになった</li>
    <li>全ページで「戻る」ボタン・操作ボタンの配置・色を統一</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            // 10. BULK-CREATE — 2026-04-20
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'bulk-create',
                'title'        => '案件の一括登録・複製・テンプレート機能の追加',
                'released_at'  => '2026-04-20',
                'summary'      => 'Coordinatorが短いスパンで多数の案件を効率よく登録できるよう、ワンクリック複製・CSV一括登録・テンプレート管理の3機能を追加しました。',
                'design_files' => ['BULK_PROJECT_CREATE_DESIGN.md', 'BULK_PROJECT_CREATE_PROPOSAL.md'],
                'claude_notes' => '詳細は z_instructions/archived/BULK_PROJECT_CREATE_DESIGN.md および BULK_PROJECT_CREATE_PROPOSAL.md を参照。実装ファイルは BulkProjectJobController.php・ProjectJobTemplateController.php・BulkCreate.vue。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>Coordinatorが短いスパンで数十件の似た案件を1件ずつ手入力しなければならず、非常に手間がかかっていました。チームメンバーは変わらずクライアントだけ変わるケースが多いため、効率化が求められていました。</p>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li>ワンクリック複製：既存の案件を1クリックで複製できるようになった。スケジュール・進行管理表の構造（担当者情報は除く）も一緒にコピーされる</li>
    <li>CSV一括登録：CSVファイルから複数の案件を一括登録できるようになった。登録前にプレビューでエラーを確認してから確定できる</li>
    <li>テンプレート管理：よく使う設定（リーダー・チームメンバー・サイズ・クライアント等）をテンプレートとして保存し、次回から自動入力で使い回せるようになった</li>
    <li>クライアントプリセット：案件作成画面でクライアントを選ぶと、そのクライアントの直近案件の設定が自動で引き継がれるようになった</li>
    <li>Shift-JIS + CRLF形式のCSVファイル（Excelで保存した形式）を正しく読み込めるように対応</li>
  </ul>
</section>
HTML,
            ],
            // ─────────────────────────────────────────────────────────────
            // 0g. PROOF-UNIFY-1 — 2026-05-30
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'proof-unify-1',
                'title'        => '校正ジョブUI統合：「依頼されたジョブ」フローへの統一・完了同期修正',
                'released_at'  => '2026-05-30',
                'summary'      => '校正ジョブ専用タブを廃止し、通常の「依頼されたジョブ → マイジョブ」フローに統一しました。校正割当と通常割当の完了が連動しなかった問題を修正。スケジュールは校正担当者のカレンダーに直接反映されるようになりました。',
                'design_files' => [],
                'claude_notes' => 'ProofRequestController: complete()/uncomplete() の sender!=user 条件を削除（自己proof時の完了不具合を解消）。MyProjectJobController: completeAssignment() に maybeCompleteProofRequest() を追加（pja100直接 / supersedes_assignment_id 経由の両パターン対応）。EventController: supersedes_assignment_id パスへのproof完了フック追加。SavesProofWorkSlots: pja101作成を廃止しpja100に直接Eventを作成。ProofRequestController::assignStore(): JobAssignmentMessage 作成で「依頼されたジョブ」タブへの表示を実現。UserNavigationTabs.vue: 校正ジョブタブを削除。MyJobBox/Show.vue: proof型割当に校正依頼情報カード（依頼者・校正管理者・締切・ステータス）を追加。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <ul>
    <li>校正ジョブが「校正ジョブ」タブと「マイジョブBOX」に二重表示され、どちらかを完了にしても連動しなかった</li>
    <li>校正管理者がセットしたスケジュールがマイジョブBOXに反映されなかった</li>
    <li>「校正ジョブ」タブが「依頼されたジョブ」と役割が重複しており、利用者が混乱していた</li>
    <li>校正担当者=自分（自己proof）の場合に完了ボタンが機能しなかった</li>
  </ul>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li><strong>「校正ジョブ」タブ廃止：</strong>ナビゲーションから「校正ジョブ」タブを削除。既存URLはすべて「依頼されたジョブ」へリダイレクト</li>
    <li><strong>依頼されたジョブへ統合：</strong>校正割当時に JobAssignmentMessage が自動生成されるため、通常の「依頼されたジョブ」タブに自然に表示されるようになった</li>
    <li><strong>完了連動の修正：</strong>マイジョブBOXから完了にすると、対応する ProofRequest も完了になり完了通知が送信される。逆に校正管理者が完了にした場合も同様に連動する</li>
    <li><strong>スケジュール直接反映：</strong>校正管理者がセットした作業スロットが校正担当者のカレンダーに直接反映される（中間割当 pja101 を廃止）</li>
    <li><strong>校正依頼情報カードの表示：</strong>マイジョブBOX詳細ページで校正ジョブを開くと、依頼者・校正管理者・締切・ステータス（校正待ち/校正中/校正完了）が表示されるようになった</li>
  </ul>
</section>

<section class="cl-note">
  <h3>補足</h3>
  <ul>
    <li>PCを持たない校正担当者向けの「校正管理者が代わりに完了する」フローは引き続き利用可能です</li>
    <li>校正担当者がマイジョブにした場合（「マイジョブにする」ボタン経由）も、元の校正割当と完了が連動します</li>
  </ul>
</section>
HTML,
            ],
            // ─────────────────────────────────────────────────────────────
            // 0h. COTYPE-1 — 2026-05-30
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'cotype-1',
                'title'        => '会社タイプ別機能分離：サン・ブレーン専用機能の部署制御・SuperAdmin コンテキスト管理',
                'released_at'  => '2026-05-30',
                'summary'      => '会社ごとに使える機能を切り分ける仕組み（COTYPE）を導入しました。サン・ブレーンでは情報出版・製版など部署ごとに専用機能を有効化できます。SuperAdmin がヘッダーから会社を切り替えて各社の管理ができるコンテキスト切り替え機能も追加しました。校正依頼ボタンや「校正管理へ依頼」オプションは情報出版部署のユーザーにのみ表示されるよう制御されています。',
                'design_files' => ['z_instructions/COTYPE_PLAN1.md', 'z_instructions/COTYPE_MANAGER1.md'],
                'claude_notes' => '【DB】companies.company_type(sunbrain|general), departments.module(publishing|prepress|ondemand), users.home_company_id を追加（migration 3本）。【ミドルウェア】CheckCompanyType: company_type を検証してルートを保護。ProofCoordinator・Prepress ルートに company_type:sunbrain を追加。【フロントエンド】CompanyModules レジストリ（sunbrain.js/general.js/index.js）でナビゲーションボタンの会社別制御を実現。CompanyModuleNavButtons.vue が extraRoles を動的描画（group=beforeUser/afterUser で位置制御）。SuperAdminContextSwitcher.vue でヘッダーから会社コンテキスト切り替え。【featureFlags】HandleInertiaRequests に auth.featureFlags.proofRequest/prepressBoard を追加。MyJobBox/Show・User/ProjectJobs/Show・Coordinator/ProjectJobs/Show・ProgressSheets/Show・WorkflowSheets/Show の校正依頼UIをフラグでガード。ProgressCell.vue の「校正管理へ依頼」オプションも usePage() 経由でガード。【SuperAdmin UX】DashboardController に ResolvesContextCompany を追加しイルカボードをコンテキスト会社でフィルタ。UserPresenceController も同様に対応。SuperAdmin/Users/Index.vue に会社タブ（filter_company クエリパラメータ）追加。SuperAdminNavigationTabs に「ユーザー管理」リンク追加。CompanyController に generateCode() ヘルパー追加（部署・担当の code 自動生成）。会社登録・編集フォームに code 入力欄とコード説明 Tips ボタンを追加。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <ul>
    <li>サン・ブレーンと将来のグループ各社（サンエー印刷など）が同じシステムを使うにあたり、サン・ブレーン固有の機能（校正管理・製版ボード・校正依頼ジョブフロー）が他社ユーザーにも表示されてしまっていた</li>
    <li>SuperAdmin がどの会社の管理をしているかが画面上で分からず、ユーザー一覧や在籍ボードに全社のデータが混在していた</li>
    <li>情報出版部署以外のユーザーが「校正依頼」ボタンを押してエラーになるケースが想定された</li>
    <li>会社・部署・担当の新規登録時に code カラムが必須のためエラーが発生していた</li>
  </ul>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li><strong>会社タイプ制御の導入：</strong>companies テーブルに company_type（sunbrain / general）を追加。サン・ブレーン専用ルート（校正管理・製版）には company_type:sunbrain ミドルウェアを付与し、他社ユーザーは 403 でアクセス不可になった</li>
    <li><strong>部署モジュールによる機能制御：</strong>departments テーブルに module（publishing / prepress / ondemand）を追加。ナビゲーションボタン「Proof Admin」「Prepress」の表示を部署モジュールとロールで制御。情報出版 Leader のみ「Proof Admin」が表示され、Coordinator・User には表示されない</li>
    <li><strong>校正依頼 UI のガード：</strong>auth.featureFlags.proofRequest フラグを導入し、情報出版部署（または Admin/SuperAdmin）以外では校正依頼ボタン・セクション・「校正管理へ依頼」オプションが非表示になった。進行表・管理シートのモーダルも同様にガード済み</li>
    <li><strong>SuperAdmin コンテキスト切り替え：</strong>ヘッダーに会社切り替えドロップダウンを追加。選択した会社に応じてユーザー一覧・在籍ボード・ナビゲーションメニューが切り替わる</li>
    <li><strong>SuperAdmin ユーザー一覧の改善：</strong>ユーザー一覧画面に会社タブ（全て・各社）を追加。タブ切り替えで会社ごとのユーザーを確認可能。SuperAdmin タブメニューに「ユーザー管理」リンクも追加</li>
    <li><strong>会社登録・編集フォームの改善：</strong>部署・担当の code フィールドを任意入力に（未入力時は自動生成）。「コードとは？」ボタンで説明を確認できる Tips を追加</li>
  </ul>
</section>

<section class="cl-note">
  <h3>補足</h3>
  <ul>
    <li>新しいグループ会社を追加するには SuperAdmin → 会社追加 から company_type を選択してください。sunbrain 専用機能が不要な場合は「一般」を選びます</li>
    <li>部署に専用機能を割り当てるには会社編集画面の「機能」セレクトを使います（sunbrain タイプの会社のみ表示）</li>
    <li>進行表・管理シートの proof_v2 セル（校正担当列）はデータを保持したまま、情報出版部署以外では「校正管理へ依頼」オプションが非表示になります</li>
    <li>さくら本番への適用には migration 3本（company_type・module・home_company_id）をコードデプロイ前に実行する必要があります</li>
  </ul>
</section>
HTML,
            ],
            // ─────────────────────────────────────────────────────────────
            // 0i. ANNEX-1 — 2026-05-30
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'annex-1',
                'title'        => 'お知らせ機能強化：会社横断送信・添付ファイル・編集削除・SuperAdmin ユーザー管理改善',
                'released_at'  => '2026-05-30',
                'summary'      => 'お知らせ（通知）機能を大幅に強化しました。サンエー印刷の Clerk は全会社またはグループ各社を選んで通知を送信できるようになりました。通知に PDF・画像などの添付ファイルを付けられるようになり、受信側では本文の下に画像・PDF の内容が直接表示されます。送信済み通知のタイトル・本文・添付ファイルを編集・削除できるようにもなりました。SuperAdmin のユーザー管理画面にも詳細・編集ページと会社タブを追加しています。',
                'design_files' => ['z_instructions/ANNEX_PLAN1.md', 'z_instructions/ANNEX_MANAGER1.md'],
                'claude_notes' => '【DB】announcements.target_company_id (nullable FK) を追加（migration 1本）。【モデル】Announcement に target_company_id fillable + attachments() morphToMany 追加。【ルート】clerk.announcements.edit / update / destroy を追加。【featureFlags】HandleInertiaRequests に crossCompanyAnnouncement フラグ追加（general タイプ会社の Clerk/Admin）。【コントローラー】Clerk/AnnouncementController: store() に会社スコープ対応（未選択=全会社 / 指定会社=その会社のみ / 一般ユーザー=自社のみ）+ AttachmentService による添付保存。edit() / update()（タイトル・本文・添付のみ、受信者変更なし）/ destroy()（添付クリーンアップ含む）追加。AnnouncementController: 受信者側 show() に attachments eager load 追加。attachments mapping: mime_type カラム名修正（$a->mime → $a->mime_type）。【フロントエンド】Create.vue: 会社チェックボックス（複数選択対応 / ソート可能）+ ドロップゾーン添付 + PDF.js サムネイル（scale 1.5/2.5）+ ライトボックス + router.post forceFormData で送信。Show.vue (Clerk): 添付インライン表示 + PDF.js で描画 + 50%幅 + ライトボックス + 編集・削除ボタンを #headerExtras へ。Edit.vue: 新規作成（タイトル・本文・既存添付削除 + 新規追加）。Announcements/Show.vue (受信側): 同様に添付インライン表示 + PDF.js 描画。【SuperAdmin ユーザー管理】SuperAdmin/Users/Show.vue・Edit.vue 新規作成（Admin 版をベースに superadmin ルートへ適用）。SuperAdmin/UserController::edit() / update() を Admin 相当に強化（companies + positionTitles 渡し / 完全バリデーション）。Index.vue に SuperAdminNavigationTabs(active=all_users) 追加。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <ul>
    <li>お知らせ機能は自社内のみの送信で、グループ会社（サンエー印刷）から他社への通知ができなかった</li>
    <li>お知らせに添付ファイルを添付する機能がなく、PDF や画像を共有するにはメッセージ機能を使う必要があった</li>
    <li>送信済みのお知らせを後から修正・削除できなかった</li>
    <li>SuperAdmin のユーザー管理画面に詳細・編集ページがなく、「詳細」「編集」ボタンを押してもエラーになっていた</li>
  </ul>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li><strong>会社横断送信：</strong>サンエー印刷（グループ親会社）の Clerk は通知作成時に会社チェックボックスで送信先を選択できる。未選択で全会社、特定会社を選ぶとその会社のメンバーのみに送信</li>
    <li><strong>添付ファイル：</strong>お知らせ作成時にドロップ兼クリック選択で複数ファイルを添付できる。画像は即時プレビュー、PDF は PDF.js でサムネイル表示。受信側では本文の下に添付内容が直接描画される</li>
    <li><strong>PDF インライン表示：</strong>添付 PDF は PDF.js で第1ページを画像化して表示。50% 幅で表示され、クリックすると全画面ライトボックスで拡大確認できる</li>
    <li><strong>編集・削除：</strong>送信済みお知らせのタイトル・本文・添付ファイルを編集できる（受信者は変更不可）。削除時は添付ファイルも含めて完全削除される</li>
    <li><strong>SuperAdmin ユーザー管理強化：</strong>詳細ページ（Show.vue）・編集ページ（Edit.vue）を新規作成。Admin の編集画面と同等の項目（会社・部署・担当・権限・雇用形態・役職称号・パスワード変更）を設定可能</li>
  </ul>
</section>

<section class="cl-note">
  <h3>補足</h3>
  <ul>
    <li>会社横断送信は general タイプ会社の Clerk / Admin / SuperAdmin のみ利用可能。サン・ブレーンの Clerk は引き続き自社内のみに送信</li>
    <li>個別選択（individual）で宛先を指定した場合、選択した会社チェックボックスに関係なく指定ユーザーのみに送信されます</li>
    <li>編集は送信者本人のみ可能。編集しても既読状況はリセットされません</li>
    <li>PDF サムネイルはページ読み込み後に非同期で描画されます（数秒かかる場合があります）</li>
  </ul>
</section>
HTML,
            ],

            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'tenant-1',
                'title'        => 'マルチテナント情報隔離：会社ごとにデータを完全分離',
                'released_at'  => '2026-05-30',
                'summary'      => '複数会社（サン・ブレーン・サンエー印刷）が同一システムを使う際に、会社をまたいだデータ漏洩が発生する問題を修正しました。Admin・Leader の案件総覧・進行レポート・チーム管理が自社データのみに絞り込まれます。校正機能（校正状況タブ・校正ジョブ）はサン・ブレーン専用機能となり、他社ユーザーには表示されなくなりました。',
                'design_files' => ['z_instructions/TENANT_PLAN1.md', 'z_instructions/TENANT_MANAGER1.md'],
                'claude_notes' => '【DB】project_jobs に company_id (nullable FK → companies) を追加。backfill: client.company_id 経由で全既存レコードに反映。【モデル】ProjectJob: company_id fillable 追加 / company() BelongsTo / scopeForCompany() 追加。【重大バグ修正】Leader/ProjectJobController: deptMemberIds・unitMemberIds が両方空のとき Laravel の where クロージャが WHERE 句なしになり全案件が返る問題。チームなし Leader は早期 return で 0 件を返すよう修正。【Admin/ProjectJobController】ResolvesContextCompany trait 追加。index(): Team/ProjectJob を contextCompanyId でフィルタ。show(): job.company_id がコンテキスト会社と不一致なら 403。【Admin/TeamController】ResolvesContextCompany trait 追加。index(): company_id でフィルタ。【Coordinator/ProjectJobController】store() / storeFromTemplate() / clone() / shareToUser() の ProjectJob::create 呼び出しに company_id をセット。【Coordinator/ProgressReportController】Admin/Clerk 時も自社の project_jobs でスコープ。SuperAdmin は contextCompanyId に応じてスコープ（null=全社参照）。【Leader/ProjectJobController】index() に company_id フィルタ追加 + 空チーム早期 return。show() に 他社案件 403 チェック追加。【routes/web.php】user proof 5ルート（user.proof.status / user.proof_jobs.*）を company_type:sunbrain ミドルウェアグループで保護。【UserNavigationTabs.vue】auth.companyType === sunbrain のときのみ「校正状況」タブを表示。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <ul>
    <li>サンエー印刷を追加したところ、そのAdmin・Leaderがサン・ブレーンの案件・チーム・進行レポートを参照できる状態になっていた</li>
    <li>チームに未所属のLeaderがログインすると全社全案件が表示されるバグが潜在していた（Laravelの空whereクロージャ問題）</li>
    <li>校正機能（校正状況・校正ジョブ）のルートが company_type 制限なしで公開されており、他社ユーザーもアクセス可能だった</li>
    <li>project_jobs テーブルに company_id がなく、会社単位のスコープができない構造だった</li>
  </ul>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li><strong>DB追加：</strong>project_jobs に company_id カラムを追加。既存89件はclient経由でcompany_id=2（サン・ブレーン）にバックフィル済み</li>
    <li><strong>Admin 案件総覧：</strong>部署フィルタが自社部署のみに、案件一覧も自社案件のみに絞り込まれるよう修正。他社案件URLへの直接アクセスは403</li>
    <li><strong>Admin チーム管理：</strong>自社チームのみ表示されるよう修正</li>
    <li><strong>Leader 案件総覧（重大バグ修正）：</strong>チーム未割り当てのLeaderが全案件を閲覧できるバグを修正。空チーム時は0件を返す + 他社案件への直接アクセスは403</li>
    <li><strong>進行レポート：</strong>AdminおよびClerkも自社案件のみ表示されるよう修正</li>
    <li><strong>新規案件作成：</strong>案件作成・複製・テンプレート作成時に company_id を自動セット</li>
    <li><strong>校正機能の会社制限：</strong>校正状況・校正ジョブの全ルートを company_type:sunbrain で保護。他社ユーザーは 403</li>
    <li><strong>ナビゲーション：</strong>「校正状況」タブをサン・ブレーンユーザーのみに表示</li>
  </ul>
</section>

<section class="cl-note">
  <h3>補足</h3>
  <ul>
    <li>SuperAdmin はコンテキスト会社切り替えに応じて各社データを参照可能（グローバルモードは全社参照を維持）</li>
    <li>client_id が未設定の案件（id=6「その他」）は company_id=NULL のまま。Admin 案件総覧では forCompany スコープにより表示されない</li>
    <li>サンエー印刷の部署間隔離は「チーム単位のメンバー管理」で自然に担保される。総務チームのLeaderは総務メンバーが担当する案件のみ閲覧可能</li>
  </ul>
</section>
HTML,
            ],
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'coshare-1',
                'title'        => 'クライアント会社間データ隔離：company_clients 中間テーブル導入',
                'released_at'  => '2026-05-30',
                'summary'      => 'サンエー印刷のAdminがサン・ブレーンのクライアント一覧・部署タブを閲覧できてしまう問題を修正しました。company_clients 中間テーブルを導入し、各社は自社に登録されたクライアントのみ表示・操作できるようになりました。クライアントはグループ共通マスターとして管理され、複数社で共有することも、一社専用にすることも可能です。',
                'design_files' => ['z_instructions/COSHARE_PLAN1.md', 'z_instructions/COSHARE_MANAGER1.md'],
                'claude_notes' => '【DB】company_clients (company_id FK, client_id FK, PK複合) テーブルを新設。既存44件のクライアントを全てサン・ブレーン(id=2)として移行。clients.company_id カラムは互換性維持のため残存。【Client モデル】companies() belongsToMany 追加。scopeForCompany を clients.company_id 比較 → company_clients の whereHas に変更。【ClientPolicy】view/update/delete を client->companies()->where(companies.id, user.company_id)->exists() ベースに変更。SuperAdmin のみ全クライアント操作可。【ClientController】index(): superadmin のみ全件、それ以外は forCompany 適用。自社部署一覧を departments prop として追加 pass。create()/edit(): departments を自社のみにフィルタ。store()/csvStore(): クライアント作成時に company_clients へも attach。merge()/batchMerge(): マージ時に source の company_clients を syncWithoutDetaching で target に引き継ぐ。clientsJson()/checkDuplicate(): admin も forCompany 適用。client_code uniqueness をグローバルに統一。【Index.vue】departments prop を新設しサーバーから自社部署のみ受け取る。DEPT_COLORS ハードコード除去し id ベースのカラーパレットに変更。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <ul>
    <li>サンエー印刷のAdminがクライアント管理を開くと、サン・ブレーンの全44件と部署タブ（情報出版・製版・オンデマンド）が表示されてしまっていた</li>
    <li>原因：ClientController::index() でadminロールはforCompanyスコープが未適用で、全クライアントを取得していた</li>
    <li>clients.company_id カラムは「どの会社が所有するか」を表すが、グループ間で同じクライアントを共有する場合に対応できない構造だった</li>
  </ul>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li><strong>company_clients 中間テーブル導入：</strong>「どの会社がどのクライアントを使うか」を管理。既存44件はサン・ブレーン所属として一括移行済み</li>
    <li><strong>表示スコープの修正：</strong>SuperAdmin以外は自社に登録されたクライアントのみ表示・操作可能に</li>
    <li><strong>部署タブの修正：</strong>クライアント一覧の部署フィルタタブが自社の部署のみ表示されるよう変更</li>
    <li><strong>クライアント作成：</strong>新規作成・CSV一括登録時に自動でcompany_clientsに登録</li>
    <li><strong>クライアント統合：</strong>マージ時に統合元のcompany_clients（利用会社の紐付け）を統合先に引き継ぐ</li>
    <li><strong>スケーラビリティ：</strong>将来のグループ会社追加時も、company_clientsにレコードを追加するだけで対応可能</li>
  </ul>
</section>

<section class="cl-note">
  <h3>補足</h3>
  <ul>
    <li>クライアントはグループ共通マスター。同一クライアント（同じclient_code）を複数社で使う場合はcompany_clientsに両社分のレコードを登録する</li>
    <li>clients.company_idカラムは削除せず残存（互換性維持）</li>
    <li>本番デプロイ時にphp artisan migrateが必要（company_clientsテーブル作成 + 既存データ移行）</li>
  </ul>
</section>
HTML,
            ],
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'coshare-2',
                'title'        => 'クライアント共有UI拡張：編集モード・共有確認モーダル・削除ロジック改善',
                'released_at'  => '2026-05-31',
                'summary'      => 'クライアント管理に「編集モード」と「共有確認モーダル」を追加しました。編集モードでは Admin/Leader/Coordinator が自社部署の紐付けをワンクリックで切り替えられ、SuperAdmin はグループ全社の部署を会社ごとに色分けして一覧管理できます。また、他社が使っているクライアントIDを入力した際に「共有しますか？」の確認モーダルが表示されるようになりました。削除時も共有状態に応じて「共有解除」と「完全削除」を自動的に使い分けます。',
                'design_files' => ['z_instructions/COSHARE_PLAN2.md', 'z_instructions/COSHARE_MANAGER2.md'],
                'claude_notes' => '【新ルート×3】clients/{client}/share-to-my-company / toggle-dept / toggle-company（admin/leader/coordinator 各グループ）。【ClientController】checkDuplicate(): other_company_match を whereNotIn on company_clients で実装（whereDoesntHaveより確実）。store(): 他社同コードチェックを Rule::unique より先に実行しユーザーフレンドリーなエラーを返す。index(): allDepts（全社部署+会社名）を SuperAdmin 用に追加 pass。edit(): sharedWith（他社リスト）を追加。toggleDeptAdmin(): SA は全社部署を操作可 + company_clients 自動同期。destroy(): 他社共有中は company_clients detach のみ、自社専用時のみ物理削除。shareToMyCompany(): department_ids を受け取り自社部署にも紐付け。【Create.vue】checkDuplicate fetch が419のとき window.location.reload()。other_company_match 検出時に共有確認モーダル（部署選択 + 色付きバッジ付き）。DEPT_COLORS ハードコードを deptColor(dept) パレット関数に統一。【Edit.vue】sharedWith prop を受け取り confirmDelete() で「共有解除 vs 完全削除」メッセージを切り替え。【Index.vue】編集モードOFF時に router.reload({only:[clients]})。clientsState を props.clients の watch で再同期。SuperAdmin 編集モード: COMPANY_PALETTES で会社ごとの色系、legendCompanies で凡例表示、allDepts の全部署ボタンで部署トグル。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <ul>
    <li>他社のクライアントIDを入力しても何の警告もなく別クライアントとして登録できてしまっていた</li>
    <li>クライアントを削除すると共有中でも物理削除されてしまい、他社のデータも消える問題があった</li>
    <li>部署の紐付けや会社間共有を変更するには編集画面を個別に開く必要があり、一括管理ができなかった</li>
    <li>SuperAdmin が別会社のクライアントを管理するには別アカウントでログインし直す必要があった</li>
  </ul>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li><strong>共有確認モーダル：</strong>クライアント新規作成時に他社が使用中のコードを入力すると「共有しますか？」モーダルを表示。部署も同時に選択して共有登録できる</li>
    <li><strong>編集モード（Admin/Leader/Coordinator）：</strong>クライアント一覧で「編集モード」をONにすると各行に自社部署のトグルボタンが表示され、ワンクリックで部署の紐付けを変更できる</li>
    <li><strong>編集モード（SuperAdmin）：</strong>グループ全社の部署が会社ごとに色分けされて表示され、凡例と合わせて横断的に管理可能。部署の追加・削除で company_clients も自動同期</li>
    <li><strong>削除ロジックの改善：</strong>他社と共有中のクライアントは「共有解除（自社の紐付けのみ削除）」、自社専用の場合のみ「完全削除」。削除確認ダイアログでも状態に応じてメッセージを変更</li>
    <li><strong>419エラー対策：</strong>CSRFタイムアウト時は自動でページリロードし、サーバーサイドでも他社同コードを事前チェックするガードを追加</li>
  </ul>
</section>

<section class="cl-note">
  <h3>補足</h3>
  <ul>
    <li>編集モードをOFFにすると画面がリロードされ、変更が即座に通常表示に反映される</li>
    <li>SuperAdmin の編集モードでは部署名が複数社でかぶる場合も会社色で識別可能</li>
    <li>共有解除時は自社の company_clients エントリと自社部署の紐付けのみ削除。他社のデータは保持される</li>
  </ul>
</section>
HTML,
            ],
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'tenant-2',
                'title'        => 'company_id フィルター漏れ修正：Coordinator/User コントローラー全域',
                'released_at'  => '2026-06-02',
                'summary'      => 'Coordinator ロールの案件作成・一括作成・ジョブ割り当て・進行テンプレート各画面で、リーダー/メンバー/部署/マスターデータの選択肢が他社データを含んでいた問題を修正しました。バリデーションでも他社ユーザーIDを指定できた脆弱性を塞ぎました。',
                'design_files' => ['z_instructions/TENANT_PLAN2.md', 'z_instructions/TENANT_MANAGER2.md'],
                'claude_notes' => '【ProjectJobController】coordinatorCandidates() に company_id フィルター追加。create()/edit() の members・departments を同一会社のみに絞り込み。【BulkProjectJobController】index()/sharedProps() の coordinatorCandidates・users・departments・members を company_id フィルター。validateRow() と findClientByFlexibleName() に companyId 引数を追加し leader 名マッチング・クライアント名マッチングを自社限定に。store() の ProjectJob::create() に company_id を追加（欠落バグ修正）。resolveCompanyId() ヘルパー追加（SuperAdmin はセッションコンテキスト参照）。【ProjectJobAssignmentsController】Rule 追加。update()/store() の user_id・sender_id バリデーションを Rule::exists() に変更し他社ユーザーID指定を防止。create()/edit()/show() の WorkItemType/Size/Stage/Status を whereNull("company_id")->orWhere("company_id", $id) でフィルター。【ProgressTemplateController】create()/edit() の Stage/Size/WorkItemType を同フィルターに修正。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <ul>
    <li>案件作成・一括作成フォームで、リーダー・サブリーダー・チームメンバーの選択肢に他社ユーザーが表示されていた</li>
    <li>一括CSV インポート時、リーダー名マッチングが全ユーザーを対象にしており他社の同名ユーザーに誤マッチする可能性があった</li>
    <li>一括作成で登録された案件に company_id がセットされておらず、会社フィルターが効かない状態で作成されていた（バグ修正）</li>
    <li>ジョブ割り当て画面のバリデーションで他社ユーザーのIDをPOSTできてしまう脆弱性があった</li>
    <li>ジョブ割り当て・進行テンプレートの各種マスター選択肢（ステージ・サイズ・作業種別・ステータス）が全社から返されていた</li>
  </ul>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li><strong>案件作成・編集：</strong>リーダー候補・メンバー・部署を自社のみに絞り込み</li>
    <li><strong>一括作成：</strong>選択肢フィルター加えて CSV インポートのリーダー名・クライアント名マッチングも自社限定に。作成時の company_id 欠落バグも修正</li>
    <li><strong>ジョブ割り当てバリデーション：</strong>user_id・sender_id に Rule::exists() で company_id 条件を追加</li>
    <li><strong>マスターデータ：</strong>ステージ・サイズ・作業種別・ステータスを「全社共通(NULL) または自社」のみ返すよう変更</li>
    <li><strong>SuperAdmin 対応：</strong>resolveCompanyId() ヘルパーでセッションコンテキストを参照</li>
  </ul>
</section>
HTML,
            ],
            // ─────────────────────────────────────────────────────────────
            [
                'version'      => 'subleader-1',
                'title'        => 'サブリーダー権限チェック漏れ修正：日報タイムテーブル・日報一覧・勤務記録',
                'released_at'  => '2026-06-02',
                'summary'      => '部署サブリーダーが日報詳細のタイムテーブル・日報一覧・勤務記録一覧を閲覧できなかった問題を修正しました。各コントローラーの権限チェックが teams.leader_id のみを参照しており、team_sub_leaders テーブルを考慮していないことが原因でした。',
                'design_files' => [],
                'claude_notes' => '【EventController】buildPermittedUserIdsForActor() に team_sub_leaders テーブル参照を追加。サブリーダーのチームもマージして permitted user ids を構築するよう修正。日報詳細ページ（Interactions/Show.vue）が events.index に user_id 付きでリクエストする際、サブリーダーは 403 になりタイムテーブルが空になっていた。【Diaries/DiaryController】index() の $isLeader 判定を team_sub_leaders 含む $allTeams.isNotEmpty() に変更。サブリーダーが leader_id に登録されていない場合 abort(403) になっていた。【WorkRecordController】buildPermittedUserIds() の Leader セクションで leader_id チームに加え team_sub_leaders 経由のチームをマージ。サブリーダーのメンバーが勤務記録一覧に表示されなかった。',
                'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <ul>
    <li>部署サブリーダーが日報詳細ページを開いてもタイムテーブル（当日の予定）が空欄のままだった</li>
    <li>サブリーダーが日報一覧ページを開くと 403 エラーになり閲覧できなかった</li>
    <li>勤務記録一覧でサブリーダーが管轄するメンバーのデータが表示されなかった</li>
    <li>原因：各コントローラーの権限チェックが <code>teams.leader_id</code> のみを参照し、<code>team_sub_leaders</code> 中間テーブルを参照していなかった</li>
  </ul>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li><strong>EventController：</strong><code>buildPermittedUserIdsForActor()</code> に <code>team_sub_leaders</code> 参照を追加し、サブリーダーのチームも permitted user ids に含めるよう修正</li>
    <li><strong>Diaries/DiaryController：</strong><code>$isLeader</code> 判定をサブリーダーチームも含む形に変更。日報一覧の 403 を解消</li>
    <li><strong>WorkRecordController：</strong><code>buildPermittedUserIds()</code> でリーダーチームとサブリーダーチームをマージして処理するよう変更</li>
    <li>参照実装（DiaryInteractionController の <code>buildPermittedUserIds</code>）はすでに正しく対応済みであり、これに揃える形で統一</li>
  </ul>
</section>
HTML,
            ],
            [
                'version'      => 'workload-dept-1',
                'title'        => '作業項目設定の部署スコープ対応：会社全体・部署別に独立して設定可能',
                'released_at'  => '2026-06-02',
                'summary'      => '作業項目設定（Stages / Work Item Types / Sizes / Statuses / Difficulties）に部署スコープ切り替え機能を追加しました。会社全体の共通設定と部署ごとの固有設定を完全に独立して登録・編集できます。また、client_id なしでも案件を登録・更新できるようバリデーションを修正しました。',
                'design_files' => ['z_instructions/WORKLOAD_DEPT_PLAN1.md'],
                'claude_notes' => '【WorkloadSettingController】resolveScope() / fetchDepartments() / fetchItems() を新設。Leader は自部署スコープを強制、SuperAdmin/Admin は ?dept= クエリパラメータで切り替え。store() は POST の scope パラメータから department_id を決定し、Leader が他部署スコープで送信した場合は 403。fetchItems() はスコープに応じて department_id IS NULL（会社全体）または department_id = X（部署固有）でフィルタリング。【Index.vue / Edit.vue】ページ上部に部署スコープバーを追加。Leader は自部署ボタンのみ有効（青）、会社全体・他部署はグレー・disabled。SuperAdmin/Admin は全ボタン有効で切り替え可能。【ProjectJobController】store()/update() の client_id バリデーションを required→nullable に変更。DBスキーマは nullable だったが PHP バリデーションが required のままで本番で登録不可になっていた。',
                'body'         => <<<'HTML'
<section class="cl-feature">
  <h3>新機能：作業項目設定の部署スコープ</h3>
  <ul>
    <li>設定ページ上部に「会社全体」「部署名」のスコープ切り替えボタンを追加</li>
    <li>会社全体（<code>department_id = NULL</code>）と部署固有（<code>department_id = X</code>）の設定が完全に独立して管理可能</li>
    <li><strong>Leader：</strong>自分の部署ボタンのみ有効（青色）、会社全体・他部署はグレー表示で操作不可</li>
    <li><strong>SuperAdmin / Admin：</strong>すべてのボタンをクリックして自由にスコープを切り替え可能</li>
    <li>既存の設定データはすべて「会社全体」スコープに自動分類（互換性を維持）</li>
  </ul>
</section>

<section class="cl-fix">
  <h3>バグ修正</h3>
  <ul>
    <li><strong>案件登録・更新：</strong><code>client_id</code> が未入力の場合に登録がはじかれる問題を修正。DBは <code>nullable</code> だがPHPバリデーションが <code>required</code> になっていたことが原因</li>
  </ul>
</section>
HTML,
            ],
            [
                'version'      => 'workload-ui-2',
                'title'        => '作業項目設定 UI 大改修：インライン編集モード・部署トグルボタン・使い方ガイド',
                'released_at'  => '2026-06-02',
                'summary'      => '作業項目設定（workload-setting）を大幅に改修しました。別ページだった編集画面を廃止し、一覧ページ内でインライン編集できるようになりました。部署トグルボタンで会社全体の項目を各部署に割り当てる操作が直感的に行えます。また、使い方ガイドボタンも追加しました。',
                'design_files' => ['z_instructions/WORKLOAD_UI2_PLAN1.md'],
                'claude_notes' => '【主な変更】①Edit.vue廃止→Index.vueにインライン編集モード統合。②headerExtrasに「✎ 編集モード」ボタン。③編集モードで全5タイプのインライン編集（追加・削除・▲▼並べ替え）。④グループ化タイプでグループ追加・名前変更・削除・並べ替え。⑤既存項目行に部署トグルボタン（青ベタ=登録済、白枠=未登録）→保存時にON→部署追加/OFF→部署削除を実行。⑥会社全体スコープで部署バッジ（読み取り・編集両モード）。⑦GeneralWorkItemDefaultsService: company_id/department_id の fillable 追加、スキップ判定を会社全体スコープ（dept=NULL）の有無に修正。⑧buildGroupConfig の null 強制追加バグ修正（savedOrder フィルタリング）。⑨使い方ガイドモーダル追加。',
                'body'         => <<<'HTML'
<section class="cl-feature">
  <h3>主な改善内容</h3>
  <ul>
    <li><strong>インライン編集モード：</strong>「✎ 編集モード」ボタンで一覧ページ内のまま全タイプを編集可能に。別ページへの遷移が不要になりました</li>
    <li><strong>部署トグルボタン：</strong>会社全体の項目に部署ボタンが表示され、クリックで ON/OFF を切り替え。保存するとその部署への追加・削除が自動で行われます</li>
    <li><strong>グループ管理：</strong>グループの追加・名前変更・削除・並べ替えを編集モード内で直接操作可能</li>
    <li><strong>部署バッジ（読み取りモード）：</strong>会社全体スコープの各項目に、登録済み部署のバッジを表示</li>
    <li><strong>使い方ガイド：</strong>ページ右上の「使い方」ボタンから操作フローを確認できます</li>
    <li><strong>新規会社への自動登録：</strong>新しい会社を追加すると、一般的な作業項目（総務・経理・営業・管理共通）が自動で登録されます</li>
  </ul>
</section>

<section class="cl-fix">
  <h3>バグ修正</h3>
  <ul>
    <li>編集モードで未登録グループが表示される問題を修正（保存済みグループ順序と実際の項目の不一致）</li>
    <li><code>WorkItemType</code> モデルの <code>company_id</code> / <code>department_id</code> が fillable に含まれておらず、スコープが正しく保存されなかった問題を修正</li>
  </ul>
</section>

<section class="cl-guide">
  <h3>推奨ワークフロー</h3>
  <ol>
    <li>「会社全体」スコープで共通作業項目を登録する</li>
    <li>編集モードの部署ボタンで各部署に割り当てる（青ボタン=登録済）</li>
    <li>必要に応じて部署スコープで独自の追加項目を登録する</li>
  </ol>
</section>
HTML,
            ],
        [
            'version'      => 'diary-team-1',
            'title'        => '日報権限チーム機能追加・日報編集の流用ボタン修正',
            'released_at'  => '2026-06-02',
            'summary'      => 'Admin が「日報権限チーム」を作成し、Clerk・Coordinator・校正Coordinator をチームのリーダーに任命できるようになりました。任命されたユーザーは「日報管理」メニューから担当チームのメンバーの日報を閲覧・既読・コメントできます。また、日報編集ページでも「過去データから流用」ボタンが使用できるようになりました。',
            'design_files' => ['z_instructions/DIARYTEAM_PLAN1.md'],
            'claude_notes' => '【主な変更】①新規テーブル3つ: diary_teams / diary_team_leaders（pivot）/ diary_team_members（pivot）。②DiaryTeamモデル追加。③User::isDiaryManager() / diaryManagerMemberIds() 追加。④DiaryManagerMiddleware追加（diary_team_leadersに登録済みユーザーのみ許可）。⑤Admin/DiaryTeamController (CRUD, diary_management権限で制御, SuperAdmin対応)。⑥DiaryManager/DiaryInteractionController（buildPermittedUserIdsをdiaryManagerMemberIds()に差し替え、routePrefix=diary_manager）。⑦diary-manager. ルートグループ追加。⑧HandleInertiaRequests にisDiaryManager shared data追加。⑨AppLayout.vue: currentRouteContext に diary_manager.* 対応, getTopTabActive に diary_teams 対応。⑩Admin/DiaryTeams/{Index,Create,Edit}.vue追加。⑪Clerk/Coordinator/ProofCoordinatorNavigationTabsに「日報管理」タブ（isDiaryManager条件付き）追加。AdminNavigationTabsに「日報権限管理」タブ追加。⑫Diaries/Edit.vue に「過去データから流用」ボタン追加（DiaryController::create() が既存日報があると edit にリダイレクトするため、Edit 側にも流用機能が必要だった）。',
            'body'         => <<<'HTML'
<section class="cl-feature">
  <h3>新機能：日報権限チーム</h3>
  <ul>
    <li><strong>Admin → 日報権限管理：</strong>Admin メニューの「日報権限管理」タブから日報権限チームを作成・管理できます。チームに「日報マネージャー（Clerk / Coordinator / 校正Co）」と「閲覧対象メンバー」を設定します</li>
    <li><strong>日報マネージャー：</strong>チームのリーダーに任命されたユーザーのナビに「日報管理」ボタンが出現します</li>
    <li><strong>限定閲覧：</strong>担当チームのメンバーの日報のみ閲覧可能。既読マーク・コメントも可能です</li>
    <li><strong>複数チーム対応：</strong>1人が複数チームのリーダーを兼任する場合、全チームのメンバーの日報を閲覧できます</li>
  </ul>
</section>

<section class="cl-fix">
  <h3>バグ修正</h3>
  <ul>
    <li><strong>日報編集の「過去データから流用」ボタン：</strong>当日すでに日報を作成済みの場合、日報作成ページが編集ページにリダイレクトされますが、編集ページに流用ボタンがなかったため使用できない状態でした。編集ページにも同様の流用ボタンを追加しました</li>
  </ul>
</section>

<section class="cl-guide">
  <h3>日報権限チームの使い方</h3>
  <ol>
    <li>Admin メニューの「日報権限管理」から日報チームを新規作成する</li>
    <li>チーム名を入力し、日報マネージャー（Clerk / Coordinator / 校正Co）と閲覧対象メンバーを選択して保存</li>
    <li>設定されたリーダーのナビに「日報管理」ボタンが出現する</li>
    <li>「日報管理」から担当チームのメンバーの日報を閲覧・既読・コメントできる</li>
  </ol>
</section>
HTML,
        ],
        [
            'version'      => 'announce-1',
            'title'        => 'お知らせ通知：送信権限者全員が全履歴を閲覧・編集可能に',
            'released_at'  => '2026-06-02',
            'summary'      => 'これまでお知らせ通知の送信履歴は「自分が送った通知のみ」が表示されていました。今後は同じ会社で送信権限を持つユーザー（Clerk・Leader・SuperAdmin）であれば、誰が送った通知でも一覧で確認し、編集・送信・削除が行えます。Aさんが下書き保存 → 上長が確認して送信、といったワークフローが可能です。また SuperAdmin が会社コンテキストを切り替えることで、各会社の通知履歴を正しく確認できるよう修正しました。',
            'design_files' => [],
            'claude_notes' => '【主な変更】Clerk/AnnouncementController・Leader/AnnouncementController の index/show/edit/update/destroy/send を修正。①index: sender_id = 自分 フィルターを削除し、sender.company_id = 自社（SuperAdminはコンテキスト会社）でフィルタリングに変更。②show/edit/update/destroy/send: abort_if(sender_id != user->id) を authorizeForCompany() ヘルパーに置き換え（sender.company_id を確認）。③一覧に sender_name カラム追加（コントローラーで with sender:id,name を追加、map に sender_name 追記）。④Clerk/Leader Announcements/Index.vue に「送信者」カラム追加。⑤Clerk/Leader Announcements/Show.vue に送信者名表示追加。SuperAdmin が サンエー コンテキストでサンエーAdminの通知履歴（id=3,4,5）を確認できることを curl で確認済み。',
            'body'         => <<<'HTML'
<section class="cl-feature">
  <h3>改善内容</h3>
  <ul>
    <li><strong>共有送信履歴：</strong>同じ会社の送信権限者（Clerk・Leader）なら、誰が送ったお知らせでも一覧で確認・編集できるようになりました</li>
    <li><strong>送信者名の表示：</strong>一覧・詳細画面に「送信者」を表示するようになりました</li>
    <li><strong>SuperAdmin の会社別履歴：</strong>SuperAdmin が会社コンテキストを切り替えると、その会社の通知履歴が正しく表示されます（従来は自分の送信履歴しか見えなかった）</li>
    <li><strong>ワークフロー対応：</strong>Aさんが下書き保存 → 上長が内容を確認して送信、BさんがAさんの送信履歴を参考に次の通知を作成、といった運用が可能になりました</li>
  </ul>
</section>
HTML,
        ],
        [
            'version'      => 'announce-2',
            'title'        => 'お知らせ受信者一覧：会社フィルター・部署・担当・ソート機能追加',
            'released_at'  => '2026-06-02',
            'summary'      => 'お知らせ通知の詳細ページ（受信者一覧）を大幅に改善しました。複数会社に送信した場合は「全員 / サンエー印刷 / サン・ブレーン」のようなフィルターボタンが表示され、会社ごとの既読状況を把握できます。また、名前・会社・部署・担当・雇用形態・既読状況・既読日時の各カラムをクリックして並べ替えができるようになりました。',
            'design_files' => [],
            'claude_notes' => '【主な変更】Clerk/AnnouncementController・Leader/AnnouncementController の show メソッドに user.company・user.department を eager load 追加。recipients に company_id, company_name, department_name を追加。Clerk/Leader Announcements/Show.vue を全面改修：①会社フィルターボタン（isMultiCompany が true のときのみ表示）。②displayRecipients computed（フィルター＋ソート）。③filteredReadCount / readRate を displayRecipients ベースに変更（絞り込み中はバー横に「絞り込み中」表示）。④全カラムにクリックソート（toggleSort/sortIcon、↕/↑/↓）。⑤会社カラムは isMultiCompany 時のみ表示。⑥部署カラム追加。⑦データなし時の空行メッセージ。',
            'body'         => <<<'HTML'
<section class="cl-feature">
  <h3>改善内容</h3>
  <ul>
    <li><strong>会社フィルター：</strong>複数会社に送信した通知の詳細ページに「全員 / 会社名」のフィルターボタンが表示されます。クリックするとその会社の受信者だけに絞り込まれ、既読バーもリアルタイムで更新されます</li>
    <li><strong>部署・会社カラム追加：</strong>受信者一覧に「部署」を追加しました。複数会社への通知では「会社」カラムも表示されます</li>
    <li><strong>ソート機能：</strong>名前・会社・部署・担当・雇用形態・既読状況・既読日時のすべての列ヘッダーをクリックして昇順・降順に並べ替えできます（↕ 未ソート / ↑ 昇順 / ↓ 降順）</li>
  </ul>
</section>
HTML,
        ],
        [
            'version'      => 'team-room-1',
            'title'        => 'チームルーム機能追加',
            'released_at'  => '2026-06-03',
            'summary'      => 'チームルーム機能を追加しました。各チームの専用ページとして「概要・メンバー」「スケジュール」「プロジェクトボード」「会議記録」の4タブを提供します。スケジュールタブは月カレンダーと週間プランナー（週単位の予定一覧＋掲示板）を切り替えられます。プロジェクトボードはカンバン形式でカードを管理でき、カラムの折り畳みやドラッグ＆ドロップに対応しています。会議記録はキーワード・年月フィルターで絞り込み検索が可能です。',
            'design_files' => [],
            'claude_notes' => '【主な変更】①新規テーブル: team_week_posts（チーム週間掲示板）。②TeamWeekPost モデル追加。③TeamWeekPostController 追加（GET/POST/DELETE、year/week パラメータで投稿を管理）。④TeamBoardCardController に show/edit メソッドを追加（Inertia レンダリング）。⑤routes/web.php: team-rooms グループに week_posts 3ルート・board.cards.show/edit 2ルートを追加。⑥TeamWeekPlanner.vue 新規作成（左:週ナビ+日別イベント一覧、右:週の掲示板＋スレッド返信）。⑦TeamScheduleCalendar.vue: currentView===week-planner 時に TeamWeekPlanner を表示、月カレンダーは v-show で保持。⑧TeamBoard.vue: 列を flex:1 0 240px で横幅いっぱいに展開、折り畳み時は writing-mode:vertical-rl の縦バーに、カード追加ボタンをヘッダー右上に移動、一覧ビューに詳細/編集/削除ボタン追加。⑨Pages/TeamRoom/Board/CardShow.vue・CardEdit.vue 新規作成（mx-auto max-w-2xl の白カード）。⑩TeamMinutesList.vue: 編集ボタン追加、行クリックで詳細遷移、content フィールドも検索対象に追加（HTMLタグ除去後にキーワードマッチ）。⑪TeamMeetingMinuteController・TeamRoomController の minutes クエリに content カラムを追加。⑫AppLayout.vue: 重複していたチームルームリンクボタンを削除。⑬Index.vue: テーブル行クリックでshow遷移。⑭Show.vue: ボードタブ時のみ bg-white を除去してページ背景色と統一。',
            'body'         => <<<'HTML'
<section class="cl-feature">
  <h3>新機能：チームルーム</h3>
  <ul>
    <li><strong>概要・メンバータブ：</strong>チーム名・部署・説明とメンバー一覧（リーダー・サブリーダー・メンバー）を表示します</li>
    <li><strong>スケジュールタブ：</strong>月カレンダーでチームの予定を管理できます。週間プランナーに切り替えると、週単位の予定一覧と「週の掲示板」（スレッド形式の投稿・返信）を表示します</li>
    <li><strong>プロジェクトボード：</strong>カンバン形式のボードでタスクカードを管理します。カラムは折り畳み可能（縦バー表示）、カードはドラッグ＆ドロップで移動できます。カードをクリックすると詳細・編集ページに遷移します</li>
    <li><strong>会議記録タブ：</strong>議事録の一覧をキーワード・年月フィルター・日付ソートで絞り込めます。タイトル・作成者・本文を横断検索でき、行クリックで詳細に遷移します</li>
  </ul>
</section>
HTML,
        ],
        [
            'version'      => 'team-manage-1',
            'title'        => 'チーム管理機能の強化',
            'released_at'  => '2026-06-04',
            'summary'      => 'ユニットチームのリーダーに Coordinator・Clerk を選べるようにしました。チームごとにリーダーが日報を閲覧できるかどうかを設定できます（デフォルト：オフ）。副リーダー制度をユニットチームから廃止しました。チーム作成フォームを部署横断メンバー選択に刷新し、部署・会社の選択が不要になりました。Admin 画面に「特別チーム」管理を追加し、会社をまたいだメンバー構成のチームを作成できます。',
            'design_files' => [],
            'claude_notes' => '【主な変更】① Leader/UnitController・Leader/TeamController: リーダー候補クエリに coordinator・clerk を追加。② teams テーブルに can_read_diary（boolean, default true）カラム追加（マイグレーション: 2026_06_04_100001）。Team モデルの fillable・casts に追加。③ Leader/UnitController::store()・Leader/TeamController::update(): can_read_diary のバリデーションと保存を追加。④ DiaryInteractionController::buildPermittedUserIds(): can_read_diary=true のチームのみ日報閲覧対象に絞り込む。⑤ Leader/Teams/Create.vue・EditForUnits.vue: 日報閲覧チェックボックスを追加（デフォルト OFF）。⑥ チーム作成時デフォルトを can_read_diary=false に変更。⑦ ユニットチームの副リーダー廃止: Leader/UnitController::store() の team_sub_leaders 挿入ロジック削除、Leader/TeamController::index() のサブリーダー検索削除、Leader/TeamController::update() のサブリーダー sync 削除。Create.vue・EditForUnits.vue からサブリーダー UI を削除。さくら本番 DB の unit チームサブリーダーを削除（department チームは維持）。⑧ チーム作成フォーム刷新: UnitController::create() を部署横断対応に変更（auth_company_id・auth_department_id を渡す）、Create.vue を EditForUnits.vue と同様の絞り込みモーダル付き全社横断テーブルに刷新。⑨ Admin 特別チーム: Admin/SpecialTeamController 新規作成（team_type="special"）、routes/web.php に admin.special_teams.* 追加、Ziggy 再生成、AdminNavigationTabs.vue に「特別チーム」タブ追加、Admin/SpecialTeams/Index・Create・Edit.vue 新規作成。SuperAdmin は全会社からリーダーを選択可。',
            'body'         => <<<'HTML'
<section class="cl-feature">
  <h3>チーム管理機能の強化</h3>
  <ul>
    <li><strong>リーダー権限の拡張：</strong>ユニットチームのリーダー・副リーダーに Coordinator（進行）・Clerk（事務）の権限を持つユーザーも設定できるようになりました</li>
    <li><strong>日報閲覧設定：</strong>チームごとに「リーダーがメンバーの日報を閲覧できるか」を設定できます。チーム作成・編集フォームのチェックボックスで切り替えられます（デフォルト：オフ）</li>
    <li><strong>副リーダー制度の廃止：</strong>ユニットチームの副リーダー機能を廃止しました。部署チーム（情報出版・製版など）の副リーダーは従来通り機能します</li>
    <li><strong>チーム作成フォームの刷新：</strong>部署や会社を選ぶ手間がなくなりました。メンバーは自社の全部署から選択でき、絞り込みモーダルで部署・担当を指定して素早く絞り込めます</li>
    <li><strong>特別チーム（Admin 新機能）：</strong>Admin メニューに「特別チーム」を追加しました。会社をまたいだメンバーで構成するチームを作成できます。会社セレクト→部署セレクトで絞り込み、複数社のメンバーをまとめて選択できます。登録されたメンバーは通常のチームと同様にチームメニューから切り替えられます</li>
  </ul>
</section>
HTML,
        ],
        [
            'version'      => 'workload-group-1',
            'title'        => '作業項目設定のグループ・カスタム項目強化',
            'released_at'  => '2026-06-05',
            'summary'      => '作業項目設定のカスタム項目（job_field_options）でグループ名が保存されない不具合を修正しました。グループ機能を Difficulties にも追加し、「グループなしで追加」ボタンによりグループ不要な項目も登録できます。カスタム項目には「カスタム設計名」をスコープ別に保存する機能を追加し、セクションヘッダーに「カスタム項目：○○」と表示されます。Statuses セクションは他機能と連動しているため表示のみに変更しました。',
            'design_files' => [],
            'claude_notes' => '【主な変更】① job_field_options の group_key が保存されないバグを2層修正（Vue の v-if 条件除去 + Controller の validationRules に items.*.group 追加）。② workload_custom_field_configs テーブル新規作成（マイグレーション: 2026_06_05_100001）、WorkloadCustomFieldConfig モデル追加。カスタム設計名を company_id + department_id の UNIQUE キーで保存。③ difficulties テーブルに group_key カラム追加（マイグレーション: 2026_06_05_100002）。Difficulty モデルの fillable に group_key 追加。④ WorkloadSettingController: enrichWithDeptUsage を汎用化（$modelClass パラメータ追加）、difficulties・job_field_options にも部署使用状況を付与。index() で customFieldLabel・difficultiesGroupOrders を props に追加。store() で Difficulty の group_key 保存・difficulties の group_orders 保存に対応。⑤ Index.vue: GROUP_TYPES に difficulties 追加。READ_ONLY_TYPES に statuses 追加（編集不可バッジ表示）。カスタム設計名インライン編集バー（blur で確定）。グループヘッダーに紫/インディゴの帯。グループなし null キーのラベルを「グループなし」に統一。ヘッダーに「グループなしで追加」ボタン追加（group=null で addRow）。項目追加ボタンを「＋追加」に簡素化。difficultiesGroupOrders prop 追加。',
            'body'         => <<<'HTML'
<section class="cl-feature">
  <h3>作業項目設定の強化</h3>
  <ul>
    <li><strong>グループ名保存の不具合修正：</strong>カスタム項目（カスタム設計）のグループ名が保存されなかった不具合を修正しました</li>
    <li><strong>Difficulties にグループ機能追加：</strong>Difficulties にもグループを作成して項目を整理できるようになりました。「グループなしで追加」ボタンでグループに属さない項目も登録できます</li>
    <li><strong>カスタム設計名の保存：</strong>カスタム項目のセクション名（例：「カスタム項目：営業進捗」）を会社・部署スコープごとに保存できます。セクションヘッダーに設定した名前が表示されます</li>
    <li><strong>Statuses セクションの表示専用化：</strong>ジョブ依頼の進行状態と連動しているため、Statuses は閲覧のみとなりました（「他機能と連動・設定不可」バッジで明示）</li>
  </ul>
</section>
HTML,
        ],
        [
            'version'      => 'team-room-2',
            'title'        => 'チームルーム一覧に部署チーム・特別チームを追加',
            'released_at'  => '2026-06-06',
            'summary'      => 'チームルーム一覧ページ（team-rooms）に、部署チームと特別チームを表示するセクションを追加しました。部署チームは薄青の枠、特別チームは薄緑の枠で上部に表示されます。各セクション内でチームをドラッグ＆ドロップで並べ替えられ、順序は端末ごとに localStorage へ保存されます。',
            'design_files' => [],
            'claude_notes' => '【主な変更】① TeamRoomController::index(): team_type を unit のみから department/special/unit の3種に拡張。departmentTeams / specialTeams / unitTeams の3プロパティに分けて Inertia に返す。② TeamRoomController::assertMember(): team_type !== unit の 404 チェックを in_array([unit, department, special]) に変更し、部署・特別チームルームへのアクセスを許可。③ Pages/TeamRoom/Index.vue 全面刷新: 3セクション構成（部署=bg-blue-50/border-blue-200、特別=bg-green-50/border-green-200、一般=bg-white）。onMounted で localStorage の保存順序を適用。HTML5 drag-and-drop（dragstart/dragover/dragend）でセクション内並べ替えを実装し、dragend 時に localStorage へ保存。localStorage キー: team-rooms-order-dept / team-rooms-order-special / team-rooms-order-unit。各行左端に ⠿ グリップアイコン。一般チームセクションのヘッダー「一般チーム」は部署か特別チームが存在する場合のみ表示。',
            'body'         => <<<'HTML'
<section class="cl-feature">
  <h3>チームルーム一覧の強化</h3>
  <ul>
    <li><strong>部署チームを一覧に追加：</strong>自分が所属する部署チームを薄青の枠で一覧の最上部に表示します。チーム名をクリックしてチームルームに入れます</li>
    <li><strong>特別チームを一覧に追加：</strong>Admin が設定した特別チーム（会社横断チーム）は薄緑の枠で中段に表示されます</li>
    <li><strong>ドラッグ＆ドロップ並べ替え：</strong>各セクション内でチームをドラッグして順序を変更できます。設定した順序は端末ごとに保存され、ページを再読み込みしても維持されます</li>
  </ul>
</section>
HTML,
        ],
        [
            'version'      => 'team-room-3',
            'title'        => 'チームルーム：係・当番表タブを追加',
            'released_at'  => '2026-06-06',
            'summary'      => 'チームルームに「係・当番」タブを追加しました。CSV または Excel ファイルをアップロードすると HTML 表に変換してプレビュー表示し、確定すると表が保存されます。保存した表はタイトル行の「再読み込み」「削除」ボタンで管理できます。CSV は Shift-JIS・BOM 付きに対応し、Excel は PhpSpreadsheet で読み込みます。',
            'design_files' => [],
            'claude_notes' => '【主な変更】① マイグレーション 2026_06_06_100001: team_duty_tables テーブル（id/team_id/user_id/title/description/html_content）を新規作成。② TeamDutyTable モデル追加。③ TeamDutyTableController: create（フォーム表示）/ preview（ファイル→HTML変換・プレビュー返却）/ store（html_content を DB 保存）/ destroy。NormalizesCsvEncoding トレイトで CSV Shift-JIS 対応、PhpSpreadsheet で xlsx/xls/ods 対応。④ routes/web.php: duty-tables.create / preview / store / destroy の4ルートを team-rooms グループに追加。Ziggy 再生成。⑤ TeamRoomController::show() に dutyTables prop 追加。⑥ Pages/TeamRoom/DutyTable/Create.vue: タイトル・説明・ファイル入力 → プレビューフォーム送信 → HTML プレビュー表示 → 確定保存の2ステップ。失敗時は赤枠エラーメッセージ。⑦ Pages/TeamRoom/Show.vue: 「係・当番」タブ追加、duty-table-content CSS クラスでテーブルスタイル。',
            'body'         => <<<'HTML'
<section class="cl-feature">
  <h3>チームルーム：係・当番表タブ</h3>
  <ul>
    <li><strong>ファイルから表を登録：</strong>CSV（Shift-JIS 可）または Excel（.xlsx/.xls）をアップロードすると、内容を HTML 表に変換してプレビュー表示します。表示を確認してから「確定して保存」で登録できます</li>
    <li><strong>再読み込み・削除：</strong>登録済みの表はタイトル行右端の「再読み込み」ボタンで新しいファイルに差し替え、「削除」ボタンで削除できます</li>
    <li><strong>ファイル読み込みエラー時：</strong>ファイルが壊れている場合やサポート外の形式の場合は赤いエラーメッセージで通知し、ファイルを修正して再アップロードするよう案内します</li>
  </ul>
</section>
HTML,
        ],
        [
            'version'      => 'team-room-4',
            'title'        => 'チームルーム：メモ・連絡タブを追加',
            'released_at'  => '2026-06-06',
            'summary'      => 'チームルームに「メモ・連絡」タブを追加しました。週間プランナーの掲示板と同様のスレッド形式で投稿・返信ができます。自分の投稿はインライン編集と削除が可能です。投稿者名・返信者名・内容のキーワード検索と年月フィルターを搭載し、新しい投稿が上に表示されます。',
            'design_files' => [],
            'claude_notes' => '【主な変更】① マイグレーション 2026_06_06_100002: team_memo_posts テーブル（id/team_id/user_id/body/parent_id）を新規作成。parent_id は自己参照 FK（cascade）。② TeamMemoPost モデル追加。③ TeamMemoPostController: index（JSON）/ store（JSON）/ update（JSON、投稿者 or SuperAdmin のみ）/ destroy（JSON、同）。④ routes/web.php: memo-posts.index / store / update / destroy の4ルートを team-rooms グループに追加。Ziggy 再生成。⑤ Components/TeamRoom/TeamMemoBoard.vue 新規作成: axios で JSON API を呼び出し。スレッドツリー表示（parent_id を辿って深さ計算）。ルート投稿は新しい順、返信は古い順。キーワード検索（スレッド単位ヒット）・年月フィルター・キーワードハイライト（mark タグ）。インライン編集・削除（確認ダイアログ）。Enter 送信（isComposing チェックで IME 対応、Shift+Enter で改行）。⑥ Pages/TeamRoom/Show.vue: 「メモ・連絡」タブ追加、TeamMemoBoard コンポーネント組み込み。',
            'body'         => <<<'HTML'
<section class="cl-feature">
  <h3>チームルーム：メモ・連絡タブ</h3>
  <ul>
    <li><strong>掲示板形式の投稿：</strong>チームメンバーが自由にメモや連絡事項を投稿できます。投稿への返信もスレッド形式で表示されます（↳ でインデント）</li>
    <li><strong>編集・削除：</strong>自分の投稿はインラインで編集でき、削除ボタンから削除できます（返信も連動削除）</li>
    <li><strong>検索・絞り込み：</strong>投稿者名・返信者名・本文のキーワード検索と年月フィルターで過去の投稿を探せます。キーワードは黄色くハイライト表示されます</li>
    <li><strong>Enter で送信：</strong>日本語入力（IME）の変換確定後に Enter を押すと投稿できます。Shift+Enter で改行できます</li>
  </ul>
</section>
HTML,
        ],
        // スケジュール（予定表）機能 - 2026-06-22
        [
            'version'      => 'schedule-1',
            'title'        => '予定表（スケジュール）機能：会議室予約・他人の予定オーバーレイ・使い方ガイド',
            'released_at'  => '2026-06-22',
            'summary'      => '会議・打合せ・イベントを会社全体で共有できる「予定表（スケジュール）」機能をリリースしました。会議室予約（テスト機能）と他のメンバーの予定をオーバーレイ表示する機能を搭載。月・週・日の3ビューに対応し、日ビューでは自分と指定した人の予定を横並びで確認できます。使い方ガイドページも追加しました。',
            'design_files' => ['SCHED_PLAN1.md'],
            'claude_notes' => '【主な機能】① 予定表ページ（/schedule）: Outlook 類似の月・週・日ビュー共有カレンダー。② 会議室予約（テスト機能）: 田端会議室・多目的ルーム・応接室を時間帯ドラッグで予約。③ オーバーレイパネル: 他ユーザー・部署の予定を日ビューに追加表示し空き時間を確認。④ 朝の予定通知（毎朝8時）。⑤ RoomReservationModal.vue: linkEventId を exclude_event_id として渡すことで既存イベント紐付け時の自己競合誤検知を修正。⑥ Guide/Schedule.vue 新規作成: 予定表 vs カレンダーの違い・会議室予約手順・オーバーレイ手順を解説。⑦ Schedule/Index.vue: タイトルバナー右端に使い方ガイドボタンを追加。',
            'body'         => <<<'HTML'
<section class="cl-feature">
  <h3>予定表（スケジュール）機能</h3>
  <ul>
    <li><strong>共有カレンダー：</strong>会議・打合せ・外出・来社などを会社全体で共有できるカレンダーです。ユーザー個人のカレンダー（作業記録）とは別に、チームのスケジュールを管理できます</li>
    <li><strong>月・週・日ビュー：</strong>3つのビューを切り替えて確認できます。日ビューでは複数人の予定を横並びで表示できます</li>
    <li><strong>会議室予約（テスト機能）：</strong>田端会議室・多目的ルーム・応接室を予定表から予約できます。終了時刻と次の予約開始時刻が同じ連続予約もOKです。正式な予約は引き続き Outlook で行ってください</li>
    <li><strong>他の人の予定を表示（オーバーレイ）：</strong>同僚の予定を日ビューに追加表示して、いつ空いているか確認できます。設定は次回ログイン時も保持されます</li>
    <li><strong>朝の予定通知：</strong>当日に予定がある方に毎朝8時ごろ通知が届きます</li>
    <li><strong>使い方ガイド：</strong>予定表ページ右上の「📖 使い方ガイド」ボタン、またはガイド一覧から確認できます</li>
  </ul>
</section>
HTML,
        ],
        [
            'version'      => 'proof-reservation-1',
            'title'        => '案件に紐づく校正予約と校正カレンダー連携',
            'released_at'  => '2026-06-30',
            'summary'      => '案件一覧から校正予約を送れるようになりました。依頼予定と締め切りは日時または自由記述で入力でき、確定日時の予約は校正カレンダーに期間予定として登録できます。予約モーダルから送信済み予約を確認でき、タイトルまたは日程が重複する場合は送信前に確認します。予約受付・校正中・完了・削除の状態管理と、proof-admin一覧の日付順切替にも対応しました。',
            'design_files' => ['PROOFRESV_PLAN1.md'],
            'claude_notes' => '【DB】proof_reservations を新設し、通常の proof_requests と予約を分離。status（reserved/in_progress/completed/deleted）を追加。【案件一覧】ステータス右に校正予約ボタン、専用モーダルで依頼予定・締め切りを日時/テキスト切替入力。モーダル右上の送信予約一覧で案件別履歴を表示。【重複確認】同一案件でタイトル一致、または依頼予定日・締切日の両方一致（時間無視）をAPIとstoreの両方で判定し、confirm承認後のみ送信。【proof-admin】校正予約一覧タブ、ジョブ管理相当の検索・年月絞り込み・グループ表示、予約詳細を追加。詳細から予約受付・校正中・完了・削除へ変更可能。一覧の完了非表示はデフォルトONでlocalStorage保存。受信箱・予約一覧・ジョブ管理にcreated_at基準の新しい順/古い順を追加（デフォルトdesc）。【カレンダー】詳細の登録ボタンで calendar_registered_at を記録し、月表示へ requested_at〜deadline_at の期間ストリップを表示。自由記述を含む予約は登録不可。deletedはカレンダーから除外。【修正】ProofReservationのAttributeヘルパーがEloquentのアクセサとして誤認識される500エラーを修正。ProofDispatcherControllerが存在しないorderedスコープを呼んで単発派遣管理が500になる問題を、ProofDispatcher::scopeOrdered追加で修正。旧実装の proof_requests 流用、通常 ProofRequestModal 流用、不要な予約CRUDを撤去。',
            'body'         => <<<'HTML'
<section class="cl-feature">
  <h3>校正予約</h3>
  <ul>
    <li><strong>案件から予約：</strong>案件一覧の「校正予約」ボタンから、案件に紐づく校正予約を送れます</li>
    <li><strong>未確定日程にも対応：</strong>依頼予定と締め切りは、カレンダーと時刻だけでなく自由なテキストでも入力できます</li>
    <li><strong>送信予約一覧・重複確認：</strong>予約モーダルから同じ案件の送信履歴を確認できます。同じタイトル、または同じ依頼予定日・締め切り日の予約がある場合は送信前に確認します</li>
    <li><strong>ステータス管理：</strong>予約詳細から「予約受付」「校正中」「完了」「削除」を切り替えられます。予約一覧では完了した予約を非表示にできます</li>
    <li><strong>日付順の切替：</strong>校正依頼受信・校正予約一覧・ジョブ管理を、依頼日の新しい順または古い順で表示できます</li>
    <li><strong>予約一覧・詳細：</strong>proof-admin の「校正予約一覧」で検索・年月絞り込みを行い、予約内容を詳細画面で確認できます</li>
    <li><strong>校正カレンダー連携：</strong>開始・終了が確定している予約は、校正カレンダーへ1本の期間予定として登録できます</li>
  </ul>
</section>
HTML,
        ],
        [
            'version'      => 'schedule-overlay-fix-1',
            'title'        => 'カレンダー・予定表：他人の予定オーバーレイの表示範囲を修正',
            'released_at'  => '2026-07-07',
            'summary'      => '個人カレンダーおよび予定表の月・週表示で、オーバーレイ登録した他人の予定が自分の予定と混在して表示され、招待されていない予定まで確定済みの予定のように見えてしまう不具合を修正しました。オーバーレイ表示は予定表の日表示（担当者ごとのカラム表示）でのみ有効になります。',
            'design_files' => [],
            'claude_notes' => '【原因】ScheduleEventController::range() が生成する overlayEvents（個人/会社/部署オーバーレイおよびオーバーレイ対象ユーザーの参加会議）が、is_own=false 以外の区別タグを持たないまま events 配列に混在していたため、カラム分割のない MonthView/WeekView や /calendar（UserCalendar.vue）でも他人の予定がそのまま表示されていた。【修正】① ScheduleEventController::range(): overlayEvents の全エントリに is_overlay=true フラグを付与（overlay_user_id タグはDay view用に維持）。② ScheduleCalendar.vue: MonthView/WeekView へ渡す events から is_overlay を除外した nonOverlayEvents を新設し使用。DayView へは従来通り全件渡し、担当者別カラム表示は維持。③ UserCalendar.vue: companyEvents から is_overlay を除外してから合成し、/calendar の月/週/日すべてでオーバーレイを非表示化。DBマイグレーションなし。',
            'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>予定表で他人の予定をオーバーレイ表示する設定をしていると、個人カレンダー（/calendar）や予定表の月・週表示にもその人の予定が自分の予定と混ざって表示されてしまい、実際には招待されていない予定なのに「承認・辞退できない確定済みの予定」のように見えてしまう問題がありました。</p>
</section>

<section class="cl-fix">
  <h3>修正内容</h3>
  <ul>
    <li>個人カレンダー（/calendar）の月・週・日表示から、他人のオーバーレイ予定を非表示にしました</li>
    <li>予定表（/schedule）の月・週表示からも、他人のオーバーレイ予定を非表示にしました</li>
    <li>予定表の日表示のみ、これまで通りオーバーレイ登録した人の予定を専用カラムで確認できます</li>
  </ul>
</section>
HTML,
        ],
        [
            'version'      => 'schedule-conflict-block-fix-1',
            'title'        => '予定表：参加者の予定重複で保存できない不具合を修正',
            'released_at'  => '2026-07-07',
            'summary'      => '予定表で会議・打ち合わせや会議室予約を作成する際、招待した参加者に既存の予定がある場合、これまでは警告が出た上で保存自体ができなくなっていました。使い方ガイドの案内どおり、警告バナーは表示しつつ保存できるように修正しました。',
            'design_files' => [],
            'claude_notes' => '【原因】resources/js/Components/Schedule/EventModal.vue と RoomReservationModal.vue の submit() が、schedule.events.conflicts の結果(conflictWarnings)が1件でもあると早期returnしてPOST自体を送信せず、保存ボタンも disabled にしていた。一方、自分自身のジョブ予定との重複は confirmOverlap() の confirm() ダイアログで警告するだけで保存継続でき、使い方ガイド(Guide/Schedule.vue)にも「参加者に競合予定がある場合は黄色いバナーで通知されます」→保存ボタンを押す、と案内されており、参加者側だけ保存不可になっていたのは実装上の不整合だった。【修正】EventModal.vue submit()・RoomReservationModal.vue submit() から conflictWarnings によるハードブロック(早期returnと保存ボタンのdisabled条件)を削除。警告バナー自体の表示は維持。RoomReservationModal.vue で不要になった showToast/useToasts のimportも削除。DBマイグレーションなし。',
            'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>予定表で会議・打ち合わせや会議室予約を作成する際、招待した参加者に既存の予定が少しでも重なっていると、警告バナーが出るだけでなく保存ボタンが押せなくなり、予定を登録できないという問題がありました。</p>
</section>

<section class="cl-fix">
  <h3>修正内容</h3>
  <ul>
    <li>参加者の予定が重複している場合、これまで通り黄色い警告バナーは表示されます</li>
    <li>警告が出ていても保存ボタンで登録できるようになりました（内容を確認のうえ登録してください）</li>
  </ul>
</section>
HTML,
        ],
        [
            'version'      => 'job-assign-dup-notify-1',
            'title'        => 'ジョブ依頼：保存の連打で割当・通知が重複する不具合を修正',
            'released_at'  => '2026-07-07',
            'summary'      => 'ジョブ割り当てフォームで保存ボタンを連打するなど短時間に二重送信された場合、同じ内容のジョブ割り当てが2件作成され、依頼通知も2重に届いてしまう不具合を修正しました。',
            'design_files' => [],
            'claude_notes' => '【原因】resources/js/Pages/Coordinator/ProjectJobs/JobAssign/AssignmentForm.vue の save() に多重実行を防ぐガードが無く、保存ボタンは :disabled="saving" のみで save() 冒頭に再入防止チェックが無かった。二重に呼ばれると Coordinator/ProjectJobAssignmentsController::store() が assignments 配列を無条件に create() するため、同一内容の project_job_assignments が2件作成され、notifyNewJob() も2回呼ばれて通知が重複していた（実データで確認: 同一秒に作成された重複レコード2組を本番DBで特定）。【修正】① AssignmentForm.vue save() の冒頭に `if (saving.value) return;` を追加しフロント側で二重実行を防止。② ProjectJobAssignmentsController::store() に保険的ガードを追加: 同一 project_job_id・user_id・sender_id・title の割当が直近15秒以内に作成済みなら二重送信とみなして作成・通知をスキップ。DBマイグレーションなし。',
            'body'         => <<<'HTML'
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>ジョブ割り当てフォームで保存ボタンを連打するなどして短時間に二重送信されると、同じジョブ割り当てが2件登録され、依頼された側に同じ内容の通知が2件届いてしまうことがありました。</p>
</section>

<section class="cl-fix">
  <h3>修正内容</h3>
  <ul>
    <li>保存処理が完了するまでは、連打しても二重に送信されないようにしました</li>
    <li>万一二重送信された場合も、サーバー側で同一内容の直近の重複登録を検知してスキップするようにしました</li>
  </ul>
</section>
HTML,
        ],
        [
            'version'      => 'operator-calendar-enhance-1',
            'title'        => 'オペレーターカレンダー：色担当一覧表示・メンバー追加の部署制限・並べ替え機能を追加',
            'released_at'  => '2026-07-09',
            'summary'      => 'オペレーターカレンダーのカレンダー上部に、製版伝票ボードと同じ「色○＋名前」の担当色一覧を表示するようにしました。また「＋メンバー」の追加候補を自分の部署のユーザーのみに限定し、部署内の並び順で表示するようにしました（SuperAdminは会社切替の状態に応じて絞り込みを調整します）。カレンダーのオペレーター行は上下ボタンで並べ替えでき、順序はデータベースに保存されます。',
            'design_files' => [],
            'claude_notes' => 'OperatorCalendar.vue: ツールバーの「色設定」トグルボタンを削除し、タイムライン直上に製版ボード Board.vue と同じ見た目（色丸＋苗字＋右下「担当色変更」リンク）のセクションを追加。苗字抽出は Board.vue の colorUserName() と同じ split(/[\s　]+/)[0] ロジックを colorUserFamilyName() として実装。' . "\n\n" . 'OperatorCalendarController.php: getCandidateUsers()（＋メンバー候補一覧）を自分の部署のユーザーのみ・User::ordered()（sort_order→name順）に限定。SuperAdmin は既存の ResolvesContextCompany トレイト（superadmin_context.company_id セッション）を使い、会社切替コンテキストが自社と一致する場合のみ自分の部署で絞り込み、他社選択中／会社未選択のグローバルモードでは部署では絞らず選択中の会社全体（またはグローバルなら無制限）を候補にする。絞り込みロジックは candidateScope() として共通化。' . "\n\n" . 'メンバー並べ替え: operator_calendar_members.sort_order（既存カラム、追加マイグレーション不要）を使用。PUT /operator-calendar/members/reorder（reorderMembers()）を新設し、「並べ替え」トグルON時に各メンバー行へ表示される▲▼ボタンでlocalMembers配列を入れ替え→即APIで永続化。' . "\n\n" . '実データ（company_id=2の情報出版/製版部署）で再現テストを実施: leader/adminユーザーでの部署絞り込みは正しく機能することをtinker経由の直接呼び出しで確認。ユーザー報告の「製版メンバーが見える」件は、修正前に開いていたブラウザタブがInertia SPAのため props 未更新のまま残っていたことが原因と判明（ページ再読み込みで解消）。' . "\n\n" . 'codexレビュー（--uncommitted）で1件（P2）検出し修正: getCandidateUsers() の部署・会社絞り込みは「＋メンバー」候補一覧のUI表示のみを制限しており、追加API storeMember() 自体には範囲チェックが無く、直接APIリクエストで範囲外（他部署・他社）のユーザーを追加できてしまう状態だった。candidateScope() を storeMember() 側でも呼び出し、対象ユーザーが会社・部署スコープ外の場合は403を返すよう修正。tinkerで範囲外ユーザー（403でブロック）・範囲内ユーザー（正常追加）の両方を実データで確認済み。',
            'body'         => <<<'HTML'
<section class="cl-feature">
  <h3>追加した機能</h3>
  <ul>
    <li>カレンダー上部に、製版伝票ボードのカードと同じ「色○＋担当者名」の一覧を表示するようにした</li>
    <li>一覧の右下の「担当色変更」リンクから、色ごとの担当者設定を開けるようにした（従来のツールバーのボタンは統合して削除）</li>
    <li>「＋メンバー」の追加候補を自分の部署のユーザーのみに限定し、部署内の並び順で表示するようにした</li>
    <li>「並べ替え」ボタンを追加。ONにするとオペレーター行に▲▼ボタンが表示され、クリックで表示順を入れ替えられる（並べ替えた順序は自動的に保存され、次回アクセス時も維持される）</li>
  </ul>
</section>

<section class="cl-fix">
  <h3>修正・改善内容</h3>
  <ul>
    <li>「＋メンバー」追加APIについても、候補一覧と同じ部署・会社の範囲チェックを行うようにし、一覧に出ない範囲外のユーザーを追加できないようにした</li>
  </ul>
</section>
HTML,
        ],
        ];

        foreach ($entries as $entry) {
            Changelog::updateOrCreate(
                ['version' => $entry['version']],
                $entry
            );
        }
    }
}
