# SunBWork 修繕計画書 第5版 — 案件・ジョブ・日報 不具合修正
作成日: 2026-05-19

---

## 背景・目的

`userwantslist0519.txt` のユーザーデバッグ結果をもとに、案件・ジョブ・スケジュール・日報まわりの不具合を修正する。
REPAIR_PLAN4（レスポンシブ対応）は全タスク完了済み。

---

## 保留・対象外

- **OCR精度調整** → データ収集後に別途対応（今回は対象外）

---

## フェーズ1：単純修正（最優先・小規模）

### R5-01 通知ページの時間表記修正

**対象ファイル:** `resources/js/Pages/JobNotifications/Index.vue`（line 194）

**問題:** `toLocaleString('ja-JP', { hour: '2-digit', ... })` が `「15時」` を返し、`${h}:${m}` が `「15時:40」` になる

**修正:**
```js
// 変更前
const h = String(d.toLocaleString('ja-JP', { timeZone: 'Asia/Tokyo', hour: '2-digit', hour12: false })).padStart(2, '0');
const m = String(d.toLocaleString('ja-JP', { timeZone: 'Asia/Tokyo', minute: '2-digit' })).padStart(2, '0');

// 変更後（数値で取得し文字列に変換）
const jst = new Date(d.toLocaleString('en-US', { timeZone: 'Asia/Tokyo' }));
const h = String(jst.getHours()).padStart(2, '0');
const m = String(jst.getMinutes()).padStart(2, '0');
```

---

### R5-02 案件一覧のお気に入り星が機能しない

**対象ファイル:**
- `routes/web.php` — ルート追加
- `app/Http/Controllers/Coordinator/ProjectJobController.php` — `toggleFavorite` メソッド追加

**問題:** `coordinator.project_jobs.favorite` ルートが存在しない（進行表・管理シートには実装済み）。フロントエンドの `Index.vue` はすでに正しくルートを呼んでいる。

**修正:**
1. `routes/web.php` に以下を追加（coordinator ルートグループ内）:
```php
Route::post('project-jobs/{projectJob}/favorite', [App\Http\Controllers\Coordinator\ProjectJobController::class, 'toggleFavorite'])
    ->name('coordinator.project_jobs.favorite');
```
2. `ProjectJobController::toggleFavorite` を追加（`ProgressSheetListController::toggleFavorite` を参考に実装）:
```php
public function toggleFavorite(ProjectJob $projectJob)
{
    $user = auth()->user();
    $fav = $projectJob->favorites()->where('user_id', $user->id)->first();
    if ($fav) {
        $fav->delete();
        $is_favorite = false;
    } else {
        $projectJob->favorites()->create(['user_id' => $user->id]);
        $is_favorite = true;
    }
    return response()->json(['is_favorite' => $is_favorite]);
}
```
3. `ProjectJob` モデルに `favorites()` リレーションが存在するか確認。なければ追加。

**注意:** `project_job_favorites` テーブルが存在するか確認必要。なければマイグレーション追加。

---

### R5-03 スケジュールパネルの CSV ボタン重複を解消

**対象ファイル:** `resources/js/Components/ProjectCalendar.vue`（line 57–64）

**問題:** 「スケジュール」パネルを開くと、上部ツールバーのCSVボタンとパネル内のCSVボタンが重複して2セット見える。

**修正:** スケジュールパネル内の CSV ボタン2つ（CSV出力 / CSV取込）を削除する。上部ツールバー側は残す。
```vue
<!-- 削除するブロック（lines 57-64 付近） -->
<button v-if="!panelEditMode" ...>CSV出力</button>
<button v-if="!panelEditMode" ...>CSV取込</button>
```

---

### R5-04 進行表ジョブ名の全角ハイフン→アンダーバー統一

**対象ファイル:** 要調査（`ProgressSheet` 関連コントローラー・コンポーネント）

**問題:** ユーザーが進行表ジョブを選択した場合「6年ー算数_初校」（全角ハイフン）、コーディネーターが選択した場合「6年_国語_初校」（アンダーバー）と表記が異なる。

**調査・修正:**
1. ジョブ名を生成している箇所を `grep -rn "ー" resources/js/Pages` で特定
2. 全角ハイフン（`ー`）をアンダーバー（`_`）に統一

---

### R5-05 ジョブ編集時の開始時間が現在時間にセット

**対象ファイル:** 要調査（`AssignmentForm.vue` またはジョブ編集フォーム）

**問題:** ジョブ編集画面で開始時間が常に現在時刻にリセットされる。新規作成時のみ現在時刻をデフォルトにすべき。

**調査・修正:**
1. `AssignmentForm.vue` または `Edit.vue` の `start_time` 初期値ロジックを確認
2. `isEdit` フラグで分岐し、編集時は既存値を保持する

---

## フェーズ2：中規模修正

### R5-06 案件一覧に伝票番号カラム追加 + 表示/非表示カスタマイズ

**対象ファイル:**
- `resources/js/Pages/Coordinator/ProjectJobs/Index.vue`
- `app/Http/Controllers/Coordinator/ProjectJobController.php`（index メソッド）

**仕様:**
- 伝票番号（`project_jobs.voucher_number` 等）のカラムをテーブルに追加
- 「表示設定」ボタンを追加し、カラムの表示/非表示をチェックボックスで管理
- 設定を `localStorage` に保存（キー: `coordinator_job_list_columns`）
- 未設定の場合はデフォルト表示（伝票番号は デフォルト表示）
- 伝票番号未設定の行は空欄表示

**注意:** バックエンドで伝票番号フィールド名を確認（カラム名: `Show.vue` の伝票情報タブを参照）

---

### R5-07 スケジュール編集モードのタブ移動統一・開始日コピー

**対象ファイル:** `resources/js/Components/ProjectCalendar.vue`（スケジュール編集フォーム部分）

**問題:**
- 年→月 はタブ移動、月→日 は文字数判定で自動移動。操作が混乱する
- 開始日入力後、同じ日を終了日にコピーしてくれると楽

**修正:**
1. 年・月・日・時・分 のすべての入力欄で `@keydown.tab.prevent` でなく、自然なタブ移動を統一（自動移動を削除し、すべてタブ移動に統一）
2. 開始日（YYYY/MM/DD）の入力が完了したら、終了日に同じ日付をコピー（`@blur` または入力完了イベントで発火）

---

### R5-08 進行表にユーザー名が表示されない（調査・修正）

**対象ファイル:** 要調査（進行表コンポーネント / コントローラー）

**問題:** コーディネーターが進行表からジョブを割り当てた場合、またはユーザーが進行表ジョブを選択した場合に、進行表にユーザー名が表示されない。

**調査・修正:**
1. 進行表セルのアサイン表示ロジックを確認
2. `assignment.user.name` または `progress_cell.assignment.user.name` の eager load が正しいか確認
3. 表示されていない場合は修正

---

### R5-09 完了/未完了フローの問題修正

**対象ファイル:** 要調査

**問題（複数）:**
- 進行表から「未完了に戻す」を実行しても、ユーザー側のジョブが完了のまま
- ユーザー側でジョブを削除しても進行表上は完了のままロックされる

**調査・修正:**
1. 進行表の「完了に戻す」実行時に `project_job_assignments.completed = false` が更新されているか確認
2. `ProgressCell` の `completed` フラグと `ProjectJobAssignment.completed` の同期ロジックを確認
3. ジョブ削除時の `ProgressCell` の状態リセット処理を確認・追加

---

### R5-10 画像登録/削除後のリロード統一

**対象ファイル:** 要調査（`Show.vue` の伝票画像アップロード/削除モーダル）

**問題:** 画像を登録・削除してもモーダルを閉じた後に画面が反映されない。案件一覧に戻って再度案件に入らないと反映されない。

**修正方針:** モーダルを閉じる処理（`closeXxxModal`）の中で `router.reload({ only: ['...'] })` を呼ぶ。`show.vue` の画像処理モーダル類を統一する。

---

### R5-11 CSV 取り込みの文字コード自動変換（Shift-JIS + CRLF 対応）

**対象ファイル:**
- `app/Http/Controllers/Coordinator/ProjectJobController.php`（一括作成CSVインポート）
- `app/Http/Controllers/Coordinator/ProjectScheduleController.php`（スケジュールCSVインポート）

**問題:** Excel で保存した CSV は通常 Shift-JIS + CRLF で保存される。現在は UTF-8 + LF のみ対応。

**修正:**
```php
// インポートコントローラー冒頭に追加
$content = file_get_contents($file->getRealPath());
// 文字コード検出・変換
$encoding = mb_detect_encoding($content, ['UTF-8', 'SJIS', 'SJIS-win', 'EUC-JP'], true);
if ($encoding && $encoding !== 'UTF-8') {
    $content = mb_convert_encoding($content, 'UTF-8', $encoding);
}
// CRLF → LF 正規化
$content = str_replace(["\r\n", "\r"], "\n", $content);
// 変換後の内容を一時ファイルに書き出して処理続行
```

---

## フェーズ3：大規模修正

### R5-12 テンプレートから案件作成タブ（BulkCreate.vue）

**対象ファイル:**
- `resources/js/Pages/Coordinator/ProjectJobs/BulkCreate.vue`
- `app/Http/Controllers/Coordinator/ProjectJobController.php`（`storeFromTemplate` メソッド追加）

**仕様:**
- `BulkCreate.vue` のタブを「テンプレートから作成（新）」「テンプレート管理」「CSV取込」の順に変更
- 「テンプレートから作成」タブ:
  1. テンプレートを選択
  2. テンプレートの固定項目をプレースホルダー表示（変更不可）
  3. 未固定の項目は入力可能なフォームとして表示
  4. 「作成」ボタン → POST で案件を1件作成
- バックエンド: `storeFromTemplate(Request $request)` を `ProjectJobController` に追加

---

### R5-13 ジョブ重複防止（is_registered フラグ修正）

**対象ファイル:**
- `app/Http/Controllers/User/ProjectJobAssignmentController.php`

**問題:** 「マイジョブとして登録」時に新しいレコードを作成しても、元のコーディネーター割当の `is_registered` が `true` にならないため、再度「マイジョブとして登録」できる。

**修正:** `store()` メソッドのトランザクション内、新規割当作成後に追加:
```php
// 元の Coordinator 割当を「登録済み」にする
if (!empty($a['supersedes_assignment_id'])) {
    ProjectJobAssignment::where('id', (int)$a['supersedes_assignment_id'])
        ->whereColumn('user_id', '!=', 'sender_id')  // Coordinator 割当のみ
        ->update(['is_registered' => true]);
}
```

**影響範囲:** `JobBox/Show.vue` はすでに `is_registered` で「登録済み」を判定している（`v-if="!assignment.is_registered"` → `v-else 登録済み`）。バックエンドのフラグを正しく立てれば UI は自動で対応。

---

### R5-14 日報記入中のタイムライン表示・カレンダー連動

**対象ファイル:**
- `resources/js/Pages/Diaries/Create.vue`（または `Edit.vue`）
- `app/Http/Controllers/Diaries/DiaryController.php`（update でカレンダーイベント更新）

**仕様:**
1. 日報記入・編集画面の入力欄の下に当日のタイムライン（`Diaries/Show.vue` で表示しているもの）を表示
2. 日報を編集・保存した場合、関連するカレンダー予定（イベント）も変更

**調査:** `Diaries/Show.vue` のタイムライン部分のコンポーネント構造を確認してから実装方針を決める

---

### R5-15 Quill エディターの箇条書き・段落番号修正

**対象ファイル:** 要調査（Quill エディターを使用している Vue コンポーネント）

**問題:** 箇条書き・段落番号入力時にインデントされるだけで、リストが正しく機能しない。

**調査・修正:**
1. `grep -rn "quill\|QuillEditor" resources/js/` で Quill 利用箇所を特定
2. Quill の `modules.toolbar` 設定と `list` モジュール設定を確認
3. CSS の `ql-editor ul`, `ql-editor ol` スタイルが適用されているか確認
4. 必要に応じて Quill の設定またはスタイルを修正

---

### R5-16 日報タイムライン編集でカレンダー連動（追加実装）

**対象ファイル:**
- `resources/js/Pages/Diaries/Create.vue`
- `resources/js/Pages/Diaries/Edit.vue`

**仕様:**
R5-14 で追加したタイムラインは `:editable="false"` の閲覧専用だったため、実際のカレンダー連動が未実装だった。

| 操作 | 挙動 |
|------|------|
| タイムライン上のイベントをドラッグ/リサイズ | `PUT /events/{id}/calendar` を呼び、タイムラインを再取得 |
| イベントをクリック | `events.edit` ページへ遷移（`return_to` で日報ページに戻れる） |
| 空白をクリック | `events.create` ページへ遷移（同上） |
| Edit.vue で日付変更 | `watch(form.date)` でタイムラインを再取得 |

**注意:**
- Create.vue は未保存のため、ナビゲーション前に確認ダイアログを表示する
- Edit.vue は既保存のため確認なしでナビゲーション
- タイムラインを常時表示（`v-if` 条件を除去）

---

## 作業 ID 一覧（全フェーズ）

| ID | 内容 | 変更ファイル数 | 難易度 |
|----|------|------------|--------|
| R5-01 | 通知時間表記修正 | 1 | 極小 |
| R5-02 | 案件お気に入り星 | 2〜3（要確認） | 小 |
| R5-03 | スケジュールパネルCSVボタン削除 | 1 | 極小 |
| R5-04 | ジョブ名全角ハイフン→アンダーバー | 要調査 | 小 |
| R5-05 | ジョブ編集時の開始時間 | 1〜2 | 小 |
| R5-06 | 伝票番号カラム + 表示設定 | 2 | 中 |
| R5-07 | スケジュール編集タブ移動 | 1 | 小〜中 |
| R5-08 | 進行表ユーザー名表示 | 要調査 | 小〜中 |
| R5-09 | 完了/未完了フロー | 要調査 | 中 |
| R5-10 | モーダル閉じてリロード統一 | 要調査 | 小〜中 |
| R5-11 | CSV文字コード自動変換 | 2 | 小 |
| R5-12 | テンプレートから作成タブ | 2（大） | 大 |
| R5-13 | ジョブ重複防止 | 1 | 小 |
| R5-14 | 日報タイムライン表示 | 2 | 中 |
| R5-15 | Quillエディター箇条書き | 要調査 | 中 |
| R5-16 | 日報タイムライン編集・カレンダー連動 | 2 | 中 |
