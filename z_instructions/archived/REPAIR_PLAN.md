# SunBWork 修繕計画書
作成日: 2026-04-21

---

## 作業方針

1. **フェーズ1** — バグ修正（即修正・リスク低）
2. **フェーズ2** — レイアウトガイドライン策定＋全体適用
3. **フェーズ3** — 機能改善（中規模）
4. **フェーズ4** — 大規模機能開発
5. **別プロジェクト** — 子案件機能（別計画書を参照）

各フェーズは前のフェーズ完了後に着手する。フェーズ内の項目は番号順に実施。

---

## フェーズ1：バグ修正

### B-01 カレンダーの予定削除が失敗する
**症状:** スケジュール編集サブウィンドウの「削除」ボタンを押すと「予定の削除に失敗しました」
**調査先:**
- `app/Http/Controllers/Coordinator/ProjectSchedulesController.php` — destroy() メソッド
- `resources/js/Pages/Coordinator/ProjectSchedules/Calendar.vue` — 削除リクエスト実装
**対応:** CSRF・ルート・HTTPメソッドの確認、エラーレスポンスのデバッグ

---

### B-02 カレンダーの日付が1日ずれて表示される
**症状:**
- 5/10〜5/20 で設定 → 案件詳細では 5/11〜5/19 と表示
- 「案件カレンダー」ボタンから開くと 5/12〜5/20 と表示（経路で表示が異なる）
- 編集後に日付がさらにずれていく
**調査先:**
- `app/Models/ProjectSchedule.php` — start_date / end_date のキャスト
- `app/Http/Controllers/Coordinator/ProjectSchedulesCalendarController.php`
- `resources/js/Pages/Coordinator/ProjectSchedules/Calendar.vue` — 日付変換処理
**対応:** タイムゾーン変換の統一（UTC↔JST の扱い）、フロントとバックの日付フォーマット統一

---

### B-03 スケジュール編集後に予定が二重表示される
**症状:** 編集するとカレンダー上で同じ予定が2つ表示される（案件詳細に戻ると消える）
**調査先:** `resources/js/Pages/Coordinator/ProjectSchedules/Calendar.vue` — イベントリストの再描画ロジック
**対応:** 編集後のイベント更新処理（削除→追加ではなく上書き更新）

---

### B-04 「ジョブ詳細を開く」ボタンが反応しない
**症状:** 進行管理表の「詳細」→「ジョブ詳細を開く」を押しても何も起きない
**調査先:**
- `resources/js/Pages/Coordinator/ProgressSheets/Show.vue` — 詳細パネルのボタン実装
**対応:** クリックハンドラーのデバッグ、ルート名の確認

---

### B-05 「未完了にする」してもジョブ一覧では完了扱いのまま
**症状:** 進行管理表でジョブを完了→「未完了にする」→ジョブ一覧では完了のまま
**調査先:**
- `app/Http/Controllers/Coordinator/ProgressCellController.php` または `ProgressSheetController.php` — 完了/未完了切り替え処理
- `ProjectJobAssignment` の `completed` フラグの更新有無
**対応:** セルの完了状態変更時に `project_job_assignments.completed` も同期して更新する

---

### B-06 ジョブ一覧「完了を表示しない」チェックOFFでも完了ジョブが出ない
**症状:** 上部ナビの「ジョブ一覧」→「完了を表示しない」のチェックを外しても完了ジョブが表示されない
**調査先:**
- `resources/js/Pages/Coordinator/JobBox/Index.vue` または `resources/js/Pages/JobBox/Index.vue` — フィルター実装
- 対応コントローラーのクエリ
**対応:** フィルター条件のバグ修正（チェック状態とクエリの対応確認）

---

### B-07 案件内の割り当て一覧→ジョブ一覧が空になる
**症状:** 案件内「割り当て一覧」を開いた後、上部「ジョブ一覧」に入ると何も表示されない。「案件一覧」など別ページを経由すれば表示される
**調査先:**
- `resources/js/Pages/Coordinator/ProjectJobs/Show.vue` — 割り当て一覧表示時の状態管理
- ジョブ一覧ページのフィルター初期化処理
**対応:** ページ遷移時のフィルター状態リセット、または Inertia の状態引き継ぎ問題の修正

---

## フェーズ2：レイアウトガイドライン策定＋全体適用

### L-01 レイアウトガイドライン文書の作成
**作成先:** `z_instructions/LAYOUT_GUIDELINES.md`
**内容:**

#### ボタン種別・デザイン定義
| 種別 | 用途 | 色・スタイル | 配置位置 |
|------|------|-------------|---------|
| プライマリ | 新規作成・保存・送信 | indigo-600（白文字） | ページ右上 または フォーム下部右 |
| セカンダリ | 編集・複製 | white + border-gray-300（gray文字） | プライマリの隣 |
| 危険 | 削除・取り消し | red-600（白文字） | 右端・確認ダイアログ経由 |
| 戻る | 前ページ・一覧へ | gray-200（gray-700文字） | ページ左上 |
| キャンセル | フォーム・モーダル内 | gray-100（gray-600文字） | フォーム下部左 または モーダル右下左 |

#### 配置ルール
- **「戻る」系ボタン:** 必ずページ左上（`#header` スロット内、見出しの左隣）に配置
- **「新規作成」系ボタン:** 必ず `#headerExtras` スロットに配置（ページ右上）
- **編集・削除ボタン:** 詳細ページの場合は `#headerExtras` スロットに配置
- **フォーム内ボタン:** 「キャンセル（左）」「保存（右）」の順

#### 表記統一
| 現状（バラバラ） | 統一後 |
|----------------|--------|
| プロジェクト詳細に戻る | 案件詳細に戻る |
| 一覧へ | 一覧に戻る |
| キャンセル（ページ遷移用） | 一覧に戻る |
| 戻る | 〇〇に戻る（遷移先明記） |

#### カレンダーのマス目サイズ
- 案件スケジュールカレンダーと案件カレンダーボタンからの表示を同一コンポーネントに統一

---

### L-02 ガイドラインの全ページ適用
**対象ページ（優先順）:**
1. Coordinator/ProjectJobs/Show.vue（案件詳細 — 最多アクセス）
2. Coordinator/ProgressTemplates/Edit.vue（テンプレート編集 — 戻るボタンなし）
3. Coordinator/ProgressSheets/Show.vue（進行管理表）
4. Coordinator/ProjectSchedules/Calendar.vue（カレンダー — 表記揺れあり）
5. JobBox/Index.vue、Coordinator/JobBox/Index.vue
6. その他全ページ順次適用

**共通コンポーネント化の検討:**
- `BackButton.vue` — 「〜に戻る」ボタンの共通コンポーネント
- `PageActions.vue` — ページ右上アクションボタン群のラッパー

---

## フェーズ3：機能改善（中規模）

### F-01 ジョブステータスフローの刷新
**現状:** `assigned` / `accepted` / `completed` フラグ + `status_id` が混在し、送信直後に「確認済み」になるバグあり
**新仕様:**
| ステータス | 条件 | DB操作 |
|-----------|------|--------|
| 送信 | 割り当て直後 | `assigned=true`, `read_at=null` |
| 確認済み | 受信者が詳細を開いた時 | `read_at=now()` |
| セット | 受信者がカレンダーにセットした時 | `accepted=true` |
| 完了 | 完了操作時 | `completed=true` |

**変更先:**
- `app/Models/ProjectJobAssignment.php` — ステータス判定ロジック追加
- `app/Http/Controllers/Coordinator/ProjectJobAssignmentsController.php` — store() で read_at をセットしないよう修正
- `app/Http/Controllers/User/ProjectJobAssignmentController.php` — 詳細表示時に read_at をセット
- 各Vue — ステータス表示ラベルの更新

---

### F-02 進行管理表テンプレートに「戻る」ボタン追加
**対象:** `resources/js/Pages/Coordinator/ProgressTemplates/Edit.vue`
**現状:** 「キャンセル」ボタンのみ（直感的でない）
**対応:** ガイドラインに従い左上に「テンプレート一覧に戻る」ボタンを追加

---

### F-03 台割行の見出しグループ後の追加ができない問題
**症状:** 見出しでグループ化して子行を作ると、後から見出し行を追加できない
**調査先:** `app/Http/Controllers/Coordinator/ProgressRowController.php` — store() の order/parent ロジック
**対応:** グループ化後でも見出し行を任意の位置に追加できるよう修正

---

### F-04 テンプレート見出し・行の編集機能
**症状:** テンプレートで設定した台割行の見出しが後から編集できない
**調査先:** `resources/js/Pages/Coordinator/ProgressTemplates/Edit.vue` — 行編集UI
**対応:** インライン編集（ダブルクリック or 編集アイコン）の実装

---

### F-05 行管理で追加時に末尾文字が省略される
**症状:** 行管理で文字を追加すると入力した最後の文字が省略される
**調査先:** `resources/js/Pages/Coordinator/ProgressTemplates/Edit.vue` — 入力バインディング
**対応:** v-model の debounce 処理またはイベントハンドラーの修正

---

### F-06 台割行の「複製」機能
**対象:** 進行管理表の台割行（ProgressRow）
**現状:** 列・ステージには複製あり、行にはなし
**対応:**
- `app/Http/Controllers/Coordinator/ProgressRowController.php` — duplicate() メソッド追加
- `resources/js/Pages/Coordinator/ProgressSheets/Show.vue` — 複製ボタンUI追加

---

### F-07 「案件詳細に戻る」で進行管理表タブに戻る
**症状:** 進行管理表から「案件詳細に戻る」を押すと、案件詳細の概要タブが開く
**期待動作:** 進行管理表タブが開いた状態で案件詳細に戻る
**対応:** 遷移先URLにタブパラメータを付与（例: `?tab=progress_sheet`）し、案件詳細側でそれを読んでタブを初期表示

---

### F-08 スケジュールの直接入力（カレンダー以外からの入力）
**現状:** スケジュールはカレンダーからしか入力・編集できない
**期待動作:** 編集サブウィンドウを直接呼び出して入力したい
**対応:**
- 案件詳細のスケジュール一覧に「＋追加」「編集」ボタンを設置
- モーダルで日付・タイトル・詳細を直接入力できるフォームを実装
- カレンダー経由と同じ API を呼ぶ

---

### F-09 「進行表に紐づける」を紐づけ済みなら操作不可
**現状:** 進行管理表から振ったジョブにも「進行表に紐づける」が操作可能
**期待動作:** すでに紐づいている場合は「紐づけ済み」として操作不可（グレーアウト）
**対応:**
- `ProjectJobAssignment` に `progress_cell_id` 等の紐づき判定フィールドを確認
- Vue側で紐づき済みの場合はボタンを disabled に

---

### F-10 カレンダー週間プランナービューの追加
**概要:** 月ビュー・週グリッドビューに加え、縦に日～土を並べた週間プランナービューを追加する

**レイアウトイメージ:**
```
┌──────┬─────────────────────────┬──────────────────┐
│      │       予定               │  週メモ           │
├──────┼─────────────────────────┤（右カラム共通）    │
│ 月   │ ■ 台割A 〜4/22          │                   │
│ 4/20 │ ■ 組版B 〜4/25          │ 自由テキスト       │
├──────┼─────────────────────────┤ 入力可能           │
│ 火   │ ■ 台割A 〜4/22          │                   │
│ 4/21 │                         │                   │
└──────┴─────────────────────────┴──────────────────┘
```

**必要な変更:**
- `database/migrations/` — `project_schedule_week_memos` テーブル追加（`project_job_id`, `year`, `week`, `body`）
- `app/Models/ProjectScheduleWeekMemo.php` — モデル追加
- `app/Http/Controllers/Coordinator/ProjectScheduleWeekMemoController.php` — store/update
- `routes/web.php` — ルート追加
- `resources/js/Components/ProjectWeekPlanner.vue` — 新規コンポーネント
- `resources/js/Components/ProjectCalendar.vue` — ビュー切替ボタンに「週間プランナー」追加

---

## フェーズ4：大規模機能開発

### G-01 スケジュールと進行管理表の連動
> **詳細設計書:** `z_instructions/G01_ITEM_DESIGN.md`
**概要:** カレンダー・スケジュール・進行管理表を三位一体で連動させる

**仕様:**
1. **過去日の色変化:** スケジュールの end_date を過ぎたセル行をグレーアウト
2. **進行表の行にスケジュール日付を紐づけ:** 台割行にスケジュールの deadline を設定可能にする
3. **期日近いセルのハイライト:** 3日以内の行を黄色でアラート表示
4. **カレンダー日付クリック→セルジャンプ:** カレンダーの日付をクリックすると、その日付に紐づいた進行管理表のセル行にページジャンプ（スクロール）

**DB変更:**
- `progress_rows` テーブルに `deadline` (date) カラムを追加するマイグレーション

**変更先（主要）:**
- `database/migrations/` — progress_rows に deadline カラム追加
- `app/Models/ProgressRow.php`
- `app/Http/Controllers/Coordinator/ProgressRowController.php`
- `resources/js/Pages/Coordinator/ProgressSheets/Show.vue` — 日付表示・ハイライト・ジャンプ
- `resources/js/Pages/Coordinator/ProjectSchedules/Calendar.vue` — クリックイベント追加

---

### G-02 案件の複製機能拡張
**現状:** タイトル・チームメンバー・SubCoのみ複製（`ProjectJobController::clone()`）
**拡張仕様:**
1. **基本情報＋メンバー:** 現状維持
2. **スケジュール複製:** `project_schedules` を全件コピー、日付を一括シフト可能
   - 複製ダイアログに「日付を〇日ずらす」入力欄を追加
3. **進行管理表の構造複製:** `progress_sheets` → `progress_rows` → `progress_cells` を構造ごとコピー（アサインは空）

**変更先:**
- `app/Http/Controllers/Coordinator/ProjectJobController.php` — clone() メソッド拡張
- `resources/js/Pages/Coordinator/ProjectJobs/Show.vue` — 複製ダイアログUI（日付シフト入力付き）

---

## 別プロジェクト：子案件機能

### 概要
親案件の下に子案件（別伝票番号）を選択式で追加できる仕組みを設計する。

### 要件（ユーザー確認済み）
- 親案件（例：7/18実施育成テスト3456年）を作成
- 別伝票番号で子案件（ここぽ / HTML）を任意に追加可能
- 子案件の有無は選択式（テストによってここぽやHTMLを作らない場合がある）
- 子案件は独立した案件として機能しつつ、親案件と連動

### 設計検討が必要な点
- `project_jobs` テーブルへの `parent_job_id` (nullable FK) 追加
- 子案件の種別マスター（ここぽ / HTML / その他）
- 親案件詳細での子案件一覧表示UI
- 進行管理表・スケジュールの親子間での共有/独立の判断
- 権限（Coordinator・Clerk）の扱い

### 計画書の作成方法
このセクションをもとに、別の Claude との会話で詳細仕様・DB設計・実装計画を策定する。

---

## 作業ログ

| 日付 | フェーズ | 項目 | 状態 |
|------|---------|------|------|
| 2026-04-21 | — | 計画書作成 | 完了 |

