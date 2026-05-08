# SunBWork ガイド改定計画書
作成日: 2026-05-06

---

## 作業方針

1. 各ロールのガイドページを現状の機能・タブメニューに合わせて全面改定する
2. タブメニューに表示されている機能のみ記載する（AppLayout.vue でコメントアウトされているものは記載しない）
3. 記載順はタブメニューの順番に合わせる
4. ProofAdmin ガイドは新規作成（ルート・コントローラーメソッド・Vue ファイルを追加）
5. 作業は GUIDE_MANAGER.md の作業フロー（5ステップ）に従って進める

---

## 共通ルール（全ガイドに適用）

### スタイル・構成
- 既存ガイドのデザインフォーマットを踏襲する（ヒーローバナー → もくじ → セクション → 戻るボタン）
- 各ロールのカラーを使用する:
  - Admin = red（`from-red-500 to-rose-400`）
  - Leader = orange（`from-orange-500 to-amber-400`）
  - Coordinator = green（`from-green-500 to-emerald-400`）
  - ProofAdmin = pink（`from-pink-500 to-rose-400`）
  - User = blue（`from-blue-500 to-sky-400`）
- ヒーローバナー上部に「← ガイド一覧」リンクを `#header` スロットに配置（既存のパターンを踏襲）
- 各セクションには `id` 属性と `scroll-mt-16` を付けてもくじのアンカーリンクが機能するようにする

### 内容方針
- 読者は社内のスタッフ（操作マニュアルとして使用）
- ですます調で記述
- 「何ができるか」→「どう操作するか」の順で書く
- 権限によって表示されないタブは「部署リーダーのみ表示」などの注釈を入れる
- **メッセージ・チャット・AIチャットは現在使用していないため全ガイドで記載しない**

---

## GUIDE-U-01：User ガイド改定

### 現状の問題点
- 「マイジョブ」と記載しているが、現在のタブ名は「マイジョブBOX」
- 「予定表」と記載しているが、現在のタブ名は「カレンダー」
- ステータス表示が現在の4段階（未読/確認済み/セット済み/完了）と一致していない（「受信済み」「進行中」等の古い名称）
- 「校正状況」「校正ジョブ」タブの説明がない

### 現在のタブ構成（掲載順）

| # | タブ名 | ルート | 備考 |
|---|--------|--------|------|
| 1 | 案件確認 | `user.project_jobs.index` | |
| 2 | マイジョブBOX | `user.myjobbox.index` | |
| 3 | 依頼されたジョブ | `user.jobbox.index` | |
| 4 | 日報一覧 | `diaries.index` | |
| 5 | カレンダー | `calendar.index` | |
| 6 | 校正状況 | `user.proof.status` | |
| 7 | 校正ジョブ | `user.proof_jobs.index` | 校正メンバーのみ表示 |
| 8 | 設定 | `user.settings.index` | |

### セクション構成（新版）

1. SunBWork とは（概要・タブメニュー一覧）
2. 基本的な使い方の流れ
3. 案件確認・進行管理表
4. マイジョブBOX（自分でジョブを登録する）
5. 依頼されたジョブ（コーディネーターからの割り当て）
6. 日報一覧（日報を書く）
7. カレンダー（スケジュール管理）
8. 校正状況
9. 校正ジョブ（校正メンバーのみ）
10. 設定（勤務形態）

### 主要修正点

- ステータス名を4段階に統一: **未読 / 確認済み / セット済み / 完了**
- タブ名を現状に合わせて修正（マイジョブBOX・カレンダー）
- 「校正状況」：校正依頼の確認・返答方法を追加
- 「校正ジョブ」：校正メンバーのみ表示である旨を明記して操作説明を追加
- 「案件確認」を冒頭に移動（タブ順に合わせる）

**変更対象ファイル:**
- `resources/js/Pages/Guide/User.vue`

---

## GUIDE-A-01：Admin ガイド改定

### 現状の問題点
- 旧機能（ガントチャート等）が記載されている可能性あり
- 最近追加された「案件総覧」「会議設定」「Leader権限管理」等の説明が不足または未掲載

### 現在のタブ構成（掲載順）

| # | タブ名 | ルート | 備考 |
|---|--------|--------|------|
| 1 | 案件総覧 | `admin.project_jobs.index` | |
| 2 | 会社管理 | `admin.companies.index` | 権限 `company_management` |
| 3 | ユーザー管理 | `admin.users.index` | 権限 `user_management` |
| 4 | 部署管理 | `admin.teams.index` | 権限 `team_management` |
| 5 | 日報管理 | `admin.diaryinteractions.index` | 権限 `diary_management` |
| 6 | クライアント管理 | `admin.clients.index` | 権限 `client_management` |
| 7 | 作業量分析 | `admin.workload_analyzer.index` | 権限 `workload_analysis` |
| 8 | 勤務形態設定 | `admin.worktypes.index` | 権限 `worktype_setting` |
| 9 | 勤務時間管理 | `admin.work_records.index` | 権限 `work_record_management` |
| 10 | Admin権限管理 | `admin.admin_permissions.index` | 代表者のみ表示 |
| 11 | Leader権限管理 | `admin.leader_permissions.index` | |
| 12 | 会議設定 | `admin.meeting_definitions.index` | |

### セクション構成（新版）

1. Admin とは（概要・権限タブについて）
2. 案件総覧
3. 会社管理
4. ユーザー管理（招待・編集・ロール変更）
5. 部署管理
6. 日報管理
7. クライアント管理
8. 作業量分析
9. 勤務形態設定
10. 勤務時間管理
11. Admin権限管理（代表者のみ）
12. Leader権限管理
13. 会議設定

### 主要修正点

- 「Admin権限管理」は代表者のみ表示される旨を明記
- 各タブが権限設定によって非表示になることを冒頭に説明
- 「会議設定」の説明を追加（メンバー選択モーダルの操作含む）

**変更対象ファイル:**
- `resources/js/Pages/Guide/Admin.vue`

---

## GUIDE-L-01：Leader ガイド改定

### 現状の問題点
- 部署リーダーのみ表示されるタブ（ユーザー管理・案件総覧）について明確な説明がない可能性がある
- 「派遣管理」「作業項目設定」「勤務時間管理」等の最近追加されたタブの説明が不足

### 現在のタブ構成（掲載順）

| # | タブ名 | ルート | 備考 |
|---|--------|--------|------|
| 1 | ユーザー管理 | `leader.user_management.index` | 部署リーダーのみ表示 |
| 2 | 案件総覧 | `leader.project_jobs.index` | 部署リーダーまたはAdmin以上のみ |
| 3 | チーム管理 | `leader.teams.index` | |
| 4 | クライアント管理 | `leader.clients.index` | 権限 `client_management` |
| 5 | 日報管理 | `leader.diaryinteractions.index` | 権限 `diary_management` |
| 6 | 作業量分析 | `leader.workload_analyzer.index` | 権限 `workload_analysis` |
| 7 | 作業項目設定 | `workload_setting.index` | 権限 `workload_setting` |
| 8 | 勤務時間管理 | `leader.work_records.index` | 権限 `work_record_management` |
| 9 | 派遣管理 | `leader.dispatch_management.index` | 権限 `dispatch_management` |
| 10 | Leader権限管理 | `leader.leader_permissions.index` | |
| 11 | 会議設定 | `leader.meeting_definitions.index` | |

### セクション構成（新版）

1. Leader とは（概要・権限とタブの可視性）
2. ユーザー管理（部署リーダーのみ）
3. 案件総覧（部署リーダー・Admin以上のみ）
4. チーム管理
5. クライアント管理
6. 日報管理
7. 作業量分析
8. 作業項目設定
9. 勤務時間管理
10. 派遣管理
11. Leader権限管理
12. 会議設定

### 主要修正点

- 冒頭に「タブの可視性は権限と役割によって異なる」旨を説明
- 「ユーザー管理」「案件総覧」が部署リーダーのみ表示であることを明記
- 「作業項目設定」「派遣管理」「勤務時間管理」の説明を追加

**変更対象ファイル:**
- `resources/js/Pages/Guide/Leader.vue`

---

## GUIDE-C-01：Coordinator ガイド改定

### 現状の問題点
- 「ガントチャート」など未実装機能への言及が残っている可能性がある
- 最近追加された「進行表一覧」「進行レポート」「設定」タブの説明がない
- 案件詳細内のタブ構成（概要・進行管理表・スケジュール・連携設定・ジョブ履歴）の変更が反映されていない
- ジョブステータスの4段階（未読/確認済み/セット済み/完了）が未反映

### 現在のタブ構成（掲載順）

| # | タブ名 | ルート | 備考 |
|---|--------|--------|------|
| 1 | クライアント管理 | `coordinator.clients.index` | |
| 2 | 外注先管理 | `coordinator.subcontractors.index` | |
| 3 | 案件一覧 | `coordinator.project_jobs.index` | |
| 4 | ジョブ一覧 | `coordinator.jobbox` | |
| 5 | 案件カレンダー | `coordinator.project_jobs.calendar` | |
| 6 | 進行表一覧 | `coordinator.progress_sheet_list.index` | |
| 7 | 進行レポート | `coordinator.progress_report.index` | |
| 8 | 設定 | `coordinator.settings.index` | |

### 案件詳細（Show.vue）内タブ構成

| # | タブ名 | キー |
|---|--------|------|
| 1 | 概要・メンバー | `overview` |
| 2 | 進行管理表 | `progress` |
| 3 | スケジュール | `schedule` |
| 4 | 連携設定 | `integration` |
| 5 | ジョブ履歴 | `history` |

### セクション構成（新版）

1. Coordinator とは（概要・役割）
2. クライアント管理
3. 外注先管理
4. 案件一覧（案件の作成・編集・完了）
5. 案件詳細の使い方（概要・メンバー / 進行管理表 / スケジュール / 連携設定 / ジョブ履歴）
6. ジョブ一覧（JobBox）とジョブ管理
7. 案件カレンダー
8. 進行表一覧
9. 進行レポート
10. 設定

### 主要修正点

- ジョブステータスを4段階に統一: **未読 / 確認済み / セット済み / 完了**
- 案件詳細内タブ（スケジュール独立・ジョブ履歴）の説明を現状に合わせて更新
- 「進行表一覧」「進行レポート」「設定（ジョブ一覧グループ表示設定）」を新規追加
- 「外注先管理」の説明を追加または整備
- ジョブ一覧のグループ表示（日付ごと・クライアントごと・案件ごと）について説明追加
- 「ガントチャート」等の旧記述を削除

**変更対象ファイル:**
- `resources/js/Pages/Guide/Coordinator.vue`

---

## GUIDE-P-01：ProofAdmin ガイド（新規作成）

### 概要
ProofAdmin（校正管理者）ガイドは現在存在しない。ルート・コントローラーメソッド・Vue ファイルをすべて新規作成する。

### 新規作成対象ファイル

| ファイル | 内容 |
|---------|------|
| `app/Http/Controllers/GuideController.php` | `proofCoordinator()` メソッドを追加 |
| `resources/js/Pages/Guide/ProofCoordinator.vue` | ProofAdmin 向けガイドページ（新規） |
| `routes/web.php` | `guide.proof_coordinator` ルートを追加 |
| `resources/js/Pages/Guide/Index.vue` | ProofAdmin カードを追加 |

### ルート設定

```php
Route::get('/guide/proof-coordinator', [GuideController::class, 'proofCoordinator'])
    ->name('guide.proof_coordinator');
```

### ガイド Index.vue への追加

- キー: `proof_coordinator`
- タイトル: 校正管理者向けガイド
- サブタイトル: Proof Admin
- アイコン: 🖊️
- カラー: pink（`from-pink-50 to-rose-50`, `border-pink-200`）
- 表示条件: `['proof_coordinator', 'leader', 'admin', 'superadmin']` のロール

### ProofAdmin タブ構成（掲載順）

| # | タブ名 | ルート | 備考 |
|---|--------|--------|------|
| 1 | 校正依頼受信 | `proof_coordinator.inbox` | 未対応件数バッジあり |
| 2 | ジョブ管理 | `proof_coordinator.jobs` | |
| 3 | 校正カレンダー | `proof_coordinator.calendar` | |
| 4 | 校正員作業量 | `proof_coordinator.workload` | |
| 5 | 校正チーム管理 | `proof_coordinator.team.index` | |
| 6 | 単発派遣管理 | `proof_coordinator.dispatchers.index` | |

### セクション構成（新規）

1. ProofAdmin とは（概要・役割）
2. 校正依頼受信（依頼の確認・受付・担当割り当て）
3. ジョブ管理（校正ジョブの一覧・ステータス管理）
4. 校正カレンダー
5. 校正員作業量（ワークロード確認）
6. 校正チーム管理
7. 単発派遣管理

**変更対象ファイル:**
- `app/Http/Controllers/GuideController.php`（追加）
- `resources/js/Pages/Guide/ProofCoordinator.vue`（新規）
- `resources/js/Pages/Guide/Index.vue`（ProofAdmin カード追加）
- `routes/web.php`（ルート追加）

---

## 作業ログ

| 日付 | 作業 | 状態 |
|------|------|------|
| 2026-05-06 | GUIDE_PLAN.md・GUIDE_MANAGER.md・GUIDE_PROMPT.md 作成 | 完了 |
