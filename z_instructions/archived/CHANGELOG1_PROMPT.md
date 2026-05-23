# CHANGELOG1 — 新セッション開始プロンプト

---

## このセッションでやること

SunBWork（Laravel 11 / Vue 3 / Inertia.js）に「更新ログ（チェンジログ）ページ」を実装する。
一般ユーザーが「何が問題だったか・何が改善されたか」を確認できるページ。
Claudeが修繕作業の参照資料としても利用できる構造にする。

---

## 必ず最初に読むドキュメント

1. `z_instructions/CHANGELOG_PLAN1.md` — DBスキーマ・エントリー一覧・MVC構成・アーカイブ計画
2. `z_instructions/CHANGELOG_MANAGER1.md` — 進捗管理・作業フロー
3. `CLAUDE.md` — プロジェクト全体ルール（必読）

---

## 設計サマリー

### DB
- テーブル: `changelogs`
- 主要カラム: `version`, `title`, `released_at`, `summary`, `body`(HTML), `design_files`(JSON), `claude_notes`
- 10エントリーを ChangelogSeeder で投入（repair-5 〜 bulk-create）

### MVC
- Model: `app/Models/Changelog.php`
- Controller: `app/Http/Controllers/ChangelogController.php`（index, show）
- Vue: `resources/js/Pages/Changelogs/Index.vue`（カード一覧）/ `Show.vue`（詳細）
- Route: `changelogs.index` / `changelogs.show`

### UI
- AppLayout のスクリプトボタン右に「更新ログ」ボタン追加
- Index: 最新順カード一覧（タイトル・日付・概要）
- Show: 背景・問題 → 改善内容 → 設計ファイル（折りたたみ）→ Claudeへの案内

### アーカイブ
- ログ実装完了後、完了済みプランファイルを `z_instructions/archived/` に移動
- CHANGELOG_PLAN1.md の「移動対象ファイル」リストに従う

---

## 進捗確認

CHANGELOG_MANAGER1.md の進捗一覧で未着手（🔲）のタスクを最初に確認し、
CL-01 から順に進める。完了後は管理書を更新する。

---

## Claudeへの重要注意事項

- Seeder の body フィールドには HTML を書く（`v-html` でレンダリングするため）
- 各エントリーの内容は `CHANGELOG_PLAN1.md` のエントリー一覧 + 各 REPAIR_MANAGER*.md を参照して作成
- アーカイブ移動は実装完了・動作確認後に実施する
- 詳細な修繕内容は `z_instructions/archived/` の各 REPAIR_PLAN*.md / REPAIR_MANAGER*.md に保管
