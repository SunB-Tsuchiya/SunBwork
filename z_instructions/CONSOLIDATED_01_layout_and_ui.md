# レイアウトと UI（統合ドキュメント）
最終更新: 2026-05-23

2026-10-30追加: 情報出版SuperAdminのUserメニューだけ、`UserNavigationTabs.vue` が「仕事・案件」を上段に表示し、対象画面では `UserWorkHubTabs.vue` で5つの内側タブを表示する。判定はサーバ共有 `userWorkHub.enabled` と会社条件。モバイルとデスクトップは同じ上段定義を使う。内側タブは横スクロールでき、各既存URLを維持して `aria-current` で選択状態を示す。詳細は `USER_WORK_HUB_PLAN1.md`。

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

2026-10-14〜16: 情報出版の Super Admin に限り、マイジョブBOXとカレンダーの通常新規マイジョブ入口をモーダルにする。最初の短縮フォームは案件の検索文字と選択IDが別々で、利用者の保存が失敗した。2026-10-16 の利用者決定により、旧登録ページと同じ共通 `AssignmentForm.vue` の選択式・全入力欄をモーダルで使い、任意詳細の開閉は置かない。簡易予定の作業種類は初期表示のまま。通常登録の旧ページと依頼引継ぎ/進行表の経路は残し、将来の簡易入力との統合後も旧形式を選べるようにする。試用範囲、反映、画面確認は `MYJOB_MODAL_MANAGER1.md` で別々に追う。

2026-10-13: 大まかな案件の登録画面 `SuperAdmin/BroadProjects/Index.vue` は通常の `AppLayout` を使う。Super Admin のモバイル・デスクトップ両メニューに「大まかな案件」を置き、表示条件とサーバ権限は情報出版の試用判定に揃える。Coordinator メニューは一般利用時の後工程。
2026-10-14: 同画面の取引先候補は文字を入力したときだけ開き、閉じるボタン・外側クリック・Esc で閉じられるようにする。空欄をフォーカスしただけで候補を表示すると下の登録ボタンを覆うため、避ける。
2026-10-14: 一覧の「編集」は上部の登録フォームを切り替えず、既存値を入れたモーダルを開く。登録と編集は同じ入力部品を使う。候補一覧が開いている間の Esc は候補を先に閉じる。

2026-10-30 管理画面への移設: `Schedule/WorkAnalysis.vue` / `WorkDetailAnalysis.vue` / `WorkloadGuide.vue` は共通 `WorkloadAnalysisShell` 内で `AppLayout(fluid)` を使う。SuperAdmin/Admin/Leaderそれぞれの `*.workload_analyzer.*` に入り、通常のロール別メニュー・黄/赤/橙・ダッシュボードへの戻り先を使う。分析内の簡易・詳細・ガイド・旧アーカイブのリンクとAPIも同じprefixを維持する。従来の独立画面・固定の「予定表へ」リンクはこの移設で置換する。

会社/部署の候補は管理用閲覧スコープから提供し、初期値と選択をURLに保持する。「作業記録の試用」は分析タブとSuperAdminの通常/モバイルメニューから外すが、既存データと厳しい入力側の試用権限は保持。一般社員向け表示は別工程。実装/配備/画面確認は `WORKLOAD_MANAGEMENT_MOVE_MANAGER1.md` を参照する。

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


## SunA 総務（年末調整・社員情報）の画面（2026-10-18）

- 総務の申請メニュー配下。ページは `Pages/SunA/GeneralAffairs/**`、部品は `Components/SunA/GeneralAffairs/Nencho/**`。AppLayout の使い方は本書の共通ルールに従い、戻るボタンは `#header` に置く。
- 従業員の入力フォームは本人入力と総務代理入力で同じコンポーネントを使い、`provide('nenchoRoutePrefix')` でルート名の接頭辞を切り替える。
- 入力部品は `Field.vue`（例を常時表示）・`AddressFields.vue`（郵便番号補完）・`v-norm`（`normalize.js`：全角／半角／金額／日付の正規化）を使い、ページごとに独自実装しない。
- 受付詳細（`Campaigns/Show.vue`）に「従業員への案内」カードがあり、お知らせ送信（文面を編集してから送信）・カレンダーのリマインダー・送信履歴（既読数）を扱う。


## 交通費の画面（2026-10-20）

- 申請者: ユーザー用のタブ「申請」→ 申請ハブ（`user.applications.index`）→「交通費」（`user.billing.transport.*`）。AppLayout・青いユーザー用タブ。「申請」タブは、確認の期間は SuperAdmin・Admin・Clerk だけに表示（`UserNavigationTabs.vue` の `APPLY_TAB_ROLES`。全員に広げるときは `showApply` を常に true）。
- 総務・経理: Clerk の「申請」タブ → 申請ハブ（`clerk.applications.index`。サンエー印刷の総務だけ、年末調整などの申請の種類も並ぶ）→「交通費」「運賃マスタ」（`clerk.billing.transport.*`）。紫の Clerk タブ。SuperAdmin も同じ画面を使う。戻るリンクは `#header` 内の灰色「← 申請に戻る」。
- 入力画面（`Pages/Billing/Transport/Apply/Index.vue`）: 入力は止まって約 1.5 秒で自動保存（JSON。画面は書き換えない）。状態表示（入力中／保存中／自動保存しました／自動保存できません）。保存できない状態での離脱・月の切替・PDF/Excel 出力の前に確認を出す。明細の行頭に行番号（エラーの「○行目」と対応）。入力の操作（行を追加・クイック入力・貼り付け・登録した行先・前月コピー）は表の下にまとめる。記入例は欄の下に常時表示する。
- 詳細画面（総務・経理）: 検算の要約を表の上、確認の操作（一致した行をまとめて確認済み／すべて確認済み）は表の下に置く（表を見終えて、下へ進みながら操作するため）。差戻しのコメントは、行番号を押すと「○行目：」が入る。
- 期限（月末）を過ぎた月: 黄色い枠で「入力・申請できません」と表示し、入力欄は操作不可。依頼は先月分だけ。許可された月は緑の枠。
- 申請書の PDF は、絶対配置（表を使わない）で原本の寸法を再現している。dompdf の表は、結合セルがあると列幅が効かないため。


2026-10-30 部署記録プレビュー（実装中/未配備）: DEPARTMENT_RECORDS_PLAN1.mdに従い、User「部署の作業」に予定/みんなの作業記録の2表示を設ける。初期は予定、共有予定ページは維持。専用読取APIで会社部署と期間を制限し、点数/private内容/他人の不足案内を返さない。現在はINFO SuperAdminだけ。一般公開は別承認。今日＋過去4営業日、会社祝日は読取利用、本人の空きは実勤怠/勤務枠/休憩がある場合のみ。実装/検証/配備/画面はDEPARTMENT_RECORDS_MANAGER1.mdで別管理。
