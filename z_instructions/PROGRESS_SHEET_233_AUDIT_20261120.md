# 進行表233 完了色・進捗不一致 調査
2026-11-20 JST。利用者対象: /members/coordinator/progress-sheets/233?back_tab=progress。オペレーター完了後も緑にならず進捗が増えず、ピンク表示から校正との関係を疑うとの報告。指示は現状調査であり修正/データ補正/完了操作/配備は未承認。
参照: AGENTS、最新保存4af9b18b9のJOB_EVALUATION_CONTROL1と関連手動完了/進行表/校正コード、archived/PROGRESS_SHEET_V2_DESIGN.md・PROGRESS_LINK_PLAN.md、ProgressSheetController/ProgressCell.vue/ProgressTable.vue/Show.vue/ProgressLinkService/ProgressCellモデル/WorkJobLegacyCompletion。資料はz_instructions（利用者表記z_instruction）で確認。共有主作業985e6fffbのClaude/ziggy等は未編集。
Git fetch origin mainでb1d57e1dd取得。進行表Controller/表示3部品は4af保存版と差分なし。ただしorigin/mainにはWorkJobLegacyCompletionが無く、本番と同版とはみなさない。本番最新版と対象233の実データはSSH前確認待ち。
コードからの候補（未確定）: workerセル本文はcompleted_atまたはassignment_completedで完了表示する一方、背景と一部進捗はcompleted_atだけ。表の行/グループ/シート全体の判定も統一されていない。ProgressLinkServiceのリンク進捗はセルcompleted_atを参照。校正セルは校正割当/校正完了を別に扱うので、単にピンクだから校正が原因とは確定しない。どの列・セルがどのジョブを参照し、手動/自動/続きによる完了情報がどこまで反映されたかを照合する必要がある。
読み取りスクリプト /tmp/sunbwork-progress233-read-20261120.sh、SHA256 bd00830fd3c6cf88eb581105250756a94952a9b20d032fc234aa97ed93eb5b38。正確SSHコマンドを利用者へ提示済み・承認待ち。対象233の行/列/セル、関連割当/新形式記録/校正依頼/リンク予定のIDと完了状態だけ。ジョブ本文・添付・売上DBは読まない。Controller実行/再計算/データ変更/通知はしない。結果はローカル/tmpの限定ログへ保存して照合する。
次の1手: 読み取り承認後に本番HEAD/対象データを取得し、その版のソースを使って原因と影響範囲を報告する。修正する場合はその後別に設計確認。深夜自動完了は利用者の2条件成功で完了、TODO繰り返し4条件は本件優先で保留を維持。

## 2026-11-20 承認済み読み取りと再現結果（調査完了）
利用者が正確SSHコマンド/上記SHA256を承認。SSH終了0、本番HEAD 0c20cba6bfeafcf2833616147ef01bbb2b22559f/main、既知未追跡6件。業務データの書換/Controller呼出/通知/再計算は行っていない。対象版のProgressCell/ProgressTable/Show/Controllerは保存4af9b18b9と一致し、その版を基準に分析した。
進行表233→案件416。行は「6年」の見出し1行と国語/算数/社会/理科の4行。列は初校/二校/確認校/念校、それぞれ組版workerと校正proof_v2。初校組版に割当3件だけが紐づき、全てジョブcompleted=1。
- 理科: cell1437/assignment9982、ジョブ完了・セルcompleted_at NULL。ジョブupdated_at 2026-11-19 09:14:08。この時刻だけから手動完了経路は断定しない。
- 算数: cell1438/assignment9986、ジョブ完了・セルcompleted_at NULL。ジョブupdated_at 2026-11-19 00:05:01。自動完了の時刻と整合するが実行ログは未読取。
- 社会: cell1439/assignment9987、両方完了。セル完了日時2026-11-18 17:59:38。
- 国語cell1435は割当なし・未完了。
校正列のセル/校正割当ID、および今回取得したセルに紐づくproof_requestはなし。progress_sheet_id=233のproject_job_itemsリンク、schedule_idもなし。案件全体の未紐付け校正依頼まで「存在しない」とは断定しない。セルの手動cell_deadlineは今回未取得で、実際の期限警告色の優先締切は未照合。
確定した不一致: worker本文はcompleted_at OR assignment_completed、背景/期限色/一部集計はcompleted_atのみ。ジョブの完了を認識した本文と、セル完了を拾えない背景/カウントが同居する。薄赤(bg-red-50)は期限超過の警告としても用いるため、ピンク表示だけで校正待ちとはいえない。
本番旧AutoCompleteMyJobsは自己割当PJAをcompleted=true/status3へ更新しイベントへprefixを付けるが、progress_cells.completed_atへ同期しない。先行する新MyJobの対象追加修正前からの旧経路であり、今回新形式向け修正が新たに校正を壊したという証拠はない。9986の時刻との一致はこの経路が原因候補であることを支持する。9982の具体的更新操作は未読取。
実ソースのVue計算を取得データのDTOと同じ完了フラグでローカル再現（/tmp/sunbwork-progress233-analysis/reproduce.cjs、終了0）:
- ProgressTable rowCompletionMap: 算数/理科0/8、社会1/8（見出し/国語も0/8）。
- ProgressTable groupProgressMap: 初校1/10、残り各0/10。
- Show sheetCompletion: 3/40（表示丸め8%）。
画面のスクリーン確認を実施したわけではなく、実データ+本番版の計算式による再現。見出し行も分母に含む現行挙動は別論点として記録し、無断で変更しない。

## 修正候補（提案・未実装）
1. 型ごとの完了判定を共通化し、workerはセルまたは紐付ジョブの完了を背景/期限表示/行・工程・全体で同じように認識。校正の判定と混同しない。
2. 深夜自動完了と既存の完了経路からセルへ反映する仕組みを確認/整備し、必要なリンク進捗も一貫して更新する。
3. 既存2セルの表示は読取時fallback等で正しく認識できるようにし、DB補正する場合は対象固定・再実行保護を別に設計/承認する。校正を無条件完了にしない。
次の1手: 利用者へ調査結果と上記修正候補を報告。指示は調査のみなのでコード/本番データの修正は未着手。修正を依頼されたら設計・管理・再開資料で範囲を具体化して確認する。TODO繰り返し4条件は保留。

2026-11-20利用者の追加決定による修正方針置換: 「自動完了→工程確定/通知」は採用しない。調査時の候補1/2（汎用job completed fallback/深夜セル同期）は置換され、セルの明示完了日時を基準に表示/集計を統一し、本人が完了済み作業から工程確定できるようにする。詳細はPROGRESS_STAGE_CONFIRM_PLAN1/MANAGER1。既存2件は手動だったと推測して緑へ補正せず、本人に確定してもらう案を確認する。
