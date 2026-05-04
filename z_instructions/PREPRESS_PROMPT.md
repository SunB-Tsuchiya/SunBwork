# SunBWork Prepress（製版）エリア Claude向けプロンプトファイル
作成日: 2026-04-29

---

## このファイルの使い方

新しい Claude セッションを開始するとき、このファイルの内容をそのまま冒頭に貼り付けてください。
または「PREPRESS_PROMPT.md を読んで実装を続けてください」と指示してください。

---

## セッション開始時のプロンプト（コピーして使用）

```
あなたはこれから SunBWork プロジェクトの Prepress（製版）エリア構築作業を行います。

まず以下のファイルを必ず順番に読んでください：
1. `/home/tchirosb/SunBWork/CLAUDE.md`（プロジェクト全体ルール・必読）
2. `/home/tchirosb/SunBWork/z_instructions/PREPRESS_MANAGER.md`（作業管理書・フロー・進捗一覧）
3. `/home/tchirosb/SunBWork/z_instructions/PREPRESS_PLAN.md`（各作業の詳細仕様・変更ファイル）

読み終えたら、以下を報告してください：
- 現在の進捗状況（未着手・進行中・完了の件数）
- 次に着手すべき推奨作業（理由も添えて）

作業は PREPRESS_MANAGER.md に記載された「作業フロー（5ステップ）」と「安全ルール」に従って進めてください。
特に STEP 2（設計・方針の提示）でユーザーの「OK」を得るまで絶対に実装を始めないこと。

各 P-xx 作業の完了・進捗状況は必ず PREPRESS_MANAGER.md に記録してください：
- 作業完了時: 進捗一覧のステータスを「✅ 完了」に更新し、作業ログに変更ファイルを記録
- ビルド成功・ユーザー確認待ちの場合: ステータスを「🔨 実装中」に更新
```

---

## 設計サマリー（Claude向け補足）

### プロジェクト背景

- **業種:** 印刷・組版会社向け社内管理システム（Laravel 11 + Vue 3 + Inertia.js）
- **目的:** 製版（Prepress）部署専用エリアを新設し、製版固有の作業管理機能を集約する
- **現状:** フェーズ1（タブ追加 + Dashboard）の実装待ち。フェーズ2以降はユーザーが機能を指定予定

### 最重要ルール（CLAUDE.md より）

1. 作業前に必ず関連コードを読む
2. 設計提示 → ユーザー確認 → 実装の順を守る
3. 質問は1つずつ
4. Vue/JSファイル変更後は `npm run build`（プロジェクトルートで実行）
5. Artisan は `docker compose exec laravel bash -lc "php artisan ..."`
6. さくら本番では `route()` 必須・ハードコードパス禁止

### 作業 ID 一覧（フェーズ1）

| ID | 内容（短縮） |
|----|------------|
| P-01 | AppLayout に Prepress タブリンクを追加（全ロール + 条件付き） |
| P-02 | PrepressNavigationTabs.vue を新規作成（green-700 カラー） |
| P-03 | Prepress/Dashboard.vue + PrepressDashboardController.php 新規作成 |
| P-04 | routes/web.php に prepress.dashboard ルート追加 |
| P-05 | HandleInertiaRequests.php に isPrepressDepartment フラグ追加 |

### テーマカラー（重要）

Prepress のモチーフカラーは **濃い緑（`green-700` / `green-800`）**。

```
アクティブタブ:      bg-green-100 text-green-800
非アクティブタブ:    border border-green-700 text-green-700 hover:bg-green-50 hover:text-green-900
ロールナビ（active): bg-green-700 text-white font-semibold
ロールナビ（inactive): text-green-700 hover:text-green-900
```

### アクセス権限（重要）

```
SuperAdmin / Admin: 常に表示・アクセス可
Leader / Coordinator / User / Clerk: department.name === '製版' の場合のみ
proof_coordinator: 対象外
```

フロントエンドでの判定: `$page.props.auth.user.isPrepressDepartment`
バックエンドでの判定: `$user->department?->name === '製版'`（ただし SuperAdmin/Admin は例外）

### 主要ファイルパス（よく触るもの）

```
resources/js/layouts/AppLayout.vue                              ← P-01
resources/js/Components/Tabs/PrepressNavigationTabs.vue         ← P-02（新規）
resources/js/Pages/Prepress/Dashboard.vue                       ← P-03（新規）
app/Http/Controllers/Prepress/PrepressDashboardController.php   ← P-03（新規）
routes/web.php                                                  ← P-04
app/Http/Middleware/HandleInertiaRequests.php                   ← P-05
```

### 参考にすべき既存ファイル

```
resources/js/Pages/Clerk/Dashboard.vue                          ← Dashboard のベース
resources/js/Components/Tabs/ClerkNavigationTabs.vue            ← タブコンポーネントのベース
app/Http/Controllers/Clerk/ClerkDashboardController.php         ← コントローラーのベース（存在確認要）
```

### AppLayout の構造理解（重要）

`AppLayout.vue` には2段ナビがある：
- **Row 1:** ロゴ + 通知アイコン + プロフィール
- **Row 2:** ロール別リンク（SuperAdmin, Admin, Leader, Coordinator, User, Prepress ...）

Row 2 のロール別 `<template>` ブロック（5つ）それぞれに Prepress リンクを追加する。
`roleNavClass('prepress')` 関数も `activeMap` / `inactiveMap` に追加する。

`currentRouteContext` computed にも `if (r.startsWith('prepress.')) return 'prepress';` を追加する（`clerk.` の前後あたりに挿入）。

### よくある落とし穴

- さくら本番に存在しないカラムを `update()` に含めると無音で壊れる
- さくら上の CSRF は `document.querySelector('meta[name="csrf-token"]')` から取得
- `route()` の第2引数はオブジェクト形式: `route('prepress.dashboard', {})`
- `department.name` の値が実際の DB と合っているか確認すること（'製版' で間違いないか）
- AppLayout のレスポンシブナビゲーション（ハンバーガーメニュー）への追加を忘れないこと

---

## フェーズ2 機能追加時のプロンプト補足

ユーザーから新機能の指示が出た場合、以下の情報を PREPRESS_PLAN.md に追記してから作業を開始すること：

1. 機能の概要と目的
2. 対象ユーザー（製版部署全員 or 特定ロールのみ）
3. 使用するデータ（既存テーブル or 新規テーブルが必要か）
4. UIイメージ（既存ページの何に近いか）
5. 期待する動作フロー
