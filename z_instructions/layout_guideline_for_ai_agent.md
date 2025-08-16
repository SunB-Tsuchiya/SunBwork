# サイトレイアウト統一仕様書（AIエージェント用）

## 目的
本仕様書は、Laravel 12 + Vue 3 + Inertia.js + Jetstream 環境で新規ページやコンポーネントを作成するAIエージェント向けに、レイアウト・UI・UXの統一ルールを明確に示すものです。英訳しやすいよう、簡潔かつ曖昧さの少ない日本語で記述します。

【重要】
main部分（<main>タグ内）のレイアウト統一のため、以下のファイルのtemplate部分（特にmain部分）も必ず読み込み・参照してから新規ページやコンポーネントを作成すること。

- resources/js/Pages/Admin/Dashboard.vue
- resources/js/Pages/Admin/Users/Index.vue
- resources/js/Pages/Admin/Users/Show.vue

これらのファイルのmain部分の構造・クラス・余白・見出し・カード・テーブル・グリッド等の使い方を徹底的に踏襲すること。

【Laravel12, Jetstreamの基本レイアウト構造について】
本プロジェクトはLaravel12およびJetstreamの標準レイアウト（app.blade.php相当）をVue/Inertiaで忠実に再現している。
AIエージェントは必ずLaravel12, Jetstreamの基本レイアウト構造を参照し、下記の特徴・仕様を守ること：

■ レイアウト構造の特徴
- ルートレイアウトはAppLayout.vueで、全ページの共通枠組みとなる。
- <Head>でページタイトルをセット。
- <Banner>で全体通知やアラートを表示。
- <nav>でグローバルナビゲーション（ロゴ・権限別メニュー・ユーザー操作）を表示。
- <header>スロットでページごとの見出しやアクションを表示。
- <main>でページ固有のコンテンツを表示。
- レスポンシブ対応（sm: md: lg: クラス）を徹底。

■ ナビゲーションの詳細
- 権限（admin/leader/coordinator/user）ごとに表示メニューが切り替わる。
- ナビゲーションリンクはNavLink/ResponsiveNavLinkコンポーネントを使い、active状態を明示。
- ロゴはApplicationMark.vue。
- ユーザー情報・チーム切替・設定ドロップダウンを右上に配置。

■ メイン部分の詳細
- <main>タグ内はpy-12で上下余白、max-w-7xl mx-auto sm:px-6 lg:px-8で中央寄せ・横幅制限。
- カード表示はbg-white shadow-xl sm:rounded-lgで統一。
- グリッドはgrid grid-cols-1 md:grid-cols-2 gap-6等で2カラム対応。
- テーブルはmin-w-full divide-y divide-gray-200、ヘッダーはbg-gray-50。
- ボタン・リンク・バッジはTailwindの配色・角丸・太字を徹底。

■ その他
- usePage().props.userでユーザー情報を取得し、権限や表示内容を制御。
- Jetstreamのプロフィール写真・チーム機能・APIトークン等のUIも踏襲。

【指示】
新規ページ・コンポーネント作成時は、Laravel12, Jetstreamの基本レイアウト構造（AppLayout.vueおよび上記特徴）を必ず参照し、main部分の統一感を最優先すること。

---

## 1. レイアウトの基本構造
- すべてのページは `AppLayout.vue` をベースレイアウトとして使用すること。
- `<AppLayout>` の `title` 属性にはページタイトルを必ず指定する。
- `<template #header>` スロットを使い、ページごとの見出しやアクションボタンを配置する。
- メインコンテンツは `<main>` 内に記述し、`<div class="py-12">` で余白を確保する。
- 最大幅は `max-w-7xl mx-auto sm:px-6 lg:px-8` を推奨。

## 2. ナビゲーション
- ナビゲーションは `AppLayout.vue` のナビゲーションバーを継承し、ユーザー権限（admin/leader/coordinator/user）ごとに表示内容を切り替える。
- タブ型ナビゲーションは `<nav class="flex space-x-8" aria-label="Tabs">` を用い、`Link` コンポーネントで遷移する。
- 管理画面では赤系、リーダーはオレンジ系、進行管理は緑系、一般ユーザーは青系の配色を使い分ける。

## 3. カラースキーム・配色
- 管理者: 赤系（text-red-600, bg-red-100 など）
- リーダー: オレンジ系（text-orange-600, bg-orange-100 など）
- 進行管理: 緑系（text-green-600, bg-green-100 など）
- 一般ユーザー: 青系（text-blue-600, bg-blue-100 など）
- その他: グレー系（text-gray-600, bg-gray-100 など）

## 4. テーブル・リスト表示
- 一覧表示は `<table class="min-w-full divide-y divide-gray-200">` を基本とし、ヘッダーは `bg-gray-50`、行は `hover:bg-gray-50` で強調。
- バッジやラベルは `inline-flex px-2 py-1 text-xs font-semibold rounded-full` を使い、権限や役職ごとに色分けする。

## 5. ボタン・リンク
- 主要アクションは太字・角丸・配色付き（例: `bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded`）。
- 二次アクションや詳細・編集はテキストリンク（例: `text-blue-600 hover:text-blue-900`）。
- 削除など危険操作は赤系テキスト（例: `text-red-600 hover:text-red-900`）。

## 6. フォーム・入力
- フォームは `grid grid-cols-1 md:grid-cols-2 gap-6` で2カラム対応。
- ラベルは `block text-sm font-medium text-gray-700`、入力欄は `mt-1 text-sm text-gray-900`。
- バリデーションエラーは赤系で明示。

## 7. プロフィール・詳細表示
- プロフィールや詳細は `<dl class="divide-y divide-gray-200">` で区切り、左右にラベルと値を配置。
- 日付は `toLocaleDateString('ja-JP')` で日本語表記。

## 8. レスポンシブ対応
- すべてのレイアウト・テーブル・フォームはスマホ・タブレット対応（Tailwindの `sm:`, `md:`, `lg:` クラスを活用）。

## 9. その他
- すべてのページで `usePage().props.user` からユーザー情報を取得し、権限や表示内容を制御する。
- 主要なページ・コンポーネントは `resources/js/Pages/` 配下に配置。
- 新規ページ作成時はこの仕様書を必ず参照し、既存の `Admin/Dashboard.vue`、`Admin/Users/Index.vue`、`Dashboard.vue` などの実装例に倣うこと。

---

## 付録：英訳しやすい表現例
- "必ず" → "must"
- "推奨" → "should"
- "使用すること" → "must use"
- "配色付き" → "with color scheme"
- "2カラム対応" → "two-column layout"
- "明示" → "clearly indicate"
- "区切り" → "separated by"
- "制御する" → "control by"

---

この仕様書に従うことで、全ページ・コンポーネントのUI/UX・レイアウト・配色・操作性が統一され、保守性・拡張性が向上します。
