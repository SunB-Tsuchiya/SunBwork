# SunBWork 修繕計画書 第2版
作成日: 2026-04-26

---

## 作業方針

1. **フェーズ1** — バグ修正（即修正・リスク低）
2. **フェーズ2** — UI改善（表示・操作性の改善）
3. **フェーズ3** — 機能改善（中規模）
4. **将来計画** — ガイド書き換え（全体修正完了後に別計画で実施）

各フェーズは前のフェーズ完了後に着手する。フェーズ内の項目は番号順に実施。

---

## フェーズ1：バグ修正

### N-06 ユーザーカレンダー（events）削除時の 500 エラーとコーディネーターとの非同期

**症状1:** ユーザーのカレンダー（`/calendar`）でイベント（events）を複数削除していくと、最後の1件を削除する際に 500 Server Error が発生する。
**症状2:** ユーザー側でイベントを削除するとカレンダーからは消えるが、コーディネーターのジョブ一覧（JobBox）には残ったまま同期されない。

**調査先:**
- `app/Http/Controllers/EventController.php` — `destroy()` メソッド（最後の1件削除時に何らかの依存レコードが残っていてエラーが出る可能性）
- `app/Models/Event.php` — リレーションの cascadeDelete 設定
- `app/Http/Controllers/EventController.php` — イベント削除時に紐づく `ProjectJobAssignment` の accepted / scheduled フラグをリセットしているか確認
- `resources/js/Pages/Calendar/Index.vue` または `resources/js/Pages/Calendar.vue` — 削除後のリロード・同期処理

**対応:**
- EventController::destroy() で最後の1件でも正常に削除できるよう修正（null チェック、関連レコードの存在確認）
- イベント削除時に紐づく `ProjectJobAssignment` の `accepted = false`（またはnull）・`scheduled_at = null` を更新してコーディネーター側に反映
- 必要に応じて WebSocket / Inertia のリロードでコーディネーター側の表示を同期

---

### N-07 ジョブ履歴からジョブを削除後のリダイレクト先が「ジョブ一覧」になる

**症状:** 案件詳細ページ（`ProjectJobs/Show.vue`）のジョブ履歴タブからジョブを削除すると、削除後に上部の「ジョブ一覧」（`coordinator.jobbox`）に飛んでしまう。案件詳細のジョブ履歴タブに戻すべき。

**調査先:**
- `app/Http/Controllers/Coordinator/ProjectJobAssignmentsController.php` — `destroy()` のリダイレクト先（現在: `coordinator.project_jobs.assignments.index`）
- `resources/js/Pages/Coordinator/ProjectJobs/Show.vue` — ジョブ履歴タブ内の削除ボタン実装（どのエンドポイントを呼んでいるか）

**対応:**
- `destroy()` のリダイレクト先を `coordinator.project_jobs.show` に変更し、クエリパラメータ `?tab=history` を付与
- フロント側（Show.vue）でも削除後に `router.reload({ only: ['jobHistory'] })` または `router.get(...)` で同タブに留まるよう修正

---

### N-09 ジョブステータス表示がページによって異なる

**症状:** 同一のジョブでも、表示ページによってステータス名が異なる。
- 進行管理表割り振り後 → ユーザーカレンダー・Coordinator ジョブ一覧：「進行中」
- 案件ジョブ履歴：「受信済」
- 案件割り当て一覧：「未読」
- ユーザーカレンダーで「確認済」→ Coordinator ジョブ一覧：「進行中」
- 案件ジョブ履歴：「確認済」、割り当て一覧：「確認済み」（表記揺れ）

**基準（F-01 で実装した4段階）:**
| ステータス | 条件 | 表示ラベル |
|-----------|------|-----------|
| 送信 | assigned=true, read_at=null, accepted=false | 未読 |
| 確認済み | read_at が set | 確認済み |
| セット | accepted=true | セット済み |
| 完了 | completed=true | 完了 |

**調査先（全て統一）:**
- `resources/js/Pages/Coordinator/JobBox/Index.vue` — `getUnifiedStatus()`
- `resources/js/Pages/JobBox/Index.vue` — `getAssignmentStatus()`
- `resources/js/Pages/Coordinator/ProjectJobs/Show.vue` — ジョブ履歴タブ `historyGetStatus()`
- `resources/js/Pages/Coordinator/ProjectJobs/JobAssign/Index.vue` — 割り当て一覧のステータス表示
- `resources/js/Pages/Coordinator/ProgressSheets/Show.vue` — 進行管理表でのステータス表示

**対応:**
- 全ページで共通の `getUnifiedStatus(assignment)` ロジック（F-01 と同じ実装）を使うよう修正
- 表示ラベルの表記を「未読 / 確認済み / セット済み / 完了」に完全統一

---

### N-10 「戻る」ボタンが機能しないページの調査・修正

**症状:** ジョブ詳細（特に Events/Show.vue 系）の「戻る」ボタンが反応しない・機能しない。他にも同様の問題がある可能性がある。

**調査方針:**
1. まず全体から「戻る」ボタンの実装を検索し候補を列挙する
2. 問題が起きていると確認されている `Events/Show.vue` から着手
3. その後他のページを一覧し、ユーザーと確認しながら順次修正

**調査先（優先）:**
- `resources/js/Pages/Events/Show.vue` — 戻るボタンの実装
- `resources/js/Pages/Coordinator/Events/Show.vue` — 存在する場合

**調査先（候補確認リスト）:**
- `resources/js/Pages/JobBox/Show.vue` — 「戻る」(routeBack()) ← ユーザー確認済みで動作している
- `resources/js/Pages/MyJobBox/Show.vue`
- `resources/js/Pages/User/ProofJobs/Show.vue`
- `resources/js/Pages/User/ProjectJobs/Show.vue`
- その他 Events 系・Show 系ページ

**対応:**
- `Events/Show.vue` の戻るボタンを `router.back()` または適切な `route()` に修正
- 他のページで問題が見つかった場合は同様に修正
- 候補一覧は REPAIR_MANAGER2.md の確認チェックリストに記載して順次確認

---

## フェーズ2：UI改善

### N-01 ジョブ履歴の初期表示を「展開済み」に変更

**症状:** 案件詳細ページのジョブ履歴タブで、各ジョブが初期状態で折りたたまれており不便。
**期待動作:** ページ表示時に全てのジョブが展開された状態になっている。

**調査先:**
- `resources/js/Pages/Coordinator/ProjectJobs/Show.vue` — ジョブ履歴セクション（`activeTab === 'history'`）の折りたたみ制御変数の初期値

**対応:**
- 折りたたみ状態を管理している `ref`/`Map` の初期値を全件展開済みに変更
- または「全て展開」「全て折りたたむ」切り替えボタンを追加

---

### N-02 ジョブ割り振り時の開始時刻初期値を現在時刻（5分刻み）に

**症状:** ジョブ割り振りフォーム（AssignmentForm）で開始時刻がデフォルト値（例：9:00）固定になっている。
**期待動作:** 開始時刻は現在時刻を5分刻みに丸めた値を初期値にする。終了時刻は 17:30 のまま。

**調査先:**
- `resources/js/Pages/Coordinator/ProjectJobs/JobAssign/AssignmentForm.vue` — 開始時刻の初期値設定
- `resources/js/Components/AssignmentForm.vue` — 存在する場合

**対応:**
- `start_time` の初期値を `Math.ceil(new Date().getTime() / (5*60*1000)) * (5*60*1000)` で計算した現在時刻（5分刻み切り上げ）に設定
- `end_time` は 17:30 のまま維持

---

### N-05 案件詳細タブ構成の変更（スケジュールタブを独立）

**現状のタブ順:**
```
概要・メンバー | 連携設定 | 進行管理表 | ジョブ履歴
```
※「スケジュール」は「概要・メンバー」タブ内のセクションとして表示

**新しいタブ順:**
```
概要・メンバー | 進行管理表 | スケジュール | 連携設定 | ジョブ履歴
```

**変更内容:**
- 「スケジュール」を独立したタブに分離（タブキー: `schedule`）
- 「連携設定」を「スケジュール」の後ろに移動
- 「概要・メンバー」タブからスケジュール一覧セクションを除去し、スケジュールタブに移動
- カレンダー専用ページ（`/coordinator/project_schedules/calendar`）は引き続き存在（スケジュールタブからリンク）

**調査先:**
- `resources/js/Pages/Coordinator/ProjectJobs/Show.vue` — `tabs` 配列・スケジュール表示セクション

---

### N-11 案件カレンダーCSV出力のファイル名に案件名を含める

**症状:** 案件スケジュールカレンダーからCSVを出力した際のファイル名が固定名（例: `schedules.csv`）になっている。

**調査先:**
- `app/Http/Controllers/Coordinator/ProjectSchedulesController.php` — `csvExport()` メソッドのファイル名生成
- `resources/js/Pages/Coordinator/ProjectSchedules/Calendar.vue` — CSV出力ボタンの実装

**対応:**
- コントローラー側でファイル名を `{案件名}_スケジュール.csv` 形式に変更
- 案件名はルートパラメータまたは request から取得
- ファイル名のサニタイズ（`/`, `\`, `:` 等を `_` に置換）を行う

---

### N-12 進行管理表の行をクリックで開けるようにする

**症状:** 案件詳細・スケジュール・ジョブ履歴の各表では行全体がクリッカブルだが、進行管理表タブのみ「開く」ボタンを押す必要がある。

**調査先:**
- `resources/js/Pages/Coordinator/ProjectJobs/Show.vue` — 進行管理表タブのリスト表示部分（`activeTab === 'progress'`）

**対応:**
- `<tr>` に `@click` を付与し、Inertia の `router.get()` で `coordinator.progress_sheets.show` に遷移
- ホバー時に `cursor-pointer` と背景色変化を追加
- 「開く」ボタンは残してもよい（アクセシビリティのため）

---

## フェーズ3：機能改善

### N-03 ジョブタイトル命名規則の統一（アンダーバー区切り）

**症状:** 同じ種類のジョブでも、作成経路によってタイトルの命名規則が異なる。
- 進行管理表から割り振り: `4年_国語_初校_組版`（アンダーバー）
- ユーザーカレンダー「ジョブ作成（進行表から）」: `6年ー算数_初校ー組版`（ハイフンとアンダーバー混在）

**調査先:**
- `app/Http/Controllers/ProjectJobs/JobBoxController.php` または `EventController.php` — ユーザーカレンダーからのジョブ作成時のタイトル生成ロジック
- `app/Http/Controllers/Coordinator/ProjectJobAssignmentsController.php` — 進行管理表からのタイトル生成ロジック
- `resources/js/Pages/Events/Create_Job.vue` — フロント側でのタイトル生成

**対応:**
- 全作成経路でタイトル区切り文字をアンダーバー `_` に統一
- `str_replace(['ー', '-', '－', '—', '–'], '_', $title)` 的な正規化処理をヘルパー関数として切り出し
- 既存のジョブタイトルは変更しない（新規作成時のみ適用）

---

### N-04 「詳細を見る（進行表へ）」の遷移先改善

**症状:** ユーザーカレンダーのジョブ作成（進行表から）で割り振ったジョブの詳細から「詳細を見る（進行表へ）」をクリックすると、案件の概要ページに飛んでしまう。

**期待動作:**
- 紐づいている進行管理表が1枚 → 直接その進行管理表ページ（`user.progress_sheets.show`）に遷移
- 紐づいている進行管理表が複数枚 → 選択モーダルを表示し、ユーザーが選んだシートに遷移

**調査先:**
- `resources/js/Pages/JobBox/Show.vue` または `resources/js/Pages/MyJobBox/Show.vue` — 「詳細を見る（進行表へ）」ボタンの実装
- `app/Http/Controllers/ProjectJobs/JobBoxController.php` — ジョブ詳細取得時に紐づき進行管理表の情報を返しているか確認

**対応:**
- バックエンド: `show()` のレスポンスに `linked_progress_sheets`（`id`, `name`, `project_job_name`）を追加
- フロント: 
  - `linked_progress_sheets.length === 1` → 直接 `router.get(route('user.progress_sheets.show', { sheet: id }))` へ
  - `linked_progress_sheets.length > 1` → シート選択モーダルを表示
  - `linked_progress_sheets.length === 0` → ボタンを非表示またはグレーアウト

---

### N-08 ジョブ一覧グループ表示の記憶 + Coordinator 設定タブの追加

#### N-08a: グループ表示記憶（DBテーブル）

**現状:** Coordinator ジョブ一覧の「日付ごと/クライアントごと/案件ごと」は localStorage で管理されていない（毎回デフォルトに戻る）。

**DB設計:**
```
テーブル名: coordinator_settings
カラム:
  id                 bigint unsigned auto_increment
  user_id            bigint unsigned NOT NULL FK→users
  jobbox_group_mode  varchar(20) DEFAULT 'date'  ← 'date' / 'client' / 'project'
  jobbox_default_tab varchar(50) DEFAULT ''       ← 将来の拡張用
  created_at         timestamp
  updated_at         timestamp
```

**変更先:**
- `database/migrations/2026_04_26_create_coordinator_settings_table.php`（新規マイグレーション）
- `app/Models/CoordinatorSetting.php`（新規モデル）
- `app/Http/Controllers/Coordinator/CoordinatorSettingController.php`（新規コントローラー）
  - `index()` — 設定取得（JSON）
  - `update()` — 設定保存（PUT）
- `routes/web.php` — ルート追加

**フロント変更:**
- `resources/js/Pages/Coordinator/JobBox/Index.vue` — グループ変更時に API を呼んで DB に保存・ページ読み込み時に DB から取得して初期値にセット

#### N-08b: Coordinator ナビゲーションに「設定」タブを追加

**現状:** Coordinator のタブメニューに「クライアント管理」「外注先管理」などはあるが「設定」がない。

**追加する設定項目（初期）:**
- ジョブ一覧のデフォルトグループ（日付ごと / クライアントごと / 案件ごと）

**変更先:**
- `resources/js/Layouts/AppLayout.vue` または `resources/js/Components/CoordinatorNavigationTabs.vue` — 「設定」タブリンクを追加
- `resources/js/Pages/Coordinator/Settings/Index.vue`（新規ページ）— 設定フォームを表示・保存
- `routes/web.php` — `coordinator.settings.index`、`coordinator.settings.update` ルートを追加

---

## 将来計画（別計画書で実施）

### GUIDE-01 ガイドの全面書き換え

**対象:** Admin / Coordinator / Leader / User の全4ガイドページ
**背景:** 機能追加・変更を重ねたため、現在の実装と内容が乖離している。具体的な問題点：
- 「依頼されたジョブ」タブとあるが、実際は「マイジョブ」に表示される
- ジョブステータスの順番がユーザー・Coordinator で異なる
- 案件スケジュールが「ガントチャート」と書かれているが未実装（カレンダー方式）
- ガイドページ上部のナビゲーションが混乱を招く
- ステータスの4段階（F-01で実装）が反映されていない

**作業方針（別計画書で策定）:**
1. 現在の実際の動作をスクリーンショット等で確認しながら現状を棚卸し
2. ガイド対象機能を一覧化し、各ロールの実際の操作フローを整理
3. 既存ガイドファイル（`resources/markdown/` 等）を更新
4. ガイドページ上部ナビゲーションの整理

**実施タイミング:** 全修繕計画（REPAIR_PLAN2）完了後に着手

---

## 作業ログ

| 日付 | フェーズ | 項目 | 状態 |
|------|---------|------|------|
| 2026-04-26 | — | 計画書（REPAIR_PLAN2.md）・管理書（REPAIR_MANAGER2.md）作成 | 完了 |
