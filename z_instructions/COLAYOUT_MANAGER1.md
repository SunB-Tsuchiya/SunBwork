# COLAYOUT_MANAGER1.md — 会社別レイアウト分離 進捗管理

## ステータス凡例
⬜ 未着手 / 🔄 作業中 / ✅ 完了 / ⏸ 保留（後回し）

## 作業フロー
1. 作業前に `COLAYOUT_PLAN1.md` の「絶対原則」を確認（サン・ブレーン不変）
2. 後回し事項（下記 D-n）を実装する場合は、先に影響範囲を提示して確認を取る
3. Vue/JS 変更後は `npm run build`。ブラウザ確認はユーザーが行う
4. 作業ログに追記し、状態を更新する

---

## Phase 1: 設計・合意

| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 1-1 | 全体構造の調査（AppLayout / CompanyModules / company_type） | ✅ | 土台は COTYPE で実装済みと確認 |
| 1-2 | 方針合意（既存ロール流用・総務/経理は clerk） | ✅ | 2026-10-15 |
| 1-3 | タブ許可リスト確定 | ✅ | PLAN 3.2 |
| 1-4 | 経理判定＝部署名に「経理」 | ✅ | |

## Phase 2: バックエンド

| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 2-1 | `DenyGeneralCompany` ミドルウェア + エイリアス | ✅ | |
| 2-2 | leader / coordinator グループに適用 | ✅ | route:list で確認済み |
| 2-3 | DashboardController 振り分け | ✅ | |
| 2-4 | `isAccountingClerk` 共有 | ✅ | |
| 2-5 | PHP 構文チェック | ✅ | Docker 内 `php -l` 全件 OK |

## Phase 3: フロントエンド

| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 3-1 | AppLayout ロール行（PC）general ブロック | ✅ | |
| 3-2 | AppLayout スマホメニュー general ブロック | ✅ | |
| 3-3 | ジョブ通知アイコン非表示 | ✅ | |
| 3-4 | Admin / User / Clerk タブ許可リスト | ✅ | |
| 3-5 | Calendar.vue / Diaries/Show.vue ジョブ系ボタン非表示 | ✅ | |
| 3-6 | `npm run build` | ✅ | 2026-10-15 成功 |

## Phase 4: 動作確認（ユーザー実施）

| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 4-1 | sunbrain 表示が不変（全ロール） | ⬜ | ローカル company 2 を sunbrain 化済み（superadmin と user/clerk で確認可） |
| 4-2 | general の admin / 総務 / 経理 / user 表示 | ⬜ | ダミー 3 名でログイン確認（下記）。user ロールのダミーは未作成 |
| 4-3 | SuperAdmin 会社切り替え | ⬜ | |
| 4-4 | general の leader/coordinator 振り分けと 403 | ⬜ | |
| 4-5 | スマホ幅 | ⬜ | |

## Phase 5: デプロイ

| # | タスク | 状態 | 備考 |
|---|---|---|---|
| 5-1 | 本番 `companies.company_type` の確認（サン・ブレーン = sunbrain） | ⬜ | **必須**。SSH 実行はユーザー確認後 |
| 5-2 | DEPLOY_SAKURA.md 手順で本番反映（VITE_APP_BASE_PATH 手順厳守） | ⬜ | ユーザー指示があるまで実施しない |
| 5-3 | ChangelogSeeder 追記 + 実行 | ⬜ | |
| 5-4 | CONSOLIDATED_01 / 09 更新 | ⬜ | |

---

## 🗂 後回し事項（Deferred Backlog）

> 「あとで対応する」と合意した項目。着手前に必ず影響範囲を提示して確認を取ること。

| ID | 項目 | 状態 | 詳細 |
|---|---|---|---|
| D-1 | **URL 直打ち対策** | ⏸ | 追加対象（2026-10-15）: `schedule.department_work.*`（部署の作業・作業分析）、`guide.*`、`scripts.*`、`changelogs.*`。現状はタブを隠しただけで、general ユーザーが URL を直接開けば画面に入れる。対象: admin の `project_jobs` / `clients` / `workload_analyzer` / `sales_analysis` / `admin_permissions`、user の `user.project_jobs.*` / `user.myjobbox.*` / `user.jobbox.*`。**共用ルートが多く、塞ぐとサン・ブレーンに波及するおそれ**があるので、ルート単位で `deny_general` を付ける際は共用箇所を洗い出してから。leader / coordinator は対応済み |
| D-2 | 売上分析のサンエー印刷版 | ⏸ | 現行データ・Excel 構造はサン・ブレーン用。サンエー印刷の経理から要望が出てから、サン・ブレーン版をベースに再構築。会社別データ分離（company_id）は実装済み・本番未デプロイ |
| D-3 | 経理判定の `departments.module` 化 | ⏸ | 現在は部署名に「経理」を含むかで判定。部署名変更・表記ゆれに弱い。必要になったら module 値（例 `accounting`）に移行（本番データ設定が必要） |
| D-4 | general の Leader / Coordinator / Proof Admin ユーザーの扱い | ⏸ | 現状は「ユーザー」ボタンのみ（リーダー相当の機能なし）。サンエー印刷で部署リーダー機能が欲しくなったら、general 用の Leader 画面を検討 |
| D-5 | 画面内のサン・ブレーン要素の全数監査 | ⏸ | タブ・ボタンは対応済み。各ページ内部（ユーザー管理の項目、部署管理の module 選択、クライアント、ワークロード等）にサン・ブレーン前提の文言・項目が残っていないか未監査。ユーザーが実画面で気付いた順に追加 |
| D-6 | ナビ定義のデータ化（sunbrain 側も含む） | ⏸ | 現在は AppLayout に general ブロックを足す方式。ロール行が 3 か所（PC・スマホ・タブ）に分散。サン・ブレーン安定運用の確認後に検討 |
| D-7 | 使い方ガイド・更新ログのサンエー印刷向け内容 | ⏸ | 2026-10-15: general ではヘッダーの guide / changelogs / scripts アイコンを非表示にした。サンエー印刷向けの内容（ガイド・更新ログ）が必要になったら作成して表示を戻す。changelogs には会社の区別カラムが無い |
| D-8 | 総務・経理それぞれの専用機能 | ⏸ | 現在は総務と経理でタブ・機能が完全に同じ（色とボタンのみ分離）。これから各部署に必要な機能を足していく。足す際は `clerkKind` で出し分け、必要なら総務/経理で別ルート（例 clerk.keiri.*）を検討 |
| D-9 | ローカル DB の company 2 を sunbrain 化 | ✅ | 2026-10-15 完了。company 2 を `sunbrain` に更新し、サンエー印刷（id=3）とダミー 3 名を登録（作業ログ参照）。**未実施: 部署の `module`（publishing/prepress/ondemand）はローカル未設定**＝Prepress/校正依頼の featureFlags 確認時に必要 |

---

## 作業ログ

| 日付 | 内容 |
|---|---|
| 2026-10-15 | 全体調査、方針合意（既存ロール流用・A案）。タブ許可リスト確定。 |
| 2026-10-15 | 実装完了（Phase 2・3）。`npm run build` 成功。Docker 起動後に `php -l` 全件 OK、route:list で leader/coordinator に `deny_general` 付与を確認。 |
| 2026-10-15 | 発見: ローカル DB は company 2（サン・ブレーン）が `general`。→ PLAN 6 / D-9 に記録。URL 直打ち対策は後回し（D-1）と決定。COLAYOUT 3 ファイル作成。 |
| 2026-10-15 | ローカル DB 整備: company 2（サン・ブレーン）を `sunbrain` に更新。サンエー印刷（id=3, code=SANEI, general）を新規登録し、総務部(id=4)・経理部(id=5)・各担当・会社/部署チーム・一般作業項目デフォルトを投入。ダミーユーザー 3 名を追加（下記）。破壊的操作なし。 |

### ローカル確認用ダミーユーザー（サンエー印刷 / パスワードはすべて `password`）

| ID | 名前 | メール | ロール | 部署 | 期待表示 |
|---|---|---|---|---|---|
| 21 | サンエー管理者（ダミー） | sanei-admin@example.invalid | admin | 総務部 | 管理者 / 総務/経理 / ユーザー |
| 22 | サンエー総務（ダミー） | sanei-soumu@example.invalid | clerk | 総務部 | 総務 / ユーザー |
| 23 | サンエー経理（ダミー） | sanei-keiri@example.invalid | clerk | 経理部 | 経理 / ユーザー |

サン・ブレーン側の確認はSuperAdmin（id=1, company 2）または既存の user/clerk（company 2）で行う。
| 2026-10-15 | 作業内容分析（/schedule/department-work 配下）を AppLayout の `lastTab_*` 保存対象から除外（銀本進行と同扱い）。ユーザー確認済みで解消。 |
| 2026-10-15 | 総務/経理を別ボタン・別色に分離（総務=紫 / 経理=teal）。タブ構成は当面同一（4 タブ: ダッシュボード・お知らせ通知・カレンダー・リマインダー設定）。`auth.clerkKind` を追加（Clerk=所属部署名に「経理」、Admin 以上=`?kind=` で選んだ方をセッション `clerk_kind` に保持、既定 総務）。`isAccountingClerk` は廃止。 |
| 2026-10-15 | 予定表を general では共有予定のみ表示（部署の作業タブ・作業実績の内訳・カレンダータブ・ガイドリンクを非表示）。ヘッダーの使い方ガイド / スクリプト / 更新ログを general で非表示（PC・スマホ）。お知らせ・予定表アイコンは共通のまま。 |
