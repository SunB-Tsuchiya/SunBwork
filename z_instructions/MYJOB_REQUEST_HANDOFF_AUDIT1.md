# 依頼からマイジョブ登録する入口別の引継ぎ調査

更新: 2026-11-09 JST。調査のみ、コード・DB・配備の変更なし。

## 根拠と利用者判断

利用者から、依頼詳細→マイジョブ登録ではクライアントや案件固定サイズ等が出るが、カレンダー→簡易作成→受けた依頼から登録では一部が空になるとの報告と比較画像。確定要望は『旧形式への移動は可。入口が変わっても既存の初期値・案件固定・元依頼とのつながりを踏襲するよう調査』。まだ具体的な修正方式の承認とは扱わない。
調査基準は保存5e93d9337。最後の本番配備証拠5ef950b77との差分は管理資料5件のみで、今回参照する実装は同じ。現在の本番を追加SSH/DBで確認したわけではない。origin/mainや共有colayout-deployは別の履歴・他Claude変更を持つため、この作業の最新版の代用にしない。
旧/tmp消失のため、保存5e93からfeature/myjob-request-handoff-audit-20261109、/tmp/sunbwork-request-handoff-audit-20261109を作成。共有worktree/CLAUDE.mdは未編集。根拠は本セッション発言・添付と保存コード、過去会話を新規取得したものではない。

## 入口と差分

| 入口 | 実装経路 | 引継ぎ |
| --- | --- | --- |
| 依頼一覧→詳細→マイジョブとして登録（通常依頼） | JobBox/Show.myJobBoxHref→user.project_jobs.assignments.create→ProjectJobAssignmentUserController.create→MyJobBox/Create_user→共通AssignmentForm | source_job_assignment_idでサーバが元依頼・projectJob.client・作業詳細を読込。projectJobと豊富なprefillを渡す。 |
| カレンダー→簡易作成→受けた依頼から登録 | QuickWorkEntryModal.openRequests→events.create_job?open=request+date/時刻→Events/Create_Job.fetchPendingRequests/applyRequestJob→共通AssignmentForm | pendingRequestsは一覧表示向け。選択時にproject_job_id/title_suffix/desired_end_date/supersedes_assignment_idの4項目だけをセット。projectJobは更新しない。 |
| 旧MyJobBox/Create_user内の依頼選択 | 同pendingRequests→Create_user.applyRequestJob | 同じ4項目だけで、同様の引継ぎ不足がある。 |
| 従来MyJobCreateModal内の依頼選択 | onSourceSelected(kind=request)→共通AssignmentForm | _client_idと候補補完はあるが、依頼詳細一式を取得せず、サイズ/種別等は不足する。 |
| 進行表由来の依頼詳細 | JobBox/Showからevents.create_jobにsource_job_assignment_id | EventController.createJobが別のprefillとセル参照を作る。通常依頼詳細と一律に置換せず専用関係を保存する。 |

## 初期値・固定表示の相違

- 両画面の入力実体は同じresources/js/Pages/Coordinator/ProjectJobs/JobAssign/AssignmentForm.vue。違いはフォームへ渡す案件オブジェクト・初期値・propsにある。
- クライアント: pendingRequestsはclient_id/client_nameも返すが、Events/Create_JobとCreate_userのapplyRequestJobはそれを_client_idへ渡さない。共通normalizeAssignmentも一般のproject_job_idだけからclientを自動照合する実装ではないため、空の選択になる。
- サイズと『案件で固定』: 共通フォームはprops.projectJob.size_idを固定判定・未指定時補完に使う。通常依頼詳細はprojectJobを渡すが、簡易から依頼を選ぶ時はprojectJobを選択案件に更新しないため、サイズ候補/固定表示が揃わない。ユーザーの『版型・サイズ』に対応する独立した複数項目とまでは断定しない。調査したProjectJobで直接確認できる既定列はsize_idとpage_count。
- 種別/工程/難易度/見積/概要: 通常依頼詳細は元依頼の値を渡す。簡易経由の一覧APIと4項目代入では取得・引継ぎされない。
- ファイル情報/数量/単位: 通常依頼詳細はfile_infoを引継ぎ、合計ページ・ファイル数、元amounts、案件page_countのfallbackを実装。簡易経由には同等処理がない。ファイル本体共有を追加する話ではない。
- ステータス: MyJobBox/Create_userはhide-statusとdefault-status-id=1、Events/Create_Jobはin_progressのdefaultを渡す。スクリーンショットの『依頼』『進行中』の差と整合する。ただしid=1の名称を全環境で同じと断定せず、勝手な統一はしない。
- 作業日時: 簡易経由はdate/startHour/startMinute/endHour/endMinuteをprefillEventへ渡す既存経路がある。依頼締切と実際に作業する日時を分け、旧画面へ寄せる修正でもドラッグした作業日時を保持する必要がある。
- 元依頼の関連: 簡易経由でもsupersedes_assignment_idは渡っており、全ての関係が消えているわけではない。source_assignment_idは続きであり代用しない。

保存側User/ProjectJobAssignmentController.storeはsize_id等をフォーム値又はnull、file_infoも未指定ならnullで保存する。元依頼から自動的に全項目を再補完する処理はない。したがって見た目のplaceholderだけでなく、新たに作られる自己割当の詳細が欠ける可能性がある。既存の案件自体のclient等が消されたと断定はしない。

## 関連経路の注意（今回未修正）

JobBox/Show.myJobBoxHrefはquery文字列を作った後に進行表row/col/sheetをparamsへ追加しており、その後のqueryへ追加分が反映されない。サーバは元依頼IDからセルを再照合する処理もあるため、実業務のリンク欠落まで確定せず、専用ケースの検証候補に残す。単純に全入口を既存URLへ移すだけで完全同等になるとはしない。

## 修正方針の提案（未実装）

『元依頼IDでサーバが認可して読み出す共通の初期値』を正本にする。依頼一覧APIの表示用4項目から個別にフォームを組み立てない。既存の通常依頼詳細の補完と固定表示を共通化し、簡易/旧作成内の依頼選択/従来モーダルからも使う。最終入力は旧形式のままでよい。画面だけを変え、値の受渡しを別実装にする対応は避ける。

1. 本人宛・会社/部署・同案件・未登録/更新状態をサーバで照合し、既存候補外の直リンクや登録競合も検証。
2. 案件client/size、元依頼の概要・種別・工程・難易度・見積・数量単位・file_info、必要な候補と固定表示の情報を一緒に渡す。明示値・案件固定・ファイル推定が矛盾する場合の優先順位は既存挙動を調査して明文化し、勝手に上書きしない。
3. 実作業日とドラッグ時刻は依頼の締切とは別に保持し、supersedes/進行セル/校正coordinator等の参照を区別。
4. 選択時には保存しない。送信時も最新の元依頼を検証。戻る・キャンセル・連続選択・遅延応答で別案件の初期値を残さない。
5. 元依頼/案件の同じ合成データで各入口の実Vue初期値・固定表示・保存payload・サーバ保存値を比較する。サイズ未設定、個別依頼値、ファイル数量、候補外だが本人宛案件、進行表/管理表/校正、ドラッグ時刻、他人/別会社/重複登録を代表例にする。

影響は共通prefillサービス、create/pending/選択用サーバ経路、各Vueの依頼選択、保存時検証、合成検証と見込む。実装前に具体的なPLAN/MANAGER/PROMPTと検証範囲を作成しLarge Work Protocolの確認を得る。今回の調査はその実装や一般公開を承認済みにするものではない。

## 現在の確認状態

利用者の比較画面報告とコード根拠を照合した。追加ブラウザ操作、実DB/SSH調査、テスト登録・完了・通知、コード修正/build/配備はしていない。完了連動の直前2点には成功回答がなく、引き続き未確認。すでに成功した登録済/候補除外/日時等を失敗扱いへ戻すのではなく、詳細引継ぎの未確認要件を追加する。
次の一手: 共通引継ぎの具体設計と入口別の最小検証を準備する。
