# レイアウトと UI（統合ドキュメント）
最終更新: 2026-05-23

`resources/js/app.js` は Vite の `vite:preloadError` を受け、ビルド更新後に古い画面が削除済みの分割 JavaScript を参照した場合、入力中の内容を失う可能性を案内して再読み込みを選べるようにする。古い JavaScript にはこの処理が含まれないため、初回はブラウザでページを再読み込みする。

CLAUDE.md の UI/レイアウトセクションが最上位の権威。本ファイルはその詳細リファレンス。

---

## AppLayout 使用規則（最重要）

すべての Inertia ページは `AppLayout` を使うこと。

### 正しいページ構造

```vue
<AppLayout title="ページタイトル">
  <template #header>
    <h2 class="text-xl font-semibold leading-tight text-gray-800">見出し</h2>
  </template>

  <div class="rounded bg-white p-6 shadow">
    <!-- コンテンツ -->
  </div>
</AppLayout>
```

**`py-12 > max-w-7xl mx-auto` は AppLayout の内部に実装済み。ページ側で重複させない。**

### 全幅レイアウト（`:fluid="true"`）

列数が多い表・グラフ中心の画面など、`max-w-7xl`（横幅制限）では窮屈になる場合は
`AppLayout`に`:fluid="true"`を渡す。ヘッダー・ナビゲーションタブは通常どおり
`max-w-7xl`内に収まったまま、**デフォルトスロット（本文）だけ**が画面幅いっぱいに広がる
（`mt-2 px-3 sm:px-4`のみの余白）。独自レイアウトを書く必要はない。

```vue
<AppLayout title="ページタイトル" :fluid="true">
  <template #header>...</template>
  <div class="rounded bg-white p-6 shadow">...</div>
</AppLayout>
```

採用例: `Schedule/Index.vue`（予定表）。

社内ナビゲーション自体を完全に外したい特殊なケース（例: `Coordinator/MGinbon/LedgerIndex.vue`の
密なスプレッドシート風画面）は`AppLayout`を使わない例外が存在するが、通常は`fluid`で十分であり、
よほどの理由がない限り`AppLayout`を外さないこと（ヘッダー・役割別タブ・ToastUnified等を失う）。

### AppLayoutを使わない独立レイアウトの例外: `SalesAnalysis`配下（2026-10-09）

`SalesAnalysis/`配下の全画面は`fluid`でも視認範囲が狭いという実機フィードバックを経て、
最終的に`AppLayout`自体を使わない専用の`SalesAnalysisShell.vue`
（`resources/js/Components/SalesAnalysis/SalesAnalysisShell.vue`）へ移行した。

```vue
<SalesAnalysisShell title="ページタイトル" :route-prefix="routePrefix" active="タブキー">
  <template #actions><!-- 任意。Excel出力ボタン等 --></template>
  <div class="rounded bg-white p-6 shadow">...</div>
</SalesAnalysisShell>
```

- `Head`・`ToastUnified`・最小限のバー（戻るリンク＋タイトル＋SuperAdminのみ会社切替
  `SuperAdminContextSwitcher`）・グループ化したタブ（`SalesAnalysisNavigationTabs.vue`）を内包する。
- 通知ベル・メール・カレンダー・ヘルプガイド・更新ログ・役割別タブ（SuperAdmin/Admin/Leader等）は
  表示されない。戻るリンク（`{routePrefix}.dashboard`）で通常メニューに戻れば使える。
- 会社切替（`SuperAdminContextSwitcher`）だけは、SuperAdminが分析対象会社を選ぶために機能上
  必須のため、銀本進行のように完全には削らず専用バーへ残している。
- 新規ページを`SalesAnalysis/`配下に追加する場合はこの`SalesAnalysisShell`を使うこと
  （`AppLayout`は使わない）。他の機能ではこの例外を真似ず、通常どおり`AppLayout`（必要なら
  `fluid`）を使うこと。

### 情報出版の作業分析試用画面（2026-10-11）

2026-10-13: 大まかな案件の登録画面 `SuperAdmin/BroadProjects/Index.vue` は通常の `AppLayout` を使う。Super Admin のモバイル・デスクトップ両メニューに「大まかな案件」を置き、表示条件とサーバ権限は情報出版の試用判定に揃える。Coordinator メニューは一般利用時の後工程。
2026-10-14: 同画面の取引先候補は文字を入力したときだけ開き、閉じるボタン・外側クリック・Esc で閉じられるようにする。空欄をフォーカスしただけで候補を表示すると下の登録ボタンを覆うため、避ける。

`Schedule/WorkAnalysis.vue` と `Schedule/WorkDetailAnalysis.vue` は、予定表や Super Admin の通常メニューから別タブで開く独立画面。両画面は `Components/Schedule/WorkloadAnalysisShell.vue` を使い、`AppLayout` の役割別ヘッダー・タブを表示しない。独立画面内では簡易順位と詳細分析を同じタブで切り替え、予定表へ戻れる。旧方式アーカイブは別タブで既存画面を参照する。集計 API と表示権限は従来どおり。

作業記録の独立試用も同じシェルを使う。Super Admin メニューの「作業記録の試用」は情報出版の Super Admin にだけ表示し、別タブで開く。モバイルの選択メニューも同じ条件を使う。ページとAPIの権限は `PersonalWorkSchedule::available()` でサーバ側でも確認する。

### NG パターン（やってはいけない）

```vue
<!-- NG: py-12 / max-w-7xl をページ側で書く -->
<AppLayout>
  <div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
      <div class="rounded bg-white p-6 shadow">...</div>
    </div>
  </div>
</AppLayout>

<!-- NG: <main> タグ使用 -->
<!-- NG: ToastUnified を各ページで重複配置（AppLayout に既存） -->
```

---

## AppLayout スロット

| スロット | 用途 |
|---------|------|
| `#header` | ページ見出し（h2）、戻るボタンなど |
| `#headerExtras` | ヘッダー右側の追加コンテンツ |
| `#tabs` | ナビゲーションタブ |
| デフォルト | メインコンテンツ（カード等） |

### AppLayout が provide する値

- `authUser` — ログインユーザーオブジェクト
- `user` — ページの user prop

---

## 戻るボタン配置（L-02 標準）

`#header` スロット内に `div.flex.items-center.gap-3` で Link + h2 を横並びにする。

```vue
<template #header>
  <div class="flex items-center gap-3">
    <Link
      :href="route('some.index')"
      class="rounded bg-gray-200 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-300 whitespace-nowrap"
    >← 一覧に戻る</Link>
    <h2 class="text-xl font-semibold leading-tight text-gray-800">ページタイトル</h2>
  </div>
</template>
```

- `#headerExtras` や `#header` の外には置かない
- `← ` は全角スペース、`whitespace-nowrap` を付ける

---

## ロール別バッジカラー

| ロール | Tailwind クラス |
|-------|---------------|
| SuperAdmin | `bg-yellow-100 text-yellow-800` |
| Admin | `bg-red-100 text-red-800` |
| Leader | `bg-orange-100 text-orange-800` |
| Coordinator | `bg-green-100 text-green-800` |
| Clerk | `bg-purple-100 text-purple-800` |
| User | `bg-blue-100 text-blue-800` |

---

## AppLayout ヘッダー右ボタン（グローバル）

AppLayout のヘッダー右エリアに以下のアイコンボタンが配置済み（追加不要）:

- **スクリプトツール**（スパナアイコン）— `auth.canAccessScripts` が true のユーザーのみ表示
- **更新ログ**（時計アイコン）— `route('changelogs.index')` へのリンク、全ユーザー表示

---

## テーブル・カード共通スタイル

```
テーブル: min-w-full divide-y divide-gray-200
ヘッダー行: bg-gray-50
データ行: hover:bg-gray-50
カード: rounded bg-white p-6 shadow
```

---

## Ziggy route() 呼び出し

パラメータは必ずオブジェクト形式で渡す:

```js
// OK
route('coordinator.project_jobs.show', { projectJob: job.id })
route('changelogs.show', { changelog: log.id })

// NG
route('coordinator.project_jobs.show', job.id)  // 第2引数に直接 ID
```

---

## レスポンシブ

- Tailwind の `sm:` / `md:` / `lg:` プレフィックスで対応
- ファイル名の大文字小文字を一貫させる（大文字小文字違いだけの差分は TypeScript/jsconfig 警告の原因）

---

## ToastUnified

AppLayout 内にグローバル配置済み。各ページで重複させない。

---

## 外部クライアント向け独立デモの例外

`/n-demo` のように、社外クライアントへ提示する独立したゲストデモのInertiaページでは、社内ナビゲーション・通知・Echo購読・keep-aliveを含む `AppLayout` を使用しない。

- デモ専用レイアウトを使い、社内機能やロール構造を表示しない
- コンテンツ幅と余白は専用レイアウト側で一元管理する
- ページ側の `main`、`py-12`、`max-w-*` 重複ラッパー禁止は継続する
- Ziggy `route()`、レスポンシブ、安全なテキスト描画などの一般規則は継続する
- 例外は明確に分離されたクライアント向けデモに限定し、通常の社内Inertiaページには適用しない

現在の対象: `resources/js/Pages/NSystem/`（`NSystemDemoLayout.vue` を使用）


## Clerkカレンダー設定画面（2026-09-15）

- `Clerk/Calendar/Settings.vue`と`Holidays.vue`はAppLayoutのデフォルトスロットへ直接白カード（rounded bg-white p-6 shadow）を置く。
- 戻るリンクと見出しをheaderスロット内のflex行にまとめる。独自main、幅制限・余白ラッパー、ToastUnifiedの重複を追加しない。
- 設定メニュー・フォームの主操作色はClerkの紫。祝日一覧は共通テーブルの罫線・hoverを使用し、狭い画面は横スクロールで対応。
- 年間カレンダーのCSSはclerk-year-strip内に限定。日付セルの休日色を予定チップへ伝播させない。
- 「予定日設定」の一覧・フォームも同じAppLayout、header内の戻るリンク、白カードを使う。主操作はClerkの紫、キャンセルは灰色、削除は赤。
- 予定日フォームは会議設定のラジオ・曜日・日付追加の入力構成を参考にし、設定内容に必要な欄だけを条件表示する。独自の`main`やページ幅ラッパーを追加しない。

## Clerkカレンダー・リマインダー（2026-09-15）

- 一般ユーザーの`/calendar`だけで、ナビゲーションの下・カレンダー本体の直前に`CalendarReminderBanner`を表示する。Clerkの`/clerk/calendar`には表示しない。
- 各リマインダーは一段の帯とし、選択色の濃い枠線と薄い背景を使う。「事務からのお知らせ：内容」を中央揃え・太字で表示し、表示期間は画面へ出さない。
- Clerk管理画面はAppLayout、header内の灰色の戻るリンク、白カード、共通テーブルを使用する。主操作はClerkの紫、キャンセルは灰色、削除は赤。
- Clerk上部メニューの「リマインダー設定」から一覧・追加・編集へ移動する。モバイルのページ選択にも同じ項目を表示する。
