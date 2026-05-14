# SunBWork 作業管理書 — ゴーストユーザー（テストユーザー）機能 第1版
作成日: 2026-05-13
更新日: 2026-05-13

---

## この管理書の使い方

**ユーザーへ:**
- 各作業の開始時に「G-01 を始めましょう」などと Claude に伝えてください
- Claude は以下の「作業フロー」に従って安全に進めます
- 作業完了ごとに「進捗一覧」を更新します

**Claude へ（セッション開始時に必ず読むこと）:**
1. この管理書（GHOST_MANAGER1.md）を読む
2. `z_instructions/GHOST_PLAN1.md` を読む（詳細仕様・変更ファイル一覧）
3. `CLAUDE.md` を参照する（プロジェクト全体ルール）
4. 現在の進捗一覧を確認し、ユーザーに次の推奨作業を提示する
5. 以下の「作業フロー」に従って進める

---

## 参照ドキュメント

| ドキュメント | 内容 |
|-------------|------|
| `z_instructions/GHOST_PLAN1.md` | ゴーストユーザー機能の詳細仕様・変更ファイル一覧 |
| `z_instructions/GHOST1_PROMPT.md` | セッション開始用プロンプト |
| `CLAUDE.md` | プロジェクト全体のルール（必読） |

---

## 作業フロー（Claude はこの手順を厳守すること）

```
STEP 1: 計画書を読む
  → GHOST_PLAN1.md の該当項目を読み、仕様を把握する
  → 関連ファイルをコードで確認する（推測で作業しない）

STEP 2: 設計・方針の提示（ユーザーに確認を取る）
  → 変更ファイル一覧・変更内容の概要・影響範囲を提示
  → 不明点があれば1つずつ質問する（複数同時に質問しない）
  → ユーザーの「OK」を確認してから次のステップへ

STEP 3: 実装
  → 承認された設計に従って実装する
  → Vue/JS ファイルを変更したら必ず npm run build を実行
  → PHP のみの変更なら npm run build は不要
  → Artisan が必要な場合は docker compose exec 経由で実行

STEP 4: 動作確認の依頼
  → 変更内容を簡潔に報告する
  → ユーザーに動作確認をお願いする（「〜を確認してください」）

STEP 5: 完了記録
  → ユーザーから「OK」が出たら進捗一覧のステータスを「✅ 完了」に更新
  → 備考欄に変更したファイル名を記録
  → 次の推奨作業を提示して止まる（自動で次の作業に進まない）
```

### 安全ルール（必ず守ること）

- STEP 2 でユーザーの確認なしに実装を始めない
- 1つの作業が完了するまで次の作業に移らない
- エラーが出た場合は同じ操作を繰り返さず、原因を調べてから対処する
- 完了後は次の推奨作業を提示して止まる（自動進行しない）

---

## 進捗一覧

### フェーズ 1：DB・モデル基盤（最優先）

| ID | 内容 | ステータス | 備考 |
|----|------|-----------|------|
| G-01 | マイグレーション — users に is_ghost / ghost_owner_id / ghost_expires_at 追加 | ✅ 完了 | 2026_05_13_000001_add_ghost_columns_to_users_table.php |
| G-02 | User モデル — Global Scope・withGhosts()・リレーション追加 | ✅ 完了 | app/Models/User.php |

### フェーズ 2：ゴーストユーザー作成・管理

| ID | 内容 | ステータス | 備考 |
|----|------|-----------|------|
| G-03 | GhostUserController — 作成・削除・セッション切り替えエンドポイント | ✅ 完了 | GhostUserController.php・routes/web.php |
| G-04 | Coordinator ダッシュボード UI — ゴーストユーザー管理ページ | ✅ 完了 | GhostUsers/Index.vue・CoordinatorNavigationTabs.vue |

### フェーズ 3：セッション切り替え

| ID | 内容 | ステータス | 備考 |
|----|------|-----------|------|
| G-05 | セッション切り替えロジック（switch / exit） | ✅ 完了 | G-03 にて GhostUserController@switch/exit として実装済み |
| G-06 | GhostUserMiddleware — MyJobBox・JobBox 以外を 403 | ✅ 完了 | GhostUserMiddleware.php・bootstrap/app.php・routes/web.php |

### フェーズ 4：フロントエンド

| ID | 内容 | ステータス | 備考 |
|----|------|-----------|------|
| G-07 | AppLayout ゴーストモードバナー（テストモード中 表示・戻るボタン） | ✅ 完了 | AppLayout.vue・User.php（is_ghost boolean キャスト追加） |
| G-08 | 割り当て UI — ゴーストユーザーに [テスト] ラベル付きで末尾表示 | ✅ 完了 | ProjectJobAssignmentsController.php・CompositeJobAssignmentController.php・AssignmentForm.vue・CompositeAssignmentForm.vue |

### フェーズ 5：自動削除

| ID | 内容 | ステータス | 備考 |
|----|------|-----------|------|
| G-09 | 自動削除コマンド（DeleteExpiredGhostUsers）・スケジューラー登録 | ✅ 完了 | DeleteExpiredGhostUsers.php・routes/console.php |

### フェーズ 6：ゴースト権限拡張

| ID | 内容 | ステータス | 備考 |
|----|------|-----------|------|
| G-10 | GhostUserMiddleware 拡張 — 案件・進行表・自己割当アクセス許可 | ✅ 完了 | GhostUserMiddleware.php・ProjectTeamMember.php・ProjectJobAssignment.php・ProjectTeamMembersController.php・Create.vue |

---

## ステータス凡例

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

## 作業ログ

| 日付 | ID | 内容 | 対応者 |
|------|----|------|--------|
| 2026-05-13 | — | 計画書（GHOST_PLAN1.md）・管理書（GHOST_MANAGER1.md）・プロンプト（GHOST1_PROMPT.md）作成 | Claude |
| 2026-05-13 | G-01 | マイグレーション作成・migrate 実行完了 | Claude |
| 2026-05-13 | G-02 | User モデル Global Scope・withGhosts()・ghostOwner/ghostUsers リレーション追加 | Claude |
| 2026-05-13 | G-03 | GhostUserController（index/store/destroy/switch/exit）・ルート 5 本追加 | Claude |
| 2026-05-13 | G-04 | GhostUsers/Index.vue 新規作成・CoordinatorNavigationTabs にタブ追加 | Claude |
| 2026-05-13 | G-05 | G-03 にて実装済みとして完了扱い | Claude |
| 2026-05-13 | G-06 | GhostUserMiddleware 新規作成・エイリアス登録・メイン auth グループに適用 | Claude |
| 2026-05-13 | G-03(修正) | セッション切り替えバグ修正: auth:sanctum が shouldUse('sanctum') で Sanctum RequestGuard にコーディネーターをキャッシュするため、login() 後に Auth::guard('sanctum')->setUser($ghost) も必要 | Claude |
| 2026-05-13 | G-07 | AppLayout ゴーストモードバナー実装・is_ghost boolean キャスト追加 | Claude |
| 2026-05-13 | G-08 | 割り当て UI テストラベル実装（4ファイル変更） | Claude |
| 2026-05-13 | G-09 | DeleteExpiredGhostUsers コマンド作成・スケジューラー登録 | Claude |
| 2026-05-13 | G-10 | 権限拡張（案件・進行表・自己割当）・Global Scope 修正・チームメンバー登録でゴースト選択可能に | Claude |

---

## 次の推奨作業

**現時点の推奨:** G-01（マイグレーション）から着手。

DB 基盤（G-01 → G-02）が整ってから上位フェーズへ進むこと。  
推奨順: G-01 → G-02 → G-03 → G-04 → G-05 → G-06 → G-07 → G-08 → G-09
