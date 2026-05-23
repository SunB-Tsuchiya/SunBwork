# CONSUPDATE 作業計画書 第1版 — CONSOLIDATED ファイル更新
作成日: 2026-05-23

---

## 概要

`z_instructions/CONSOLIDATED_*.md` の内容を 2026-04-20 以降の変更・追加機能に合わせて更新する。
また、レイアウト関連は LAYOUT_GUIDELINES / LAYOUT_SPEC_V2（どちらも archived に移動済み）を参照しないよう書き直す。

**原則: 古い backup ファイルの内容を引き継がない。CLAUDE.md と現状コードが正とする。**

---

## 変更対象ファイル一覧

| ファイル | 変更規模 | 内容 |
|---------|---------|------|
| `CONSOLIDATED_01_layout_and_ui.md` | ★ 全面書き直し | AppLayout パターン修正・ボタン配置・スクリプト/更新ログ追加 |
| `CONSOLIDATED_05_calendar_and_jobbox.md` | ★ 中規模追記 | UTC/JST Trait・TimelineDiary 編集機能・新イベントルート |
| `CONSOLIDATED_09_domain_rules.md` | ★ 大規模追記 | イルカボード・ゴーストユーザー・更新ログ・工程シート・スクリプト・clientCode |
| `CONSOLIDATED_07_workload_and_handover.md` | 軽微修正 | first_prompt.md 参照を CLAUDE.md に変更 |
| `CONSOLIDATED_SUMMARY.md` | 軽微追記 | 新機能の要点を追加 |

---

## 詳細仕様

### CONSOLIDATED_01: レイアウト・UI（全面書き直し）

**削除する内容:**
- `backups/` 配下の旧ファイルへの参照（layout_guideline_for_ai_agent.md, layout_and_ui_unification_spec_for_ai_agent.md）
- 誤った AppLayout 構造 (`py-12 > max-w-7xl` をページ側が書くという記述)
- 「次のアクション」などの曖昧な将来予測

**追加・修正する内容:**
1. **AppLayout 正しいパターン:**
   - `py-12 > max-w-7xl` は AppLayout 内部が提供。ページ側は `<div class="rounded bg-white p-6 shadow">` をデフォルトスロットに直接入れる
   - NG: ページ側で `<div class="py-12"><div class="max-w-7xl mx-auto">...` と書く
2. **スロット一覧:** `#header` / `#headerExtras` / `#tabs` / デフォルト
3. **戻るボタン配置（L-02 標準）:**
   - `#header` スロット内 `div.flex.items-center.gap-3` に `<Link class="rounded bg-gray-200 px-3 py-1.5 ...">← 戻る</Link>` + `<h2>` を並べる
4. **AppLayout ヘッダー右ボタン:** スクリプト（スパナアイコン）+ 更新ログ（時計アイコン）が追加済み
5. **ロール別カラー:** SuperAdmin=黄 / Admin=赤 / Leader=オレンジ / Coordinator=緑 / Clerk=紫 / User=青
6. **ToastUnified:** AppLayout 内グローバル配置済み。ページ側で重複させない
7. **NG/OK コード例:** py-12 二重ラップ、main タグ使用など

---

### CONSOLIDATED_05: カレンダー・JobBox（中規模追記）

**追記セクション:**
1. **UTC/JST 混在ルール（`CalculatesEventTime` Trait）:**
   - 通常イベント: JST 文字列保存 → `Carbon::parse()` でそのまま JST
   - proof ジョブイベント: UTC 保存 → `resolveJstCarbon($event, 'starts_at')` を使う
   - トレイトパス: `app/Http/Controllers/Concerns/CalculatesEventTime.php`
2. **TimelineDiary 編集機能（R5-16, 2026-05-23）:**
   - `:editable` prop で編集可否切り替え
   - `@update:events` → `PUT /events/{id}/calendar` (route: `events.update_from_calendar`)
   - `@open-edit` / `@open-create` → router.visit でダイアリー編集/作成ページへ遷移
   - Edit.vue: `watch(() => form.date, fetchDayEvents)` で日付変更時に再取得
3. **新イベントルート種別:**
   - `client-event` / `internal-event` が追加（proof に加えて）
4. **NormalizesCsvEncoding Trait（CSV インポート共通）:**
   - Shift-JIS + CRLF + BOM 対応。CLAUDE.md と重複するが重要なので簡潔に記載

---

### CONSOLIDATED_09: ドメインルール（大規模追記）

**追記セクション（9件）:**

1. **イルカボード（在籍ボード, 2026-05-15）:**
   - テーブル: `user_presence_statuses`
   - カラム: `user_id`, `status`(in_office/remote/out/off), `status_detail`(text), `updated_at`
   - コントローラ: `app/Http/Controllers/User/UserPresenceController.php`
   - Vue: `resources/js/Pages/User/IrukaBoard/Index.vue`
   - ルート: `user.iruka_board.index` / `user.presence.update`
   - カレンダー: FullCalendar の eventContent で在籍状況バッジを表示

2. **ゴーストユーザー（2026-05-13）:**
   - テーブル: `users.is_ghost` (boolean, default false) / `users.ghost_owner_id` (FK → users.id, nullable)
   - 用途: テスト用・仮ユーザー。通常の一覧・割当から除外
   - コントローラ: `Admin/UserController` に `ghost_owner_id` 経由管理
   - フロント: `Admin/Users/Index.vue` にゴーストユーザー表示トグル

3. **更新ログ（Changelog, 2026-05-23）:**
   - テーブル: `changelogs`（version unique / title / released_at / summary / body(longText) / design_files(JSON) / claude_notes）
   - モデル: `app/Models/Changelog.php`
   - コントローラ: `app/Http/Controllers/ChangelogController.php`（index/show のみ、認証不要）
   - Seeder: `ChangelogSeeder`（`updateOrCreate(['version' => ...])`で冪等性確保）
   - Vue: `Pages/Changelogs/Index.vue` / `Pages/Changelogs/Show.vue`
   - ルート: `changelogs.index` / `changelogs.show`
   - **SuperAdmin のみ:** `auth.user.isSuperAdmin` が true の場合、`design_files` 折りたたみセクションを表示
   - Claude 参照指示: 概要・詳細を読み、必要なら `z_instructions/archived/` 内の設計ファイルを読む

4. **工程シート（WorkflowSheets / Process, 2026-05-14）:**
   - テーブル: `workflow_sheets` / `workflow_sheet_rows` / `workflow_sheet_cells`
   - コントローラ: `Coordinator/WorkflowSheetController.php`
   - Vue: `Pages/Coordinator/WorkflowSheets/`

5. **スクリプトセクション（2026-05-16）:**
   - `auth.canAccessScripts`（`auth.user` 配下ではなく `auth` 直下）でアイコン表示制御
   - ツールコンポーネント: `resources/js/Components/Scripts/` に配置
   - `Show.vue` の `componentMap` にキーを登録
   - 実装規約: `z_instructions/SCRIPTS_SECTION_GUIDELINES.md`

6. **クライアント ID（client_code, 2026-05-21）:**
   - `clients.client_code`（varchar20, nullable, unique）— さくら本番 Migration 済み
   - CSV インポート時に既存クライアントと突合するキー
   - `clients.is_registered`（boolean）— CSV 登録フロー管理フラグ

7. **製版ボード（Prepress Board, 2026-04-28）:**
   - `resources/js/Pages/Coordinator/PrepressBoard/` に配置
   - ルート: `coordinator.prepress_board.*`

8. **一括案件登録（BulkCreate, 2026-04-20）:**
   - `resources/js/Pages/Coordinator/BulkCreate/Index.vue`
   - ルート: `coordinator.project_jobs.bulk_create`

9. **ProgressSheet v2（2026-04-27）:**
   - JobLink セル / User 型セルのロック連動（すでに `CONSOLIDATED_09` の既存セクションに記載あり）
   - 内容は既存セクションで十分

---

### CONSOLIDATED_07: ワークロード（軽微修正）

- `first_prompt.md` への参照を削除（archived に移動済み）
- 「CLAUDE.md および CONSOLIDATED_01 を参照」に変更

---

### CONSOLIDATED_SUMMARY.md（軽微追記）

- 2026-04-20 以降の新機能を箇条書きで追記:
  - イルカボード / ゴーストユーザー / 更新ログ / 工程シート / スクリプトセクション / クライアントID / 製版ボード

---

## 変更ファイル一覧

```
z_instructions/CONSOLIDATED_01_layout_and_ui.md       (全面書き直し)
z_instructions/CONSOLIDATED_05_calendar_and_jobbox.md (追記)
z_instructions/CONSOLIDATED_07_workload_and_handover.md (軽微修正)
z_instructions/CONSOLIDATED_09_domain_rules.md        (大規模追記)
z_instructions/CONSOLIDATED_SUMMARY.md                (追記)
z_instructions/CONSUPDATE_PLAN1.md                    (本ファイル)
z_instructions/CONSUPDATE_MANAGER1.md                 (進捗管理)
z_instructions/CONSUPDATE1_PROMPT.md                  (新セッション用)
```

---

## 注意事項

- `z_instructions/backups/` や `z_instructions/archived/` のファイルを参照・引用しない
- CLAUDE.md の内容と矛盾しない（CLAUDE.md が最上位の権威）
- CONSOLIDATED ファイルはリファレンス文書。コード実装例は最小限にとどめる
