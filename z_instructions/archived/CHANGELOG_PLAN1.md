# SunBWork 更新ログページ 設計書 第1版
作成日: 2026-05-23

---

## 概要・目的

一般ユーザー向けに「何が問題だったか・何が改善されたか」を伝えるチェンジログページを作成する。
2026年4月20日以降のすべての修繕・機能追加を対象とし、最新順に一覧と詳細を提供する。
また、このログはClaudeが修繕作業を行う際の参照資料としても機能する。

---

## データ設計

### `changelogs` テーブル（新設）

```sql
id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
version       VARCHAR(30)           -- 内部識別キー (例: 'repair-5', 'prepress')
title         VARCHAR(200)          -- 一般ユーザー向けタイトル
released_at   DATE                  -- 公開・完了日（ソートキー）
summary       TEXT                  -- 一覧ページ用の短い説明（2〜3文）
body          LONGTEXT              -- 詳細ページ用のHTML（問題・改善内容・設計ファイル・Claudeノート）
design_files  JSON                  -- 設計ファイル名の配列（管理者・Claude向け）
claude_notes  TEXT                  -- Claudeへの追加案内（アーカイブ先など）
created_at    TIMESTAMP
updated_at    TIMESTAMP
```

### ログエントリー一覧（seeder で投入、新しい順）

| # | version | title | released_at | 設計ファイル（design_files） |
|---|---------|-------|-------------|--------------------------|
| 1 | repair-5 | 案件・ジョブ・日報の不具合修正と機能改善 | 2026-05-23 | REPAIR_PLAN5.md, REPAIR_MANAGER5.md |
| 2 | repair-4 | スマートフォン・タブレット対応 | 2026-05-12 | REPAIR_PLAN4.md, REPAIR_MANAGER4.md |
| 3 | repair-3 | 工数・時間計算の精度改善 | 2026-05-09 | REPAIR_PLAN3.md, REPAIR_MANAGER3.md |
| 4 | repair-2 | 案件・ジョブ・UIの改善（第2版） | 2026-04-26 | REPAIR_PLAN2.md, REPAIR_MANAGER2.md |
| 5 | proof-jobs | 校正管理者のジョブ管理改善 | 2026-04-30 | PROOF_JOBS_PLAN.md, PROOF_JOBS_MANAGER.md |
| 6 | ui-state | 画面の状態が自動的に記憶されるようになった | 2026-04-29 | UI_STATE_PERSIST_PLAN.md, UI_STATE_PERSIST_MANAGER.md |
| 7 | prepress | 製版部署専用エリアの新設 | 2026-04-28 | PREPRESS_PLAN.md, PREPRESS_MANAGER.md, PREPRESS_PLAN2.md, PREPRESS_MANAGER2.md |
| 8 | progress-v2 | 進行管理表の全面刷新 | 2026-04-27 | PROGRESS_SHEET_V2_DESIGN.md, PROGRESS_SHEET_V2_MANAGER1.md, PROGRESS_SHEET_V2_MANAGER2.md, PROGRESS_SHEET_V2_MANAGER3.md |
| 9 | repair-1 | バグ修正・機能改善（第1版） | 2026-04-24 | REPAIR_PLAN.md, REPAIR_MANAGER.md |
| 10 | bulk-create | 案件の一括登録・複製機能の追加 | 2026-04-20 | BULK_PROJECT_CREATE_DESIGN.md, BULK_PROJECT_CREATE_PROPOSAL.md |

---

## body フィールドの構造（HTMLフォーマット）

各エントリーの `body` は以下の4セクションで構成する:

```html
<section class="cl-problem">
  <h3>背景・問題</h3>
  <p>何が起きていたか（一般ユーザー向け）</p>
</section>

<section class="cl-fix">
  <h3>改善・修正内容</h3>
  <ul>
    <li>修正項目1</li>
    <li>修正項目2</li>
  </ul>
</section>

<section class="cl-design-files">
  <!-- 管理者・Claude向け: ページ上では折りたたみ or 管理者のみ表示 -->
  <h3>関連設計ファイル（管理者・Claude参照用）</h3>
  <ul>
    <li>REPAIR_PLAN5.md</li>
    <li>REPAIR_MANAGER5.md</li>
  </ul>
</section>

<section class="cl-claude-notes">
  <!-- Claudeへの指示 -->
  <h3>Claudeへの案内</h3>
  <p>
    このエントリーに関する詳細は z_instructions/archived/ ディレクトリ内の
    REPAIR_PLAN5.md および REPAIR_MANAGER5.md を参照してください。
    各タスクの仕様・変更ファイル・作業ログが記録されています。
  </p>
</section>
```

---

## MVC ファイル構成

### マイグレーション
- `database/migrations/YYYY_MM_DD_create_changelogs_table.php`

### モデル
- `app/Models/Changelog.php`
  - `$fillable`: version, title, released_at, summary, body, design_files, claude_notes
  - `$casts`: design_files → array, released_at → date

### コントローラー
- `app/Http/Controllers/ChangelogController.php`
  - `index()`: Changelog::orderBy('released_at', 'desc')->get() → Inertia::render
  - `show(Changelog $changelog)`: Inertia::render

### シーダー
- `database/seeders/ChangelogSeeder.php`
  - 上記10エントリーを投入
  - `DatabaseSeeder.php` に `$this->call(ChangelogSeeder::class)` 追加

### ルート（routes/web.php）
```php
// auth ミドルウェア内
Route::get('/changelogs', [App\Http\Controllers\ChangelogController::class, 'index'])->name('changelogs.index');
Route::get('/changelogs/{changelog}', [App\Http\Controllers\ChangelogController::class, 'show'])->name('changelogs.show');
```

### Vue ページ
- `resources/js/Pages/Changelogs/Index.vue`
  - AppLayout使用
  - 各エントリーをカード形式で表示（タイトル・日付・summary）
  - クリックで show ページへ遷移
- `resources/js/Pages/Changelogs/Show.vue`
  - AppLayout使用
  - body を `v-html` で表示（DOMPurify でサニタイズ）
  - 設計ファイル欄は折りたたみ（デフォルト非表示）

### AppLayout への追加
```html
<!-- スクリプトツールの直後 (line ~415) に追加 -->
<div class="group relative">
    <Link :href="route('changelogs.index')" class="flex h-8 w-8 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-gray-700">
        <!-- ドキュメント時計アイコン (History/Changelog) -->
        <svg>...</svg>
    </Link>
    <div class="pointer-events-none absolute right-0 top-9 z-50 w-44 rounded-md bg-gray-800 px-3 py-2 text-xs text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100">
        <p class="font-medium">更新ログ</p>
        <p class="text-gray-300">機能追加・不具合修正の履歴</p>
    </div>
</div>
```

---

## アーカイブ計画

### アーカイブディレクトリ
```
z_instructions/archived/
```

### 移動対象ファイル（完了済みプランのみ）

**REPAIR系:**
- REPAIR_PLAN.md, REPAIR_MANAGER.md, REPAIR_PROMPT.md (REPAIR1)
- REPAIR_PLAN2.md, REPAIR_MANAGER2.md, REPAIR2_PROMPT.md
- REPAIR_PLAN3.md, REPAIR_MANAGER3.md, REPAIR3_PROMPT.md
- REPAIR_PLAN4.md, REPAIR_MANAGER4.md, REPAIR4_PROMPT.md
- REPAIR_PLAN5.md, REPAIR_MANAGER5.md, REPAIR5_PROMPT.md

**機能系:**
- PROGRESS_SHEET_V2_DESIGN.md, PROGRESS_SHEET_V2_MANAGER1.md, PROGRESS_SHEET_V2_MANAGER2.md, PROGRESS_SHEET_V2_MANAGER3.md, PROGRESS_SHEET_V2_PLAN1.md, PROGRESS_SHEET_V2_PLAN2.md, PROGRESS_SHEET_V2_PLAN3.md, PROGRESS_SHEET_V2_PROMPT.md
- PROGRESS_SHEET_V2_PROMPT.md
- LAYOUT_REPAIR_PLAN.md, LAYOUT_REPAIR_MANAGER.md, LAYOUT_REPAIR_PROMPT.md
- UI_STATE_PERSIST_PLAN.md, UI_STATE_PERSIST_MANAGER.md, UI_STATE_PERSIST_PROMPT.md
- PROOF_JOBS_PLAN.md, PROOF_JOBS_MANAGER.md, PROOF_JOBS_PROMPT.md
- PREPRESS_PLAN.md, PREPRESS_MANAGER.md, PREPRESS_PROMPT.md
- PREPRESS_PLAN2.md, PREPRESS_MANAGER2.md, PREPRESS2_PROMPT.md
- PREPRESS_BOARD_V2_DESIGN.md
- GHOST_PLAN1.md, GHOST_MANAGER1.md, GHOST1_PROMPT.md
- IRUKA_PLAN1.md, IRUKA_MANAGER1.md, IRUKA1_PROMPT.md
- PROCESS_PLAN1.md, PROCESS_MANAGER1.md, PROCESS1_PROMPT.md
- SCRIPT_PLAN1.md, SCRIPT_MANAGER1.md, SCRIPT1_PROMPT.md
- BULK_PROJECT_CREATE_DESIGN.md, BULK_PROJECT_CREATE_PROPOSAL.md, BULK_PROJECT_CREATE_PROMPT.md
- WORKFLOW_V2_PLAN1.md, WORKFLOW_V2_PLAN2.md, WORKFLOW_V2_PLAN3.md
- WORKFLOW_V2_MANAGER1.md, WORKFLOW_V2_MANAGER2.md, WORKFLOW_V2_MANAGER3.md
- WORKFLOW_V21_PROMPT.md, WORKFLOW_V22_PROMPT.md, WORKFLOW3_PROMPT.md
- CLIENTCODE_PLAN1.md, CLIENTCODE_MANAGER1.md, CLIENTCODE1_PROMPT.md
- GUIDE_PLAN.md, GUIDE_MANAGER.md, GUIDE_PROMPT.md
- PROGRESS_LINK_PLAN.md, PROGRESS_LINK_MANAGER.md, PROGRESS_LINK_PROMPT.md
- EVENT_RENEWAL_MANAGER.md, EVENT_RENEWAL_PROMPT.md
- CHANGELOG_SINCE_APR20.md, CHANGELOG_SINCE_APR20_OVERVIEW.md
- PROGRESS_SHEET_SPEC.md, RENAME_AND_ROW_EDIT_SPEC.md
- SPEC_EVENT_RENEWAL.md, COMPOSITE_JOB_SPEC.md, JOB_NOTIFICATION_SPEC.md
- LAYOUT_SPEC_V2.md, PROGRESS_SHEET_V2_DESIGN.md
- PROMPT_FOR_NEW_CLAUDE.md, PROMPT_FOR_NEW_CLAUDE2.md (旧プロンプト)
- G01_ITEM_DESIGN.md, PROOF_SYSTEM_DESIGN.md, PROOF_SYSTEM_GUIDE.md, PROOF_USER_SYSTEM_GUIDE.md
- OCR_REPORT_overview.md, OCR_TICKET_MANAGER.md
- userwants2.txt, userwantplan.txt, komoku01.txt, komoku02.txt
- LAYOUT_GUIDELINES.md (CONSOLIDATED_01に統合済み)
- CHANGELOG_PLAN1.md, CHANGELOG_MANAGER1.md, CHANGELOG1_PROMPT.md (本計画書 — 完了後に移動)

### z_instructions/ に残すファイル（恒久参照）
- CONSOLIDATED_01〜10_*.md (全10ファイル)
- CONSOLIDATED_SUMMARY.md
- DEPLOY_SAKURA.md
- JOB_FLOW_GUIDE.md
- SCRIPTS_SECTION_GUIDELINES.md
- GITHUB_CLI_AUTH.md
- userwantslist0519.txt (最新の要望リスト)
- newscript.md, newscriptplan.md (作業中か確認後)
- progresssheet.png, prepress_sample.csv (素材)
- archived/ (本ディレクトリ)

---

## タスク一覧

| ID | タスク | 変更ファイル | 難易度 |
|----|--------|------------|--------|
| CL-01 | マイグレーション作成 | 1 | 小 |
| CL-02 | Changelog モデル作成 | 1 | 小 |
| CL-03 | ChangelogController 作成 | 1 | 小 |
| CL-04 | ChangelogSeeder 作成（10エントリー本文記述込み） | 1 | 大 |
| CL-05 | DatabaseSeeder に追加 | 1 | 極小 |
| CL-06 | routes/web.php にルート追加 | 1 | 極小 |
| CL-07 | Changelogs/Index.vue 作成 | 1 | 中 |
| CL-08 | Changelogs/Show.vue 作成 | 1 | 中 |
| CL-09 | AppLayout にボタン追加 | 1 | 小 |
| CL-10 | ziggy 再生成 + npm run build | — | 極小 |
| CL-11 | php artisan db:seed ChangelogSeeder | — | 極小 |
| CL-12 | archived/ ディレクトリ作成・ファイル移動 | — | 小 |
