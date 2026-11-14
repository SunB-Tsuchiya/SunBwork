# 情報出版「部署の作業」公開とマイジョブ入力統合 設計1

作成: 2026-11-14 JST。状態: 利用者が段階順を承認。直後に利用者の指示で一時中断。アプリ実装、追加配備、今回の画面確認は未実施。
基準: 隔離ブランチ `feature/info-department-myjob-plan-20261114` の起点 `ae46644ea`。最後に確認した本番アプリ版は `0ab725a78`。今回、本番SSH・DB照会はしていない。現行のSuper Admin試用と情報出版一般Userの仕事・案件公開については各MANAGERの利用者成功記録を引き継ぐ。

## 利用者の決定と範囲

- **確定:** 「部署の作業」を情報出版のユーザー全員に見せる。2026-11-14の回答で対象を「サン・ブレーンの情報出版全員」と明示。添付画像は松本さんの名前と下段メニューの欠落を示すもので、ロゴからサンエー向け公開を推定しない。製版・オンデマンド・サンエー向け公開は今回の対象外。
- **確定:** 今後の標準の「マイジョブ」は現在の簡易入力を意味する。Super Adminで試した入力・統合BOXを情報出版の通常運用にする。旧形式で作成する選択肢と既存記録は残す。
- **確定:** 急いで画面を切り替えず、調査と計画の後に段階的に実装する。既確認のSuper Admin画面試験を繰り返さない。
- **提案:** 部署画面の一般公開は**閲覧専用**に分ける。本人の入力権限、管理者のV3点数/順位/分析権限、共有予定の編集権限とは混ぜない。
- **提案:** 旧 `project_job_assignments` と新 `personal_work_entries` は統合BOXに併存させ、名前一致や時刻一致で結合・コピー・一括変換しない。旧形式は詳細項目や進行表・銀本が必要な場合の選択肢として維持する。

## 現行コードから確認した差

| 対象 | 現在 | 設計上の扱い |
| --- | --- | --- |
| User下段「部署の作業」 | `UserNavigationTabs.vue` は `isSuperAdmin && canAccessWorkRecordTrial` のみ | INFO所属本人の閲覧用フラグに置換。兼用ロールのUser画面でも同じ表示 |
| 部署画面本体 | `ScheduleController::index` の `departmentWorkEnabled` は `PersonalWorkSchedule::available()`（INFO Super Admin試用） | 閲覧用判定で予定/記録の2タブを出す。通常の共有予定は現行のまま |
| 部署予定API | `DepartmentWorkController::index/readContext` は試用guard。会社・部署で人を絞り、他人のprivate件名を伏せる | このGETだけ閲覧guardへ。分析/results/details/guide等は試用・管理guardのまま |
| みんなの作業記録API | `DepartmentWorkRecords::records` は同じ試用guard。返却は氏名/時間/4区分/本人案内で、点数/順位/内容を含まない | 専用GETだけ閲覧guardへ。会社・部署・日付/祝日・private・本人案内の境界を退行検証 |
| 個人入力 | `PersonalWorkSchedule::personalAvailable()` と `UserWorkHub` でINFO一般Userへ既に開放 | 既存の本人権限を保持。部署の閲覧判定で書込・分析を開かない |
| カレンダーの簡易作業入口 | `CalendarController` は `available()` を `departmentWorkEnabled` に使い、`UserCalendar` の簡易イベント取得・ドラッグ後の選択肢・作成モーダルを一緒に制御。一般UserはBOX保存できてもこの入口が旧画面 | 本人入力可否と部署閲覧可否を別propsに分け、INFO一般Userのカレンダーからも同じ簡易入力へ |
| カレンダーの選択肢 | `ActionSheet` には「簡単な作業予定を追加」と「マイジョブ登録」が併存。前者は試用者だけ、後者は一般Userを旧形式へ送る | 標準の「マイジョブ登録」1入口を簡易入力へ接続。旧形式はモーダル内の既存「旧形式で作成」から開く。進行表・管理表の専用入口は保持 |
| BOX/保存先 | `UnifiedMyJobBox` は `entry:{id}` と `assignment:{id}` を投影。簡易入力は `PersonalWorkEntry`、旧形式は自己割当+events | 同じ仕事を複製しない。新規標準は簡易記録、旧履歴は同じBOXで読めるまま |
| 表示語 | BOXに「簡易入力」、部署ボードに「簡易予定」、凡例に「マイジョブ（簡易入力）」が残る | 利用者向けは新規標準を「マイジョブ」に統一。旧形式との区別が必要な画面だけ由来を補足 |

## 段階と実装案

1. **閲覧権限を分離して部署画面を公開。** `canViewDepartmentWork(User, context)` 相当を1か所に置き、実所属がサン・ブレーンINFO、会社コンテキスト一致の場合だけ許可。メニュー、`Schedule/Index`、部署予定GET、記録GETへ同じ条件を使う。会社・部署をリクエストで広げない。既存の `available()` は管理分析/試用評価用に保持。読取APIのprivate内容、点数/順位、本人以外の不足案内が出ないことを合成検証。
2. **カレンダーの本人入力を標準マイジョブへ接続。** `CalendarController` の本人簡易入力フラグと部署閲覧フラグを分ける。`UserCalendar` の簡易記録の取得・表示・ドラッグ日時・作成/編集を `personalAvailable()` に揃える。通常予定・進行表/管理表の入口は独立させる。「マイジョブ登録」は `QuickWorkEntryModal(entryPoint=myjob)` へ。旧形式はモーダル内の既存選択肢で開く。ボタン二重表示を解消し、保存先は `personal_work_entries` のまま。
3. **言葉と導線を統一。** 新しい入力・BOX・カレンダー・部署ボードの見出し/凡例を「マイジョブ」に揃える。旧記録を「旧形式」と明示できる範囲を限定。過去流用・受けた依頼から登録・続き・完了・校正依頼・詳細入力・ファイル情報は既存経路を使い、旧形式への引継ぎで日時や元依頼の関係を失わない。
4. **公開差分を固定して反映。** 他のClaude作業・本番HEAD・生成資産を照合し、変更ファイルだけの隔離ブランチと `/members` buildを準備。SSH前にAGENTS.mdどおり正確なコマンドを提示し確認を得る。既存URL/保存タブは変えず、DB一括変更を行わない。

## 検証と残す境界

- 合成データでINFOの一般User・SuperAdmin・User画面兼用者の同部署GET/メニュー、INFO外・別会社コンテキストの拒否、他人privateの伏字、点数/順位/係数のAPI不在、本人以外の案内不在を確認。管理分析GETと更新権限は拡張しない。
- カレンダーの時刻ドラッグ→標準マイジョブ入力→保存→本人BOX/カレンダー/日報の同一作業、編集・完了・続き、旧形式選択、過去流用、受けた依頼、校正、進行表/銀本専用経路を確認。同名作業・元依頼・旧未予定・複数日予定で二重表示/二重計上がないことを確認。UTC/JSTは `CONSOLIDATED_05_calendar_and_jobbox.md` に従う。
- 既確認のSuper Admin操作を繰り返さず、一般Userの新規「部署の作業」画面、カレンダーからの標準入力、旧形式への戻りだけ実画面で確認する。コード検証・配備・画面・管理側確認は別状態で記録。
- 正式な評価点公開、製版/オンデマンド/サンエーの仕事機能公開、旧データ一括移行、分類統合/削除、進行表/銀本の簡易形式への移植は別判断。通常予定はマイジョブBOXへ混ぜない。
- 主な候補ファイル: `app/Services/PersonalWorkSchedule.php`、`DepartmentWorkRecords.php`、`app/Http/Controllers/Schedule/{ScheduleController,DepartmentWorkController}.php`、`app/Http/Controllers/CalendarController.php`、`app/Http/Middleware/HandleInertiaRequests.php`、`resources/js/Components/Tabs/UserNavigationTabs.vue`、`resources/js/Pages/Schedule/Index.vue`、`resources/js/Components/Calendar/{UserCalendar,ActionSheet,QuickWorkEntryModal}.vue`、BOX/部署表示Vue、関連テスト・資料。新テーブル/マイグレーションは想定しない。

## 実装前に確認すること

この設計の段階分けと「情報出版全員には部署の予定・記録を閲覧、評価点/順位は従来どおり非公開」「新規マイジョブは簡易入力、旧形式は任意で残す」方針を利用者に提示し、2026-11-14に「この順で実装してよい」と承認された。その直後に利用者が区切りのよいところでの中断を指示。再開指示まで実装へ進まない。
