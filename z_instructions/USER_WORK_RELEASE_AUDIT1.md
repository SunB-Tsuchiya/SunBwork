# 情報出版の仕事・案件 公開前の静的調査

2026-11-14。起点 `f3405ea2c`、最後の本番配備証拠 `dfb1362eb`。今回SSH・DB・画面操作なし。Super Admin試用の以前の利用者「成功」は既存管理資料の範囲で引き継ぎ、現行本番画面を再確認したとは扱わない。

## 対象と分離する権限

| 対象 | 現行コード | 公開準備で必要なこと |
| --- | --- | --- |
| 仕事・案件の上段と5内側タブ | `UserNavigationTabs.vue` と `UserWorkHubTabs.vue` は `userWorkHub.enabled` を参照。11/10の `cfccf17c6` で共有値が削除された履歴あり | INFO Super Admin限定条件で共有値を作業ブランチに復元済み。一般向け有効化は別段階。製版/オンデマンドの6メニュー、サンエー表示を維持 |
| 統合BOXと簡易入力 | `UserWorkHub::available` → `PersonalWorkSchedule::available`。この判定はINFO Super Admin限定。`workHubMyJobRoute` はさらにSuper Adminを要求 | INFOの本人入力・統合BOXだけを開く専用判定へ分ける。旧形式と詳細・続き・完了・依頼取り込みを同じ対象者に揃える |
| 部署の作業・試用評価 | `DepartmentWorkController::readContext`、`ScheduleController`、`WorkRecordTrialController` 等も `PersonalWorkSchedule::available` を使用 | 従来の試用/分析判定を保持。一般Userの入力解放と一緒に部署全体の分析・評価画面を開かない |
| 大まかな案件 | Userルートは `BroadProjectController` を継承し、管理者だけの `BroadProjectTree::authorize` を先に要求。`BroadProjectFavorites` も同判定。User画面は `canManage=false` で追加ボタンも隠す | INFO一般Userの同部署一覧・検索・お気に入り・即時追加だけを許可。編集・移動・並べ替え・休止は進行管理/Leader/Super Adminのまま。会社・部署境界と既存参照を維持 |
| 依頼ジョブ・校正 | 既存ルートと試用中の続き・完了・通知経路あり | 新タブからの行先を揃える。既確認のSuper Admin本人操作を繰り返さず、一般Userの新規対象と非対象者の拒否を検証 |

## 入口と保存処理の照合範囲

| 分類 | 主なコード・ルート | 現時点の扱い |
| --- | --- | --- |
| 入口 | `HandleInertiaRequests::share`、`UserNavigationTabs.vue`、`workHubNavigation.js`、SuperAdmin試用入口 | 共有値を従来の試用判定で復元。`workHubMyJobRoute` の追加Super Admin判定も一般公開時に合わせる |
| 本人の作業 | `UnifiedMyJobBoxController`、`WorkJobDetailController`、`DepartmentWorkController` の `options/mine/store/update/destroy`、`EventController` の `myjob_modal`、`ProjectJobAssignmentController`、`DiaryWorkEntries` | GET/POST/PUT/DELETEと候補・日報・カレンダーを同じ本人/INFO判定にそろえる。`PersonalWorkSchedule::available` を一括変更しない |
| 分類の参照と新規登録 | User broad-projectルート、`BroadProjectTree`、`BroadProjectFavorites`、共通一覧と登録フォーム | 読取・新規追加・お気に入りと、管理操作をサーバで分離。現在の `UserWorkHub::createBroadProject` は旧平面形で、現行ツリー/複数取引先フォームに直接接続しない |
| 試用専用画面 | `DepartmentWorkController` の部署一覧/分析、`WorkRecordTrialController`、`ScheduleController` の部署作業タブ、Leader作業分析、Coordinator依頼フォーム試用 | 公開対象外のまま、現行認可と画面を保持する |
| 戻り先 | `AppLayout.vue` の `lastTab_<role>`、旧詳細URLとカレンダー直リンク | URLを変えずに移行する。保存済みURLの失敗時はダッシュボードへ戻る既決方針を、実装工程で照合する |

## 最初の安全な工程

1. 欠落した共有値 `userWorkHub` と `auth.canManageBroadProjects` を作業ブランチの `HandleInertiaRequests` に復元した。両方とも本人の会社コンテキストを要求し、`userWorkHub` は既存 `UserWorkHub::available`、分類管理は既存 `BroadProjectTree::canManage` に従う。よってこの差分だけでは一般User、製版、オンデマンド、サンエーへ新機能を開かない。既存統合テストにはSuper Admin有効、他ロール/部署/会社無効の期待がある。PHP実行・本番反映・新しい利用者画面確認は未実施。
2. 本人のジョブ操作専用のINFO判定を用意する。試用分析の `available` は維持したまま、関連する本人の入口・保存処理を新判定へ移す。一般公開スイッチをオンにする前に、合成データで本人/別人・同部署/別部署・会社コンテキストを確認する。
3. 分類の一般User閲覧・追加・お気に入りを管理操作から分離する。ツリーと複数取引先の現行入力に合わせ、既存分類の編集/移動/休止は管理者だけにする。
4. 新しいBOXと旧形式/依頼/校正の行先を統合し、試用表示文言を公開向けに整える。既確認操作の再実施を避け、新規ロールの代表例だけ画面確認する。

この順序は調査結果に基づく実装順であり、一般Userの本番有効化・画面確認を実施済みとするものではない。
