# ジョブフロー完全ガイド

作成日: 2026-05-20  
このファイルはジョブ割り当て・登録・重複防止の全フローを記録する。  
修正・追加時は必ずここに反映すること。

---

## 1. テーブル・用語の定義

### `project_job_assignments`（PJA）— 唯一のジョブテーブル

すべてのジョブ（コーディネーター割当・マイジョブ・進行表ジョブ）が同一テーブルに格納される。

| キー列 | 意味 |
|--------|------|
| `sender_id = user_id` | 自己割当 = マイジョブ（MyJobBox に表示） |
| `sender_id ≠ user_id` or NULL | コーディネーター割当（JobBox に表示） |
| `supersedes_assignment_id` | このマイジョブが「置き換えた」コーディネーター割当のID |
| `coordinator_assignment_id` | コーディネーターが「予定をセット」した際に自動生成されたユーザー側PJAが指す、元のコーディネーター割当のID |
| `source_assignment_id` | 「続きジョブ」として登録した場合の前のジョブのID |
| `is_registered` | コーディネーター割当がマイジョブとして登録済みか（`supersedes_assignment_id` で参照される側に付く） |
| `job_type` | `'worker'` `'proof'` など |

### `job_assignment_messages`（JAM）

- コーディネーターがジョブを割り当てた際に送信されるメッセージレコード
- **メッセージボックス（Message UI）は廃止済み** だが、このテーブル自体は残っている
- `JobBoxController::user()` が「依頼されたジョブ」一覧を構築するために今も利用している
- JAM が存在しないコーディネーター割当は、`user()` メソッドの結果に現れない（後述）

---

## 2. JAM（job_assignment_messages）が作成される条件

コーディネーターがジョブを割り当てるルートは複数ある：

| ルート | コントローラー | JAM 作成タイミング |
|--------|--------------|------------------|
| 案件詳細 → ジョブ割り当て（standard） | `Coordinator/ProjectJobAssignmentsController::store()` | `send_immediately = true` のときのみ |
| 案件詳細 → 複合ジョブ割り当て | `Coordinator/CompositeJobAssignmentController::store()` | 常に作成 |
| JobBox → 返信/新規メッセージ | `JobBoxController::store()` | 常に作成 |

**⚠️ 注意:** `send_immediately = false` で作成されたコーディネーター割当は JAM が存在しない。  
この場合、ユーザーの「依頼されたジョブ」画面に表示されない（現在の既知問題）。

---

## 3. 「依頼されたジョブ」表示の仕組み

### ルート
- `GET /user/jobbox` → `JobBoxController::user()`
- ページ: `resources/js/Pages/JobBox/Index.vue`（`routeContext='user'`）

### クエリ条件
```php
// JAM テーブルベース
JobAssignmentMessage
    ->where('project_job_assignments.user_id', $user->id)  // 自分への割当
    ->where('job_assignment_messages.sender_id', '!=', $user->id)  // 自己送信は除外
```

**以前あった条件（2026-05-20 削除）:**
```php
// 削除済み: 登録済みジョブを DB レベルで除外していた
// ->whereNotExists(pja_self.supersedes_assignment_id = pja.id AND ...)
```

→ 削除理由：フロントエンドの「登録済みを表示しない」チェックボックスで制御するため

### is_registered の動的計算
`user()` の paginate 後に `supersedes_assignment_id` の存在チェックで補完する：
```php
// DB列 is_registered が false/null でも動的フォールバックで正しい値を返す
$supersededSet = array_flip(PJA::whereIn('supersedes_assignment_id', $aids)
    ->whereColumn('sender_id', 'user_id')->pluck('supersedes_assignment_id')->all());
$msg->projectJobAssignment->is_registered = $dbVal || isset($supersededSet[$aid]);
```

---

## 4. マイジョブとして登録フロー（JobBox → MyJobBox）

### ユーザーの操作
1. 「依頼されたジョブ」一覧 → ジョブ詳細（`JobBox/Show.vue`）を開く
2. 「マイジョブとして登録」ボタンをクリック  
   （条件: `isAssignee && !assignment.is_registered`）

### リンク先
```
/user/project_jobs/assignments/create?source_job_assignment_id={coordinator_pja.id}
→ ProjectJobAssignmentUserController::create()
→ ページ: MyJobBox/Create_user.vue
```

### バックエンド: create() のガード
```php
// 既に登録済みなら MyJobBox にリダイレクト（ブラウザバック二重登録防止）
if ($alreadyRegistered) {
    return redirect()->route('user.myjobbox.index')
        ->with('error', 'このジョブはすでにマイジョブとして登録済みです。');
}
```

### バックエンド: store() の処理
`User/ProjectJobAssignmentController::store()` が実行する処理：

```
1. 重複ガード: supersedes_assignment_id の二重登録チェック（DB レベル）
2. 新しい自己割当 PJA を作成
   - sender_id = user_id（自己割当マーカー）
   - supersedes_assignment_id = 元コーディネーター PJA の ID
   - accepted = true, scheduled = true（即時セット済み）
3. 元コーディネーター PJA に is_registered = true をセット
4. 対応する Event を作成（starts_at/ends_at 付き）
5. 進行表セルとの紐付け（_row_id/_col_key がある場合）
```

### 結果

| 場所 | 表示されるもの |
|------|---------------|
| 依頼されたジョブ（JobBox） | 元コーディネーター PJA が残る（is_registered=true、デフォルト非表示） |
| マイジョブBOX（MyJobBox） | 新しい自己割当 PJA が表示される |
| カレンダー | 新しい Event が表示される |

---

## 5. 「予定をセット」フロー（MyJobBox → イベント）

### ユーザーの操作
1. MyJobBox でジョブ詳細（`MyJobBox/Show.vue`）を開く
2. 「予定をセット」ボタンをクリック  
   （条件: `isAssignee && !(assignment.scheduled || assignment.scheduled_at)`）

### リンク先
```
/events/create_job?job={pja.id}
→ EventController::createJob()
→ AssignmentForm.vue (mode="user")
```

### 処理内容
- **新しい PJA は作成しない**
- 既存の PJA に紐づく `Event` レコードを作成するだけ
- PJA の `scheduled = true` / `scheduled_at = now()` をセット

---

## 6. コーディネーター側「予定をセット」フロー

コーディネーターが JobBox の割当に対して「予定をセット」を実行した場合：

### 処理内容
- ユーザー側の自己割当 PJA を新規作成する（`coordinator_assignment_id` で元を参照）
- `sender_id = user_id`（自己割当扱い）
- MyJobBox に自動的に表示される

### ⚠️ 重複リスク
コーディネーターが「予定をセット」した後、ユーザーが同じジョブで「マイジョブとして登録」した場合：
- `coordinator_assignment_id` を持つ PJA（コーディネーター作成分）
- `supersedes_assignment_id` を持つ PJA（ユーザー登録分）

→ 両方が MyJobBox に表示される可能性がある（**未解決・要確認**）

---

## 7. is_registered / whereNotExists の歴史的経緯

### 旧来の動作（2026-05-20 以前）
- `JobBoxController::user()` に `whereNotExists` 句があり、`supersedes_assignment_id` で登録済みのコーディネーター割当を DB レベルで除外していた
- 結果：登録済みジョブが「依頼されたジョブ」一覧から消え、ユーザーが確認できなかった

### 変更後の動作（2026-05-20~）
- `whereNotExists` を削除
- `is_registered = true` のジョブは一覧に残るが、フロントエンドの「登録済みを表示しない」チェックボックス（デフォルトON）で非表示
- チェックを外せば「登録済」バッジ付きで表示される

### `is_registered` の信頼性
- `store()` で正しく `true` にセット済み（新規フロー）
- 古いレコードは `supersedes_assignment_id` の動的チェックでフォールバック
- `myjob:cleanup-duplicates --execute` で既存データの補完が可能

---

## 8. 重複防止の仕組み（現在の実装）

| レイヤー | 実装場所 | 内容 |
|----------|---------|------|
| フォーム表示ガード | `ProjectJobAssignmentUserController::create()` | `is_registered` または supersedes 存在で MyJobBox にリダイレクト |
| 保存時 DB ガード | `User/ProjectJobAssignmentController::store()` | `supersedes_assignment_id` の重複チェック→エラー返却 |
| UI ガード | `JobBox/Show.vue` | `is_registered = true` の場合ボタンを「登録済み」に切り替え |
| 表示フィルター | `JobBox/Index.vue` | `hideRegistered` チェックボックスでデフォルト非表示 |

---

## 9. 既知の問題・TODO

### 未解決
- [ ] `send_immediately = false` の割当が「依頼されたジョブ」に表示されない  
  → `user()` に extraItems 的な仕組みを追加するか、send_immediately を常に true にするか要検討
- [ ] コーディネーターが「予定をセット」→ ユーザーが「マイジョブとして登録」した場合の重複  
  → `coordinator_assignment_id` を持つ PJA と `supersedes_assignment_id` を持つ PJA が共存する

### 解決済み（2026-05 REPAIR5）
- [x] 編集フォームで終了時刻が現在時刻+30分になるバグ（normalizeAssignment の sender_id 漏れ）
- [x] ブラウザバックによる二重登録（create/store のガード追加）
- [x] `is_registered` が動的クエリで上書きされるバグ（JobBoxController::show）
- [x] 登録済みジョブの可視化（「登録済みを表示しない」チェックボックス追加）
- [x] 既存 DB の is_registered 補完（`myjob:cleanup-duplicates` コマンド）

---

## 10. 関連ファイル一覧

| ファイル | 役割 |
|---------|------|
| `app/Http/Controllers/ProjectJobs/JobBoxController.php` | 依頼ジョブ一覧・詳細・完了（user() / show()） |
| `app/Http/Controllers/ProjectJobs/ProjectJobAssignmentUserController.php` | マイジョブ登録フォーム表示（create()）|
| `app/Http/Controllers/User/ProjectJobAssignmentController.php` | マイジョブ登録保存（store()）|
| `app/Http/Controllers/User/MyProjectJobController.php` | マイジョブBOX一覧・完了 |
| `app/Models/ProjectJobAssignment.php` | PJA モデル・スコープ・リレーション |
| `resources/js/Pages/JobBox/Index.vue` | 依頼ジョブ一覧（フィルター・チェックボックス） |
| `resources/js/Pages/JobBox/Show.vue` | 依頼ジョブ詳細・「マイジョブとして登録」ボタン |
| `resources/js/Pages/MyJobBox/Index.vue` | マイジョブ一覧 |
| `resources/js/Pages/MyJobBox/Show.vue` | マイジョブ詳細・「予定をセット」ボタン |
| `resources/js/Pages/MyJobBox/Create_user.vue` | マイジョブ登録フォーム |
| `app/Console/Commands/CleanupDuplicateMyJobRegistrations.php` | 既存DB重複クリーンアップ |
| `z_instructions/JOB_NOTIFICATION_SPEC.md` | 通知機能仕様（未実装） |
