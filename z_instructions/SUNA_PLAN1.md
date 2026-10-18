# SUNA_PLAN1.md — サンエー印刷専用機能（SunA）設計書 v1（申請メニュー）

作成: 2026-10-16 / 前提: COLAYOUT（会社別レイアウト分離）、CLAUDE.md「サンエー印刷専用機能の配置ルール（SunA）」

## 1. 目的
サンエー印刷（`company_type = 'general'`）専用の機能を `SunA/{部署英語名}/` に集約して育てていく。
第 1 弾として**総務に「申請」メニュー**を作る。

- 「年末調整」などは、社員が総務に提出する**申請の「種類」**
- 今回は、種類を一覧で並べ、種類を管理（CRUD）する入口まで
- 各申請の入力フォーム・提出・承認フローは、ユーザーの案が固まってから別フェーズで追加

## 2. 絶対原則
- サン・ブレーンに影響させない（`general` のときだけ表示・動作するガード方式）
- SunA 配置ルールを守る（CLAUDE.md）
- 新規ページは AppLayout を使う（CONSOLIDATED_01、戻るボタンは L-02 標準）

## 3. 仕様
### 3.1 画面
| 画面 | URL | ルート名 | 内容 |
|---|---|---|---|
| 申請一覧 | `/clerk/general-affairs/applications` | `clerk.general_affairs.applications.index` | 有効な申請の種類を枠（カード）で一覧表示。サンプル「年末調整」。「申請の種類を管理」ボタン |
| 種類一覧 | `/clerk/general-affairs/application-types` | `clerk.general_affairs.application_types.index` | 表。編集・停止/再開・削除 |
| 種類 追加/編集 | `…/application-types/create` `…/{type}/edit` | `…application_types.create/edit` | 名前・説明・並び順・有効 |

- ルート名は接頭辞 `clerk.` を保つ（AppLayout のロール判定・ナビ色が `clerk.` で動くため）
- 総務タブに「申請」を追加（`general` かつ総務 `clerkKind === 'soumu'` のときのみ表示）
- 権限: clerk / admin / superadmin（`clerk` ミドルウェア）+ `company_type:general`（SuperAdmin は通過）
- 会社スコープ: `ResolvesContextCompany` で company_id 絞り込み（リマインダー CRUD と同じ方式）

### 3.2 DB
`suna_ga_application_types`（`ga` = GeneralAffairs）

| カラム | 型 | 備考 |
|---|---|---|
| id | bigint | |
| company_id | FK companies | cascadeOnDelete |
| name | string(100) | 必須 |
| description | text null | |
| sort_order | unsigned int default 0 | |
| is_active | boolean default true | |
| created_by | FK users | |
| timestamps | | |

- サンプル「年末調整」は migration で自動投入しない（本番に勝手に作らない）。ローカルでは画面から登録

## 4. 変更ファイル一覧
| 種別 | ファイル |
|---|---|
| migration | `database/migrations/2026_10_16_100000_create_suna_ga_application_types_table.php` |
| Model | `app/Models/SunA/GeneralAffairs/ApplicationType.php` |
| Request | `app/Http/Requests/SunA/GeneralAffairs/ApplicationTypeRequest.php` |
| Controller | `app/Http/Controllers/SunA/GeneralAffairs/ApplicationController.php`（一覧）/ `ApplicationTypeController.php`（CRUD） |
| ルート | `routes/suna.php`（新規）+ `routes/web.php` に require 1 行 |
| Page | `resources/js/Pages/SunA/GeneralAffairs/Applications/Index.vue` / `ApplicationTypes/Index.vue` / `ApplicationTypes/Form.vue` |
| 空ディレクトリ | `app/…/SunA/Accounting/` 各種 `.gitkeep`（経理用の置き場） |
| 変更 | `ClerkNavigationTabs.vue`（申請タブ、general+総務のみ）、（AppLayout は変更しない。各ページが `#tabs` スロットで `active="applications"` を渡す。※ `getTopTabActive` は `includes('ai')` に誤マッチするため、スロットを使わない新ページを足すときは注意） |
| 再生成 | `resources/js/ziggy.js`（ルート追加のため） |

## 5. 検証観点（ブラウザ確認はユーザーが実施）
1. サンエー総務・管理者（総務選択時）に「申請」タブが出る。経理には出ない（経理は SunA 経理の機能追加時に検討）
2. サン・ブレーンの Clerk / Admin のタブが従来どおり
3. 種類の追加・編集・停止・削除、並び順、有効/無効が一覧に反映
4. サン・ブレーンのユーザーが URL 直打ちで 403
5. スマホ幅

## 6. デプロイ時
- **migration あり** → `php artisan migrate --force`（CLAUDE.md・DEPLOY_SAKURA.md）
- routes 変更あり → Ziggy 再生成
- Vue 変更あり → VITE_APP_BASE_PATH 6 ステップ厳守

## 7. 追記（2026-10-16）: 年末調整トップと設計ガイド
- `suna_ga_application_types.feature_key`（nullable）で、申請の種類を機能画面に連携（現在: `nencho` = 年末調整）。連携できる機能は `ApplicationType::FEATURES` に追加していく
- ルート: `clerk.general_affairs.nencho.index` / `…nencho.guide`。Controller: `SunA/GeneralAffairs/Nencho/NenchoController`、ページ: `SunA/GeneralAffairs/Nencho/{Index,Guide}.vue`
- 設計ガイドの元データ: `resources/docs/suna/general_affairs/nencho_overview.md`（Markdown 1 ファイル。サーバーで HTML を除去して描画）
- migration は 2 本（`2026_10_16_100000` / `2026_10_16_110000`）。本番は `migrate --force` が必要
