# 依頼登録の共通引継ぎ 修正設計1

更新: 2026-11-09 JST。調査正本MYJOB_REQUEST_HANDOFF_AUDIT1.md。親MYJOB_UNIFIED_BOX_PLAN1.md。
状態: 利用者『あなたの案でOK、修正しましょう』により共通引継ぎ方針と修正着手は承認。本書の具体仕様は実装前確認待ち。

## 目的・境界

依頼一覧詳細、カレンダー簡易入力、旧作成ページ内の依頼選択、従来モーダルから、同じ本人宛依頼を同じ初期値・固定表示で旧形式のマイジョブ入力へ渡す。新式に詳細項目を増やさない。現行の情報出版・非一般会社SuperAdmin本人の試用内で修正し、一般公開/全roleの挙動変更はしない。一般roleには従来の経路を維持。
コード基準45eaece31（調査資料追加のみ。実装は最後の配備証拠5ef950b77と同じ）。共有Claude作業・CLAUDE.mdを変更しない。追加migration・既存データ補正は予定しない。

## 共通の読取と受渡し

サーバのReceivedJobPrefill相当サービスを正本とし、元依頼IDから本人宛Coordinator割当・同案件・会社部署/context・未取り込みを検証して、以下を一つの応答へ組み立てる。旧NULL所属は本人宛同案件で、既存試用の互換境界を守る。候補外だが本人宛の有効依頼も必要な案件/client候補を補う。生のモデル全属性は返さず許可した項目だけを返す。

- projectJob: 案件ID/名称、client情報、size_id/page_count等の既存固定・補完に必要な値。
- assignment: _client_id、project_job_id、title_suffix、detail、work_item_type_id、size_id、stage_id、difficulty_id、estimated_hours、amounts/amounts_unit、file_info、依頼締切/時刻、本人user_id。
- 関連: supersedes_assignment_id、校正coordinator_assignment_id、確認済み進行表/管理表セル参照。source_assignment_idは続きであり受信取り込みには使わない。
- 表示候補と固定フラグ、元依頼/案件の更新確認用情報。ファイル本体を返したり保存したりしない。

選択一覧は軽い表示用のままにし、選んだ1件の詳細を認可付きGETで取得する。直リンクの旧createも同じサービスを呼ぶ。GETはDB無変更。4項目だけをフォームへコピーする処理を対象入口で置換する。イベントcreateの進行表・校正専用経路も同じ値の組立てを利用できる範囲を確認し、専用参照を失う単純redirectはしない。

## 値の扱い

| 項目 | 今回の扱い |
| --- | --- |
| クライアント | 案件に紐づく値を初期選択し、必要な候補も追加。 |
| サイズ | 通常の依頼詳細で現在使う『元依頼の明示サイズ→ファイル情報由来の推定→案件size_id』の順序を共通化。案件size_idがあれば既存の『案件で固定』表示を維持。明示値と案件値が矛盾する例は自動補正せず、既存値と表示を合成で照合してmanagerに記録。 |
| 数量/単位 | 現行詳細のfile_info合計→元amounts→案件page_countの補完を維持。0と未指定を区別し、単位を勝手にpageへ変えない。元の既知の不自然な条件等は今回の共通化で意味を変えない。 |
| 作業詳細 | 元依頼の種別/工程/難易度/見積/概要/file_infoを初期値として渡す。ユーザーが編集可能な項目は入力後の変更を保持。 |
| 作業日時 | カレンダーの選択日/開始終了を最優先で保持。依頼締切とは別に扱う。JSTローカル日付、既存prefillEventの処理を使用。 |
| ステータス | 今回の目的外。旧詳細とevents.create_jobの既存入口別初期値/非表示設定を変更しない。id=1の名称を全環境で決めつけない。 |

明示サイズと案件固定値の矛盾を今回新ルールで解消すること、数量算定方式の変更は保留。修正中に共通化では解消できない矛盾が出たらそのケースだけ相談する。

## 選択・保存・エラー

- 詳細取得に失敗したら現在入力を保持してエラー表示。未取得の不完全な選択で保存へ進めない。
- 依頼A→Bの連続選択、閉じる/戻る、遅延応答でAのprojectJobや固定サイズをBへ残さない。初期値・案件オブジェクト・候補・参照をまとめて更新してからフォームを再生成する。
- 選択時は作成しない。保存時は本人宛/案件/会社部署/context・取り込み済み判定をトランザクション内で元依頼をロックして再検証。選択後に元依頼や案件が変わった場合は再読込案内とし、古い固定情報で黙って保存しない。入力内容は可能な限り保持。
- ユーザーが編集した任意値を元依頼値で一律上書きしない。必要項目の欠落をフロントの不完全なコピーで発生させない設計と、保存時の関係検証を分ける。
- 新自己割当1件/予定/元登録済み/進行セル等の既存保存関係を維持。保存失敗や競合で途中だけ登録済みにならないことを検証。校正の専用coordinator参照と通常supersedesを混同しない。
- JobBox/Showのquery組立て後に追加されたrow/col/sheetがURLへ入らない箇所も、関連保持の範囲で修正・検証する。認可はURLの自己申告ではなく元依頼を基準にする。

## 影響予定

- 新しい共通prefill Serviceと、必要なら選択処理の共通JS。
- ProjectJobs/ProjectJobAssignmentUserController.create、User/MyProjectJobControllerの選択読取、EventController.createJobの対象prefill、User/ProjectJobAssignmentController.storeの試用受信登録検証。
- routes/web.phpの本人試用読取GET。
- Events/Create_Job.vue、MyJobBox/Create_user.vue、MyJobCreateModal.vue、共通AssignmentForm.vue、JobBox/Show.vue（関連queryのみ）。QuickWorkEntryModalの日時handoffは原則維持。
- 合成Integration/実Vueテスト、関係CONSOLIDATED、専用ChangelogSeederとChangelogSeederの委譲。version列30文字以内を事前検証。

実際の差分は実装後MANAGERで確定し、他のCalendarController等の共有作業を無条件で取り込まない。

## 検証と配備

1. 同じ合成依頼で4入口のprefill/固定表示/フォームpayload/保存値を比較。client、案件固定サイズ/未指定サイズ/明示競合、file_info/数量単位/0、種別/工程/概要/見積/難易度を含める。
2. ドラッグ日時保持、依頼締切との区別、キャンセル/遅延/選択切替/取得失敗/保存失敗時保持。
3. 本人以外・別会社部署/context・登録済・元依頼更新・同時保存、候補外有効依頼、進行表/管理表/校正の参照、supersedesと続きの区別、複製/重複通知なし。
4. 既存完了連動の合成54件を必要な影響範囲で退行確認。実DBを使わず明示SQLite :memory:/network none、Tests\TestCase/RefreshDatabase/migrate:fresh禁止。実Vue/lint/PHP構文、members build/manifest/source全保持/旧資産保持を確認。
5. 配備前に最新本番SHAと他Claude変更を照合、固定差分と専用Seederだけの手順を準備し、正確なSSHコマンドを承認後に実行。一般公開なし。実装/コード検証/配備/利用者画面/上長照合を別状態で管理。

利用者画面確認は今回報告の2入口の初期値・固定表示とドラッグ日時を中心に最小限とし、以前成功した全試験を繰り返さない。実保存確認は今回の詳細値保持に必要な代表例だけを、登録してよい仕事がある場合に依頼。前回の完了連動画面2点と通知実受信はまだ未確認として保持。

## 段階

A: 3資料を作成して具体設計の実装前確認。B: 共通読取/各入口/保存検証と合成テスト。C: 最新本番との差分確認と限定配備の別承認。D: 今回の利用者画面確認。次の一手はAの確認。
