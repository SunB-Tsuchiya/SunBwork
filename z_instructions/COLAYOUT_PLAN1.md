# COLAYOUT_PLAN1.md — 会社別レイアウト分離（サンエー印刷 / サン・ブレーン）設計書 v1

作成: 2026-10-15 / 前提プロジェクト: COTYPE（`companies.company_type` による機能分離）

---

## 1. 目的

サンエー印刷（`company_type = 'general'`）も本ポータルを使えるよう、**ロールボタン・タブメニューを会社タイプ別に出し分ける**。
デザイン・レイアウト・サイトのルール・共通機能（日報、カレンダー、チャット、お知らせ等）は共通のまま。

## 2. 絶対原則（最重要）

> **サン・ブレーンは毎日使われている本番。サン・ブレーン側の見た目・挙動を一切変えない。**

- すべての変更は `companyType === 'general'`（フロント）/ `company_type === 'general'`（サーバー）のときだけ効く**ガード方式**にする
- 既存のサン・ブレーン用マークアップは書き換えない（`v-if` → `v-else-if` への変更と、先頭への分岐追加のみ許可）
- `company_type:sunbrain`（会社未設定ユーザーも 403 にする）ではなく、**`deny_general`（general だけ拒否）**を使う
- SuperAdmin・会社未設定ユーザー（`companyType = 'global'`）は従来どおり

## 3. 確定仕様（2026-10-15 ユーザー確定）

- ロールは**既存のものを流用**（新ロールは作らない）。総務・経理は両方 `clerk`
- 総務/経理の振り分けは**所属部署名に「経理」を含むか**で判定（`auth.clerkKind`。Admin 以上は ?kind= で選択しセッション保持）
- サンエー印刷では Leader / Coordinator / Proof Admin / Prepress を**見えなくする**
- 売上分析はサンエー印刷では出さない（サンエー印刷の経理から要望が出た後に、サン・ブレーン版をベースに再構築）
- user の「設定」タブはサンエー印刷にも残す（中身は会社非依存：基本勤務形態・カレンダー初期表示）
- **URL 直打ち対策は今は不要**（後回し → MANAGER の「後回し事項」D-1）

### 3.1 ロール行（2 段目）

| 実ロール | ボタン表示（general） |
|---|---|
| superadmin | SuperAdmin / 管理者 / 総務 / 経理 / ユーザー |
| admin | 管理者 / 総務 / 経理 / ユーザー |
| clerk | 総務 または 経理（所属部署で自動）/ ユーザー |
| leader, coordinator, proof_coordinator, user | ユーザー のみ |

### 3.2 タブ（3 段目）許可リスト（general のみ適用）

| ロール | 表示するタブ |
|---|---|
| admin（管理者） | ダッシュボード・会社管理・ユーザー管理・部署管理・特別チーム・日報管理・日報権限管理・勤務形態設定・勤務時間管理・Leader権限管理・会議設定・在席ボード管理・会議室管理・予定表権限設定 |
| user | ダッシュボード・カレンダー・日報一覧・チームルーム・設定 |
| clerk（総務） | ダッシュボード・お知らせ通知・カレンダー・リマインダー設定 |
| clerk（経理） | 当面 総務と同じ（ダッシュボード・お知らせ通知・カレンダー・リマインダー設定）。色のみ teal |

非表示にした admin タブ: 案件総覧・クライアント管理・作業量分析・売上分析・Admin権限管理
非表示にした user タブ: 案件確認・マイジョブBOX・依頼されたジョブ・校正状況

### 3.3 サン・ブレーン要素の除去（general のみ）

- ヘッダーのジョブ通知アイコン（PC・スマホ）
- カレンダー / 日報詳細の予定登録: 「案件打合せ・外出」「マイジョブ」「進行表・管理表ジョブ」
- ログイン直後の振り分け: general の leader/coordinator/proof_coordinator は User ダッシュボードへ

## 4. 実装方針（ガード方式）

当初案は「ナビ定義の完全データ化（`CompanyModules/*.js` に navRoles）」だったが、
サン・ブレーン既存コードの書き換えリスクがあるため**採用しない**。代わりに既存ブロックの前に general 用ブロックを足す。
（将来 sunbrain 側もデータ化する場合は D-6 参照）

## 5. 変更ファイル一覧

| 種別 | ファイル | 内容 |
|---|---|---|
| 新規 | `app/Http/Middleware/DenyGeneralCompany.php` | general 所属（SuperAdmin 除く）を 403 |
| 変更 | `bootstrap/app.php` | エイリアス `deny_general` |
| 変更 | `routes/web.php` | leader / coordinator グループに `deny_general` |
| 変更 | `app/Http/Controllers/DashboardController.php` | general の leader/coordinator/proof_coordinator は `user.dashboard` へ |
| 変更 | `app/Http/Middleware/HandleInertiaRequests.php` | `auth.user.isAccountingClerk` 追加 |
| 変更 | `resources/js/layouts/AppLayout.vue` | `isGeneral` / `generalClerkLabel`、ロール行・スマホメニューの general ブロック、ジョブ通知アイコン非表示 |
| 変更 | `resources/js/Components/Tabs/AdminNavigationTabs.vue` | general 許可リスト |
| 変更 | `resources/js/Components/Tabs/UserNavigationTabs.vue` | general 許可リスト |
| 変更 | `resources/js/Components/Tabs/ClerkNavigationTabs.vue` | general 許可リスト（総務/経理別） |
| 変更 | `resources/js/Components/Calendar.vue` | ジョブ系予定登録を general で非表示 |
| 変更 | `resources/js/Pages/Diaries/Show.vue` | 同上（案件打合せ・外出） |

## 6. リスクと注意

- **ローカル DB の会社タイプ（2026-10-15 整備済み: company 2 = sunbrain / company 3 = サンエー印刷）**: 整備前はローカルは `company 2 = サン・ブレーン` が `company_type = 'general'`（`code` が `SUNBRAIN` でないため migration の更新対象外）。
  → **ローカルでは全員が general レイアウトで表示される**。サン・ブレーン表示の確認時は company 2 を `sunbrain` に更新するか、本番相当のデータで確認すること
- **本番デプロイ前に必ず確認**: 本番の `companies` でサン・ブレーンが `company_type = 'sunbrain'` であること（`general` だと全員のメニューが変わる）。SSH 実行はユーザー確認のうえで
- `company_id = NULL` のユーザーは `companyType = 'global'` となり従来表示（影響なし）
- Docker 起動は `docker compose up -d`（`--user sail` で exec）

## 7. 検証観点（ブラウザ確認はユーザーが実施）

1. sunbrain 所属ユーザー（各ロール）の表示が変更前と完全に同一
2. SuperAdmin の会社切り替え: グローバル / サン・ブレーン = 従来、サンエー印刷 = 3.1・3.2 のとおり
3. general の admin / clerk（総務・経理）/ user のタブが 3.2 のとおり
4. general の leader/coordinator が /dashboard から User ダッシュボードへ入れる
5. general で `/leader/dashboard` `/coordinator/dashboard` が 403
6. スマホ幅（ドロップダウン・ハンバーガー）

## 8. 完了時にやること（CLAUDE.md 規定）

1. `ChangelogSeeder` に追記 → `php artisan db:seed --class=ChangelogSeeder`
2. `CONSOLIDATED_01`（UI）と `CONSOLIDATED_09`（ドメインルール）に追記
3. COLAYOUT 3 ファイルを `z_instructions/archived/` へ移動（**後回し事項が残っている間は移動しない**）
