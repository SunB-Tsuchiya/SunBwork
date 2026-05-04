# SunBWork レイアウト修繕 作業管理書
作成日: 2026-05-02

---

## ■ この管理書の使い方

**ユーザーへ:**
- 各作業の開始時に「R-01を始めましょう」などと Claude に伝えてください
- Claude は以下の「作業フロー」に従って安全に進めます
- 作業完了ごとに「進捗一覧」を更新します

**Claude へ（セッション開始時に必ず読むこと）:**
1. この管理書（LAYOUT_REPAIR_MANAGER.md）を読む
2. `z_instructions/LAYOUT_REPAIR_PLAN.md` を読む（詳細仕様が記載されている）
3. `z_instructions/LAYOUT_SPEC_V2.md` を読む（ガイドラインの正解パターン）
4. 現在の進捗一覧を確認し、ユーザーに次の推奨作業を提示する
5. 以下の「作業フロー」に従って進める

---

## ■ 参照ドキュメント

| ドキュメント | 内容 |
|-------------|------|
| `z_instructions/LAYOUT_REPAIR_PLAN.md` | 修繕計画の詳細仕様・対象ファイル・対応内容 |
| `z_instructions/LAYOUT_SPEC_V2.md` | レイアウト統一仕様書（ガイドライン正解パターン） |
| `z_instructions/LAYOUT_GUIDELINES.md` | 旧ガイドライン（LAYOUT_SPEC_V2.md に統合済み） |
| `CLAUDE.md` | プロジェクト全体のルール（必読） |

---

## ■ 作業フロー（Claude はこの手順を厳守すること）

各作業項目（R-xx）は以下のステップで進める。

```
STEP 1: 計画書を読む
  → LAYOUT_REPAIR_PLAN.md の該当項目を読み、仕様を把握する
  → 対象ファイルを Read ツールで確認する（推測で作業しない）

STEP 2: 設計・方針の提示（ユーザーに確認を取る）
  → 変更ファイル一覧・変更内容の概要・影響範囲を提示
  → 不明点があれば1つずつ質問する（複数同時に質問しない）
  → ユーザーの「OK」を確認してから次のステップへ

STEP 3: 実装
  → 承認された設計に従って実装する
  → Vue/JSファイルを変更したら npm run build を実行
  → エラーが出たら修正してから次のファイルへ進む

STEP 4: 動作確認の依頼
  → 変更内容を簡潔に報告する
  → ユーザーに動作確認をお願いする

STEP 5: 完了記録
  → ユーザーから「OK」が出たら進捗一覧のステータスを「✅ 完了」に更新
  → 備考欄に変更したファイル名を記録
  → 「次は R-xx（内容）が推奨です。進めますか？」と聞いて、必ずここで止まる
  → ユーザーが「yes」「OK」などと言うまで次の作業（ファイル読み込み・設計提示を含む）を一切始めない
```

### ⚠️ 作業ペースルール（最重要）

- **STEP 4（動作確認依頼）の後は必ず止まる。** ユーザーが「OK」と言うまで STEP 5 に進まない。
- **STEP 5 の完了記録後も必ず止まる。** 次の作業の提案のみ行い、ファイルを読んだり設計を提示したりしない。
- ユーザーが明示的に「yes」「OK」「進めて」などと言った場合のみ次の STEP 1 に進む。
- 「OK」はその作業への承認であって、次の作業の自動開始を意味しない。

### ⚠️ カード幅ルール（2026-05-02 確定）

| ページ種別 | カードクラス |
|-----------|------------|
| Index / 一覧系 | `rounded bg-white p-6 shadow`（フルワイド、`mx-auto max-w-*` なし） |
| Create / Edit / Show / Select 系 | `mx-auto max-w-2xl rounded bg-white p-6 shadow` |

基準: Index 系 → `Coordinator/ProjectJobs/Index.vue` / それ以外 → `mx-auto max-w-2xl`

### ⚠️ 安全ルール（必ず守ること）

- STEP 2 でユーザーの確認なしに実装を始めない
- 1つの作業が完了するまで次の作業に移らない
- エラーが出た場合は同じ操作を繰り返さず、原因を調べてから対処する
- ルートクラス名はコードで確認してから使う（推測禁止）

---

## ■ 進捗一覧

### フェーズ1：Coordinator 残修正

| ID | 内容 | 対象ファイル | ステータス | 備考 |
|----|------|------------|-----------|------|
| R-01 | 案件一覧: 新規作成ボタンを `#headerExtras` へ、色統一 | `Coordinator/ProjectJobs/Index.vue` | ✅ 完了 | |
| R-02 | 案件作成: `#header` 修正・戻るボタン追加・ボタン整理 | `Coordinator/ProjectJobs/Create.vue` | ✅ 完了 | |
| R-03 | 案件編集: `#header` 修正・戻るボタン移動・ボタン整理 | `Coordinator/ProjectJobs/Edit.vue` | ✅ 完了 | |
| R-04 | ジョブ割り当て一覧: 検索ボタン色・`#header` 修正 | `Coordinator/ProjectJobs/JobAssign/Index.vue` | ✅ 完了 | |
| R-05 | ジョブ割り当て詳細: 戻るボタンを `#header` へ移動 | `Coordinator/ProjectJobs/JobAssign/Show.vue` | ✅ 完了 | |
| R-06 | 案件選択: 戻るボタンを `#header` へ移動 | `Coordinator/ProjectJobs/JobAssign/SelectProject.vue` | ✅ 完了 | |
| R-07 | 外注先3ページ: 戻るボタンスタイル統一 | `Coordinator/Subcontractors/{Show,Create,Edit}.vue` | ✅ 完了 | 3ファイル |
| R-08 | チームメンバー: `bg-blue-600` → `bg-indigo-600` | `Coordinator/ProjectTeamMembers/{Create,Index}.vue` | ✅ 完了 | 2ファイル |
| R-09 | スケジュールコメント作成: 戻る・保存ボタン修正 | `Coordinator/ProjectSchedules/Comments/Create.vue` | ✅ 完了 | |

### フェーズ2：User / JobBox / Events

| ID | 内容 | 対象ファイル | ステータス | 備考 |
|----|------|------------|-----------|------|
| R-10 | イベント詳細: `#header` 追加・ボタン整理 | `Events/Show.vue` | ✅ 完了 | |
| R-11 | イベント編集: `#header` 追加・max-w 削除 | `Events/Edit.vue` | ✅ 完了 | |
| R-12 | イベント作成: `#header` 追加・max-w 削除・ボタン色 | `Events/Create.vue` | ✅ 完了 | |
| R-13 | ジョブ作成: `#header` 追加・max-w 削除・ボタン色 | `Events/Create_Job.vue` | ✅ 完了 | |
| R-14 | ジョブ詳細: 戻る・編集・削除を `#header` / `#headerExtras` へ | `JobBox/Show.vue` | ✅ 完了 | |
| R-15 | ジョブスケジュール: 戻るを `#header` へ移動 | `JobBox/Schedule.vue` | ✅ 完了 | |
| R-16 | マイジョブ詳細: 戻るを `#header` へ移動・max-w 削除 | `MyJobBox/Show.vue` | ✅ 完了 | |
| R-17 | ジョブ通知: `#header` 追加・`bg-blue-600` 修正 | `JobNotifications/Index.vue` | ✅ 完了 | |

### フェーズ3：Admin

| ID | 内容 | 対象ファイル | ステータス | 備考 |
|----|------|------------|-----------|------|
| R-18 | ユーザー一覧: `bg-blue-600` → `bg-indigo-600` | `Admin/Users/Index.vue` | ✅ 完了 | |
| R-19 | チーム詳細: 戻るボタン修正・max-w 削除・ボタン色 | `Admin/Teams/Show.vue` | ✅ 完了 | |

### フェーズ4：Leader / Diaries / Messages

| ID | 内容 | 対象ファイル | ステータス | 備考 |
|----|------|------------|-----------|------|
| R-20 | 案件一覧: `mx-auto max-w-6xl` → `max-w-6xl` に修正 | `Leader/ProjectJobs/Index.vue` | ✅ 完了 | |
| R-21 | 日報一覧・詳細: ボタン色・戻るボタン修正 | `Diaries/Index.vue` / `Diaries/Show.vue` | ✅ 完了 | 2ファイル |
| R-22 | 日報インタラクション: `bg-blue-600` 修正 | `Diaries/Interactions/Index.vue` | ✅ 完了 | |
| R-23 | メッセージ詳細: 戻るボタンをボタンスタイルに | `Messages/Show.vue` | ✅ 完了 | |

### フェーズ5：ProofCoordinator / Proof / Prepress / Clerk

| ID | 内容 | 対象ファイル | ステータス | 備考 |
|----|------|------------|-----------|------|
| R-24 | 発注先詳細: 戻るボタンスタイル統一 | `ProofCoordinator/Dispatchers/Show.vue` | ✅ 完了 | |
| R-25 | 割り当て詳細: max-w 削除・戻るボタン確認 | `ProofCoordinator/Assignments/Show.vue` | ✅ 完了 | |

---

## ■ ステータス凡例

| 記号 | 意味 |
|------|------|
| 🔲 未着手 | まだ始めていない |
| 🔍 調査中 | コード調査・仕様確認中 |
| 📝 設計中 | 設計・方針をユーザーと確認中 |
| 🔨 実装中 | コード変更・ビルド中 |
| ✅ 完了 | ユーザー確認済み |
| ⏸ 保留 | 依存関係・仕様未定のため一時停止 |
| ❌ スキップ | 不要と判断、またはユーザー判断でスキップ |

---

## ■ 作業ログ

| 日付 | ID | 内容 | 対応者 |
|------|----|------|--------|
| 2026-05-02 | — | 計画書（LAYOUT_REPAIR_PLAN.md）・管理書・プロンプト作成 | Claude |
| 2026-05-02 | R-01 | Coordinator/ProjectJobs/Index.vue: #header修正・#headerExtras追加・bg-indigo統一 | Claude |
| 2026-05-02 | R-02 | Coordinator/ProjectJobs/Create.vue: #header修正・戻るボタン追加・mx-auto max-w-2xl追加・bg-indigo統一 | Claude |
| 2026-05-02 | R-03 | Coordinator/ProjectJobs/Edit.vue: #header修正・戻るボタン追加・mx-auto max-w-2xl追加・bg-indigo統一・フォームボタン整理 | Claude |
| 2026-05-02 | R-04 | Coordinator/ProjectJobs/JobAssign/Index.vue: #header修正・戻るボタン追加・bg-indigo統一・h1削除 | Claude |
| 2026-05-02 | R-05 | Coordinator/ProjectJobs/JobAssign/Show.vue: #header戻るボタン追加・#headerExtras追加(編集/削除/紐づけ)・h1削除 | Claude |
| 2026-05-02 | R-06 | JobAssign/SelectProject.vue: #header戻るボタン追加・h1削除・mx-auto max-w-2xl追加 | Claude |
| 2026-05-02 | 幅修正 | R-02/03 max-w-3xl→max-w-2xl・R-05/06 フルワイド→max-w-2xl・JobAssign/Edit.vue max-w-2xl追加 | Claude |
| 2026-05-02 | R-08 | ProjectTeamMembers/Create.vue: bg-indigo統一・max-w-2xl追加 / Index.vue: bg-indigo統一 | Claude |
| 2026-05-02 | R-09 | ProjectSchedules/Comments/Create.vue: 戻るを#headerにLink化・bg-indigo統一・goBack()削除 | Claude |
| 2026-05-02 | R-10 | Events/Show.vue: #header追加(戻る)・#headerExtras追加(編集/削除)・bg-indigo統一・カード内ボタン整理 | Claude |
| 2026-05-02 | R-11 | Events/Edit.vue: #header追加(戻る)・h1削除・bg-indigo統一・フォームボタン整理 | Claude |
| 2026-05-02 | R-12 | Events/Create.vue: #header追加(戻る)・h1+説明文削除・max-w-3xl→max-w-2xl・bg-indigo統一・フォームボタン整理 | Claude |
| 2026-05-02 | R-13 | Events/Create_Job.vue: #header追加(戻る)・#headerExtras追加(流用/依頼ボタン)・h1削除・max-w-3xl→max-w-2xl・bg-indigo統一 | Claude |
| 2026-05-02 | R-14 | JobBox/Show.vue: #header戻るボタン追加・#headerExtras追加(編集/削除)・bg-indigo統一・max-w-3xl→max-w-2xl | Claude |
| 2026-05-02 | R-15 | JobBox/Schedule.vue: #header戻るLink追加・フォーム内戻るボタン削除・bg-indigo統一 | Claude |
| 2026-05-02 | R-16 | MyJobBox/Show.vue: #header戻るLink追加・アクション行戻る削除・max-w-3xl削除・bg-indigo統一 | Claude |
| 2026-05-02 | R-17 | JobNotifications/Index.vue: #header追加・カード内h2削除・bg-indigo統一（適用/未読のみ） | Claude |
| 2026-05-03 | R-18 | Admin/Users/Index.vue: #headerExtras分離・bg-indigo統一 / UserTable.vue: 役職列追加・デフォルトソート変更 / UserController: positionTitle eager load | Claude |
| 2026-05-03 | 追加 | Admin/Users/Show.vue: #header標準化・max-w-2xl・役職表示追加 / Edit.vue: #header標準化・max-w-2xl / Create.vue: #header標準化・max-w-2xl | Claude |
| 2026-05-03 | 追加 | Admin/Teams/Create.vue・Edit.vue: max-w-3xl→max-w-2xl / JobBox/Schedule.vue・MyJobBox/Show.vue: flexラッパー追加（縦並び修正） | Claude |
| 2026-05-03 | R-19 | Admin/Teams/Show.vue: #header戻るLink追加・#headerExtras編集Link追加・max-w-4xl削除・goBack/goEdit削除 | Claude |
| 2026-05-03 | R-20 | Leader/ProjectJobs/Index.vue: mx-auto max-w-6xl削除・h1+説明文削除 | Claude |
| 2026-05-03 | R-21 | Diaries/Index.vue: h1削除・bg-indigo統一 / Diaries/Show.vue: #header標準化(戻るbutton+日報詳細)・#headerExtras追加(編集/削除)・カード内ボタン行削除 | Claude |
| 2026-05-03 | R-22 | Diaries/Interactions/Index.vue: bg-indigo統一(適用/全部既読/ページネーション) | Claude |
| 2026-05-03 | 追加 | Diaries/Interactions/Show.vue: #header戻るbutton追加・h1削除・一覧へボタン削除・bg-indigo統一 | Claude |
| 2026-05-03 | R-23 | Messages/Show.vue: Link import追加・#header戻るLink+h2標準化・#headerExtras削除ボタン移動・カード内ボタン行削除 | Claude |
| 2026-05-03 | R-24 | Dispatchers/Show.vue: #header戻るLink標準化・#headerExtras編集/削除ボタン移動 / Edit.vue・Create.vue: #header戻るLink標準化・mx-auto max-w-2xl追加 | Claude |
| 2026-05-03 | R-25 | Assignments/Show.vue: #header戻るLink追加・#headerExtras編集/開始/完了ボタン移動・mx-auto max-w-3xl削除・usePage不要import削除 | Claude |
| 2026-05-03 | 追加 | Inbox/Assign.vue・Assignments/Edit.vue: #header戻るボタンをテキストリンク→ボタンスタイルに・左寄せに変更 / Inbox/Assign.vue・Assignments/Edit.vue・Assignments/Show.vue: mx-auto max-w-3xl追加 | Claude |

---

## ■ 次の推奨作業

**現時点の推奨:** R-01（Coordinator/ProjectJobs/Index.vue）から開始。
変更が1ファイル・影響が局所的で確認しやすく、他の Coordinator ページ修正のウォームアップになる。

---

## ■ まとめ統計

| フェーズ | ID 数 | 対象ファイル数 | 主な修正内容 |
|--------|------|-------------|------------|
| フェーズ1（Coordinator残） | R-01〜R-09 | 13ファイル | #header修正・ボタン移動・色統一 |
| フェーズ2（User/JobBox/Events） | R-10〜R-17 | 8ファイル | #header追加・ボタン移動・max-w削除 |
| フェーズ3（Admin） | R-18〜R-19 | 2ファイル | 色統一・戻るボタン修正 |
| フェーズ4（Leader/Diaries/Messages） | R-20〜R-23 | 5ファイル | max-w削除・色統一・戻るボタン修正 |
| フェーズ5（ProofCoordinator等） | R-24〜R-25 | 2ファイル | 戻るボタン・max-w修正 |
| **合計** | **25件** | **約30ファイル** | |
