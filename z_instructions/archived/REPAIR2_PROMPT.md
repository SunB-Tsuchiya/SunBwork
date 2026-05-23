# SunBWork 修繕第2版 Claude向けプロンプトファイル
作成日: 2026-04-26

---

## このファイルの使い方

新しい Claude セッションを開始するとき、このファイルの内容をそのまま冒頭に貼り付けてください。
または「REPAIR2_PROMPT.md を読んで実装を続けてください」と指示してください。

---

## セッション開始時のプロンプト（コピーして使用）

```
あなたはこれからSunBWorkプロジェクトの修繕作業（第2版）を行います。

まず以下のファイルを必ず順番に読んでください：
1. `/home/tchirosb/SunBWork/CLAUDE.md`（プロジェクト全体ルール・必読）
2. `/home/tchirosb/SunBWork/z_instructions/REPAIR_MANAGER2.md`（作業管理書・フロー・進捗一覧）
3. `/home/tchirosb/SunBWork/z_instructions/REPAIR_PLAN2.md`（各作業の詳細仕様）
4. `/home/tchirosb/SunBWork/z_instructions/REPAIR_MANAGER.md`（第1版・完了済み項目の参照用）

読み終えたら、以下を報告してください：
- 現在の進捗状況（未着手・進行中・完了の件数）
- 次に着手すべき推奨作業（理由も添えて）

作業は REPAIR_MANAGER2.md に記載された「作業フロー（5ステップ）」と「安全ルール」に従って進めてください。
特に STEP 2（設計・方針の提示）でユーザーの「OK」を得るまで絶対に実装を始めないこと。

各N-xx作業の完了・進捗状況は必ず REPAIR_MANAGER2.md に記録してください：
- 作業完了時: 進捗一覧のステータスを「✅ 完了」に更新し、作業ログに変更ファイルを記録
- ビルド成功・ユーザー確認待ちの場合: ステータスを「🔨 実装中」に更新
```

---

## 設計サマリー（Claude向け補足）

### プロジェクト背景

- **業種:** 印刷・組版会社向け社内管理システム（Laravel 11 + Vue 3 + Inertia.js）
- **目的:** ユーザーからの改善希望（userwants2.txt）をもとに、バグ修正・UI改善・機能追加を行う
- **前提:** 修繕計画第1版（B/L/F/G/Vフェーズ）はすべて完了済み

### 最重要ルール（CLAUDE.md より）

1. 作業前に必ず関連コードを読む
2. 設計提示 → ユーザー確認 → 実装の順を守る
3. 質問は1つずつ
4. Vue/JSファイル変更後は `npm run build`（プロジェクトルートで実行）
5. Artisan は `docker compose exec laravel bash -lc "php artisan ..."`
6. さくら本番では `route()` 必須・ハードコードパス禁止

### 作業ID一覧

| フェーズ | ID | 内容（短縮） |
|--------|-----|------------|
| バグ修正 | N-06 | ユーザーカレンダーのevent削除 500エラー＋Coordinator非同期 |
| バグ修正 | N-07 | ジョブ履歴削除後のリダイレクト先を案件詳細ジョブ履歴タブに |
| バグ修正 | N-09 | ジョブステータス表示の全ページ統一（4段階基準） |
| バグ修正 | N-10 | 「戻る」ボタン不動のページ調査・修正 |
| UI改善  | N-01 | ジョブ履歴を初期展開表示に |
| UI改善  | N-02 | 割り振り開始時刻を現在時刻（5分刻み）に |
| UI改善  | N-05 | 案件詳細タブにスケジュールタブを独立追加 |
| UI改善  | N-11 | カレンダーCSV出力ファイル名に案件名を含める |
| UI改善  | N-12 | 進行管理表の行をクリックで開けるように |
| 機能改善 | N-03 | ジョブタイトル命名規則をアンダーバーに統一 |
| 機能改善 | N-04 | 「詳細を見る（進行表へ）」遷移先改善（複数シート→モーダル） |
| 機能改善 | N-08 | ジョブ一覧グループ表示記憶（DB）＋Coordinator設定タブ新設 |
| 将来計画 | GUIDE-01 | ガイド全面書き換え（全修繕完了後に別計画で実施） |

### 第1版からの引き継ぎ事項（重要）

- **F-01**（4段階ステータス実装済み）→ N-09 の統一基準として使用すること
  - 「未読 / 確認済み / セット済み / 完了」の4段階が正しい
- **L-02**（ガイドライン適用済みページ）→ N-10 の「戻る」調査でガイドライン適用済みページは確認済みとして扱ってよい
- **F-07**（`?tab=` パラメータ方式）→ N-07 のリダイレクト実装で同じパターンを流用する
- **F-08**（スケジュール直接入力を概要タブに実装済み）→ N-05 でスケジュールタブに移動する際に注意

### N-08 の DB 設計（事前確認済み）

```
テーブル名: coordinator_settings
カラム:
  id                 bigint unsigned auto_increment
  user_id            bigint unsigned NOT NULL FK→users
  jobbox_group_mode  varchar(20) DEFAULT 'date'  ← 'date' / 'client' / 'project'
  jobbox_default_tab varchar(50) DEFAULT ''
  created_at         timestamp
  updated_at         timestamp
```

Coordinator ナビゲーションに「設定」タブを追加し、設定ページ（`Coordinator/Settings/Index.vue`）を新規作成する。

### N-10 の「戻る」ボタン確認チェックリスト

調査優先順（Events/Show.vue が特に問題と報告あり）：
1. `Pages/Events/Show.vue` ← 最優先
2. `Pages/MyJobBox/Show.vue`
3. その他 Show 系ページ
4. `Pages/JobBox/Show.vue` は動作確認済みでスキップ可

### よくある落とし穴（過去の修正から）

- さくら本番に存在しないカラムを `update()` に含めると壊れる → `Arr::pull()` で除去
- `project_jobs.schedule` カラムはさくらに存在しない
- Coordinator のルート名には必ず `coordinator.` プレフィックスが必要
- `route()` の第2引数はオブジェクト形式: `route('coordinator.project_jobs.show', { projectJob: id })`
- さくら上の CSRF は `document.querySelector('meta[name="csrf-token"]')` から取得（クッキーは使わない）

### 主要ファイルパス（よく触るもの）

```
app/Http/Controllers/EventController.php
app/Http/Controllers/Coordinator/ProjectJobAssignmentsController.php
app/Http/Controllers/Coordinator/ProjectSchedulesController.php
resources/js/Pages/Coordinator/ProjectJobs/Show.vue          ← N-01/N-05/N-07/N-09/N-12
resources/js/Pages/Coordinator/ProjectJobs/JobAssign/AssignmentForm.vue  ← N-02
resources/js/Pages/Coordinator/JobBox/Index.vue              ← N-08/N-09
resources/js/Pages/JobBox/Index.vue                          ← N-09
resources/js/Pages/Events/Show.vue                           ← N-10
resources/js/Pages/Coordinator/ProjectSchedules/Calendar.vue ← N-11
routes/web.php
```
