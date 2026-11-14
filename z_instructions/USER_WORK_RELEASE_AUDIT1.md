# 情報出版の仕事・案件 公開前の静的調査

2026-11-14。調査起点 `f3405ea2c`、実装起点 `b7436fa02`、最後の本番配備証拠 `dfb1362eb`。今回SSH・本番DB・画面操作なし。Super Admin試用の以前の利用者「成功」は既存管理資料の範囲で引き継ぎ、現行本番画面を再確認したとは扱わない。下表の「現行コード」は隔離作業ブランチの未配備差分を指す。

## 対象と分離する権限

| 対象 | 現行コード | 公開準備で必要なこと |
| --- | --- | --- |
| 仕事・案件の上段と5内側タブ | `UserNavigationTabs.vue` と `UserWorkHubTabs.vue` は共有 `userWorkHub.enabled` を参照。11/10の `cfccf17c6` で共有値が削除された履歴あり | 作業ブランチで共有値を復元し、本人のINFO判定とBOX入口を一般User/兼用者に揃えた。製版/オンデマンドの6メニュー、サンエー表示はJS確認済み |
| 統合BOXと簡易入力 | `UserWorkHub::available` → `PersonalWorkSchedule::personalAvailable`。従来の `available` は部署分析用として維持 | INFOの本人入力・統合BOX・旧形式へのリンク・詳細・続き・完了・依頼取り込みを同じ対象者に揃えた。合成PHPテスト済み、本番未反映 |
| 部署の作業・試用評価 | `DepartmentWorkController::readContext`、`ScheduleController`、`WorkRecordTrialController` 等も `PersonalWorkSchedule::available` を使用 | 従来の試用/分析判定を保持。一般Userの入力解放と一緒に部署全体の分析・評価画面を開かない |
| 大まかな案件 | Userルートは `BroadProjectController` を継承。作業ブランチでは `canBrowse` と `canManage` を分離 | INFO一般Userの同部署一覧・検索・お気に入り・即時追加を許可。編集・移動・並べ替え・休止は進行管理/Leader/Super Adminのまま。会社・部署境界と既存参照を維持 |
| 依頼ジョブ・校正 | 既存ルートと試用中の続き・完了・通知経路あり | 新タブからの行先を揃える。既確認のSuper Admin本人操作を繰り返さず、一般Userの新規対象と非対象者の拒否を検証 |

## 入口と保存処理の照合範囲

| 分類 | 主なコード・ルート | 現時点の扱い |
| --- | --- | --- |
| 入口 | `HandleInertiaRequests::share`、`UserNavigationTabs.vue`、`workHubNavigation.js`、SuperAdmin試用入口 | 共有値を復元し、`workHubMyJobRoute` の追加Super Admin判定を外した。サーバのINFO判定と会社コンテキストに従う |
| 本人の作業 | `UnifiedMyJobBoxController`、`WorkJobDetailController`、`DepartmentWorkController` の `options/mine/store/update/destroy`、`EventController` の `myjob_modal`、`ProjectJobAssignmentController`、`DiaryWorkEntries` | GET/POST/PUT/DELETEと候補・日報・カレンダーを同じ本人/INFO判定にそろえた。`PersonalWorkSchedule::available` は一括変更せず、試用分析のまま |
| 分類の参照と新規登録 | User broad-projectルート、`BroadProjectTree`、`BroadProjectFavorites`、共通一覧と登録フォーム | 読取・新規追加・お気に入りと、管理操作をサーバで分離。追加は現行ツリー/複数取引先フォームから `BroadProjectTree::save` の本人追加モードを使う。旧平面形の `UserWorkHub::createBroadProject` は接続しない |
| 試用専用画面 | `DepartmentWorkController` の部署一覧/分析、`WorkRecordTrialController`、`ScheduleController` の部署作業タブ、Leader作業分析、Coordinator依頼フォーム試用 | 公開対象外のまま、現行認可と画面を保持する |
| 戻り先 | `AppLayout.vue` の `lastTab_<role>`、旧詳細URLとカレンダー直リンク | URLを変えずに移行する。保存済みURLの失敗時はダッシュボードへ戻る既決方針を、実装工程で照合する |

## 最初の安全な工程

1. 先行コミット `b7436fa02` では、欠落した共有値 `userWorkHub` と `auth.canManageBroadProjects` を従来条件で復元した。その時点で一般Userの新機能公開はなかった。今回の追加差分で `UserWorkHub::available` の判定だけを本人入力用に切り替えた。分類管理値は従来の `BroadProjectTree::canManage` のまま。
2. 本人のジョブ操作専用のINFO判定を用意し、試用分析の `available` は維持。本人の入口・保存処理を新判定へ移した。合成データで本人/別人・同部署/別部署・会社コンテキストを検証済み。
3. 分類の一般User閲覧・追加・お気に入りを管理操作から分離。ツリーと複数取引先の現行入力を利用し、既存分類の編集/移動/休止は管理者だけにした。
4. 新しいBOXの表示文言を公開向けに変更。既確認操作の再実施を避け、新規ロールの代表例だけを配備後に画面確認する。配備・画面確認は未実施。

今回の未配備差分は、関連PHP合成テスト160件・1,507断言、JSナビ/分類/入力テスト、ESLint、`/members` ビルド、PHP構文、差分チェックが成功。一般Userの本番有効化・画面確認を実施済みとするものではない。既存URLは変更していないため `lastTab_<role>` の保存URLはそのまま利用できる。URLが失敗した場合のダッシュボード復帰は別工程として残る。
