# SunBWork ゴーストユーザー機能 第1版 — Claude 向けプロンプトファイル
作成日: 2026-05-13

---

## このファイルの使い方

新しい Claude セッションを開始するとき、以下のプロンプトをそのまま貼り付けるか、
「GHOST1_PROMPT.md を読んでゴーストユーザー機能の作業を続けてください」と指示してください。

---

## セッション開始時のプロンプト（コピーして使用）

```
あなたはこれからSunBWorkプロジェクトのゴーストユーザー（テストユーザー）機能を実装します。

まず以下のファイルを必ず順番に読んでください：
1. `/home/tchirosb/SunBWork/CLAUDE.md`（プロジェクト全体ルール・必読）
2. `/home/tchirosb/SunBWork/z_instructions/GHOST_MANAGER1.md`（作業管理書・フロー・進捗一覧）
3. `/home/tchirosb/SunBWork/z_instructions/GHOST_PLAN1.md`（各作業の詳細仕様・変更ファイル一覧）

読み終えたら、以下を報告してください：
- 現在の進捗状況（未着手・進行中・完了の件数）
- 次に着手すべき推奨作業（理由も添えて）

作業は GHOST_MANAGER1.md に記載された「作業フロー（5ステップ）」と「安全ルール」に従って進めてください。
特に STEP 2（設計・方針の提示）でユーザーの「OK」を得るまで絶対に実装を始めないこと。

各G-xx作業の完了・進捗状況は必ず GHOST_MANAGER1.md の進捗一覧に記録してください。
```

---

## 設計サマリー（Claude 向け補足）

### この機能の背景

Coordinator がユーザーとのジョブのやり取りをシミュレートするためのゴーストユーザー機能。
正規ユーザーリストを汚さず、Coordinator にのみ紐づく一時的なテストユーザーを作る。

**重要:** 別ログインページは作らない。Coordinator のセッション内で
`Auth::loginUsingId()` を使ってセッション切り替えを行う方式。

### 実装の核心

| ポイント | 実装方針 |
|---|---|
| ゴースト非表示 | User モデル Global Scope で `where('is_ghost', false)` を全クエリに強制 |
| セッション切り替え | `session('ghost_return_user_id')` に元 ID を保存 → `Auth::loginUsingId()` |
| 権限制限 | GhostUserMiddleware で MyJobBox・JobBox 以外を 403 |
| 割り当て UI | `withGhosts()->where('ghost_owner_id', auth()->id())` で自分のゴーストのみ取得し `[テスト]` ラベル付与 |
| 自動削除 | `ghost_expires_at < now()` をスケジューラーで毎日チェック |

### 依存関係（作業順序の根拠）

```
G-01（マイグレーション）
  ↓
G-02（User モデル: Global Scope が全後続フェーズの前提）
  ↓
G-03（Controller: store/destroy/switch/exit）
  ↓
G-04（UI: 管理ページ） ← G-05（セッション切り替え実装） ← 並行可
  ↓
G-06（Middleware: 権限制限）
  ↓
G-07（AppLayout バナー） ← G-08（割り当て UI テストラベル） ← 並行可
  ↓
G-09（自動削除コマンド）
```

### よくある落とし穴

- `withoutGlobalScope('no_ghost')` を忘れると Coordinator 切り替え時にゴーストが取得できない
- `Auth::loginUsingId()` はセッションを再生成するため、`ghost_return_user_id` は **切り替え前に** セッションに保存すること
- ゴーストユーザーの email に `@ghost.local` を使うため、Mailable 送信前に `str_ends_with($user->email, '@ghost.local')` でガードを追加する
- Global Scope の追加は既存のすべてのユーザー取得クエリに影響するため、G-02 完了後は Admin・Leader 系ページで意図しない絞り込みが起きていないか確認すること

### 主要ファイルパス

```
app/Models/User.php
app/Http/Controllers/Coordinator/GhostUserController.php（新規）
app/Http/Middleware/GhostUserMiddleware.php（新規）
app/Console/Commands/DeleteExpiredGhostUsers.php（新規）
resources/js/layouts/AppLayout.vue
resources/js/Pages/Coordinator/GhostUsers/Index.vue（新規）
routes/web.php
routes/console.php
```
