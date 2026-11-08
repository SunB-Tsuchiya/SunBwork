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

2026-10-31 大まかな案件の階層管理（ローカル検証・未配備）: BroadProjectTreeが本人の会社/INFO部署とSuperAdmin/Coordinator/Leaderを再認可する。最大3段の親子を一つの一覧で開閉し、名前・祖先名・取引先・年度の検索では一致行と祖先を表示。登録/編集は共通BroadProjectFormモーダル、親選択、ロール別ルート/操作色、通常AppLayoutを維持。親の休止も簡易入力の新規候補から除き、既存選択は再編集できる。簡易入力の候補だけに経路ラベルを返し、既存カレンダータイトル/レコードは一括変更しない。分類管理の進行管理/Leader開放と一般Userの仕事ハブ/新入力公開は別権限。承認済み62分類は専用CLIで明示会社部署にdry-run後登録し、import_keyでWeb編集を保護。詳しくはUSER_WORK_HUB_PLAN1/MANAGER1。

2026-10-31追記: 上記階層管理はe6280319fへ本番限定配備済み。承認62分類を追加し旧年度付き1件を保持、合計63件。INFO同部署Coordinator/Leader/SuperAdminの分類管理のみ開放。一般Userの新ハブ/入力の公開と既存ProjectJob紐付けは含まない。新階層の利用者画面確認は未完了（USER_WORK_HUB_MANAGER1）。

2026-10-31 利用者承認による操作整理（ローカル実装/未配備）: 分類を大/中2段とし、通常編集は名前/取引先/年度/休止だけ。大追加と各大内の中追加を分け、中だけ専用「グループ移動」で大の間を移す。大を子へ移す/中の下へ追加しない。共通順は承認Excel順を初期値とし、INFO同部署Coordinator/Leader/SuperAdminのみ、専用モーダルで大同士/同じ大内の中同士を並べ替えて保存する。休止込みの全兄弟を対象に現在順をサーバ照合し、同時変更時は再表示を求める。一般Userの並べ替えは不可。AppLayout/role色/ルート/共通フォームは維持。新sort_order/初期化CLIは設定済み順を保護し、既存ID/所属/名称を初期化で変えない。一般公開/お気に入り/ProjectJob紐付けは後続。進捗はUSER_WORK_HUB_MANAGER1。

2026-10-31追記: 上記大中2段/操作分離/管理者共通順は本番968f164baへ限定配備完了。sort列だけ追加、63件20兄弟集合を承認Excel順へ初期化し、sort以外の全列/ID保持を確認。独立更新履歴/キャッシュ4種/公開manifestと対象JS確認完了。新操作の利用者確認は別管理（USER_WORK_HUB_MANAGER1）。

2026-11-02 ProjectJob分類参照（ローカル検証/未配備）: Coordinatorの通常新規/編集のタイトル付近でBroadProjectPickerを使い、大/中分類を任意選択。検索、共通順、元の休止選択保持、未確定候補の手動選択に対応。権限がない場合は欄と送信項目を出さず、保存時も実所属/案件管理権限を再検証。project_jobs.broad_project_idだけを明示登録し、未送信は既存値保持、NULLで解除。既存案件名/依頼/マイジョブ/添付は一括変更しない。分類経路は許可者のCoordinator詳細で独立表示し、一般Userの新ハブ/簡易入力公開とは分離。詳細はUSER_WORK_HUB_PLAN1/MANAGER1。

2026-11-02追記: 上記ProjectJob分類参照は本番94d42b779へ限定配備完了。新nullable FKのみ追加し、既存の紐付け0/件数保持/分類マスター不変更を検証。公開資産も確認済み。利用者画面での新操作/保存は未確認、USER_WORK_HUB_MANAGER1で別管理。

2026-11-02 利用者指示（ローカル検証/未配備）: Coordinatorの案件作成/編集は「機密案件（部署の作業予定表に案件名を表示しない）」だけチェックする方式に反転。「機密案件以外はチェックを付けないでください。」を常時表示。画面computedのみ反転し、既存のshow_on_department_scheduleと公開範囲の意味は保持。新規は未チェック、既存非表示だけチェック。進捗はUSER_WORK_HUB_MANAGER1。

2026-11-02追記: 機密案件だけチェックするCreate/EditのUI反転は本番614e8ec2dへ限定配備済み。DB値と表示範囲は保持。利用者画面の反転文言/新規未チェックは次の確認（USER_WORK_HUB_MANAGER1）。

2026-11-02 個人お気に入り（コード検証/未配備）: 大まかな案件一覧で大/中を個別に星登録解除し、「お気に入りだけ」で親文脈を保って絞込む。共通順は不変、休止した星は「休止中も表示」で解除可能。簡易入力とCoordinatorの分類pickerは本人の星を別候補群で先に表示し、同じIDの重複なし。通常30件より後ろの星も呼べる。本人/現在会社部署をサーバ固定し既存試用/管理guardを維持、一般User公開なし。星更新はCSRFメタと冪等PUT、失敗時は星を変更せず案内。AppLayout/role色/共通部品を使用。詳細はUSER_WORK_HUB_PLAN1/MANAGER1。

2026-11-02追記: 個人お気に入りは本番4e816e1c6へ限定配備済み。新テーブルは空で追加し、共通分類/順序と既存案件リンクを保持。公開資産/独立履歴/キャッシュ確認済み。利用者の星登録/保持/絞込と入力候補呼出は未確認（USER_WORK_HUB_MANAGER1）。

2026-11-02 利用者指示の休止UI明確化（ローカル検証/未配備）: 共通BroadProjectFormは「休止にする」だけチェックする形へ反転。新規/有効は未チェック、既存休止はチェック済み。is_activeの意味と既存DB状態は変えない。「簡易予定」をラベルから外し、将来のマイジョブ統合でも通じる「新しい作業や案件登録の選択候補」を説明に使う。過去記録保持と、大を休止すると中も候補外になることを表示。詳細進捗はUSER_WORK_HUB_MANAGER1。

2026-11-02追記: 「休止にする」の共通フォーム反転は本番5462a2b09へ限定配備済み。DBの有効/休止値は更新せず保持。公開資産/キャッシュ確認済み、新表示の利用者確認は次（USER_WORK_HUB_MANAGER1）。


2026-11-06 大まかな案件の複数クライアント（未配備）: 既存編集モーダルで複数選択/個別解除。一覧は2社までと「ほかN社」をnative detailsで展開する。中分類の一致は親も表示し、名称と関連取引先名で検索。親の集約関連は直接編集値とは分ける。既存AppLayout/role色/共通順/お気に入り/休止表示を維持。簡易入力・進行管理の分類選択も複数名で検索可能だが、選択だけで具体案件の取引先を変更しない。

2026-11-06追記: 上記複数クライアント機能は本番e856d0c5aへ限定配備済み。専用migrationと初期関連94組登録、既存分類/参照保持を確認。利用者画面確認待ち、一般公開未承認。

2026-11-06利用者確認: 複数クライアント表示/ほかN社展開、クライアント名検索と親子表示、複数選択追加・解除の保存再表示の3点は「成功」。一般公開・別アカウント・全入力画面の確認まで拡張しない。

2026-11-07入力のボタン表記整理（未配備）: 新式/旧モーダル/旧作成pageの流用入口は「過去データから流用」、受信取り込みは「受けた依頼から登録」、選択見出しは「受けた依頼を選択」。他人への依頼発信と区別する名称で、従来の保存・続き・独立流用・日時引継ぎ・権限/ルートは保持。統合BOXの旧い全機能旧BOX案内を、流用/受信は作成入口、難易度等は旧BOXと分けて案内する。

## ポートフォリオの画面（SB専用・2026-11-06）

- **AppLayout はルート名の接頭辞でロール別ナビを決める**（`AppLayout.vue` の `currentRouteContext`）。接頭辞なし（`portfolio.manage` 等）だと、全員が「ユーザー」のナビになる（実際に発生）。画面を持つルートは `admin.portfolio.*` / `user.portfolio.*` のように**ロールごとに登録**する（`routes/portfolio.php` がクロージャを返し、`routes/web.php` の各ロールのグループ内で呼ぶ。売上分析の `$registerSalesAnalysisRoutes` と同じ方式）。画面内リンクはコントローラーが渡す `routePrefix` で組み立てる。画面のないルート（API・ファイル配信）と、独立レイアウトの閲覧ページは接頭辞なし（`portfolio.api.*` / `portfolio.view`）。
- **ナビの「選択中」表示**は `getTopTabActive()` に `.portfolio.` を含むルートで `portfolio` を返す分岐を入れてある。タブのキーは `portfolio`。
- **閲覧ページ（`Pages/Portfolio/View.vue`）は AppLayout を使わない独立レイアウト**で、管理画面の「客先で見せる ↗」から `<a target="_blank">` で別タブに開く。管理用の操作（追加・編集・削除）は置かない。白ベース＋モノトーン＋デザイナーが選ぶアクセント色（CSS はインライン style）。
- **管理画面は「作る人」と「見る人」で分ける**: 本人（スイッチ ON の人）は user エリアの編集画面（`user.portfolio.manage`）。Admin / SuperAdmin は admin エリアの**タブ付きの管理画面**（下記）で、メンバーの作品を閲覧するだけ（編集ボタンは出さない）。サーバー側でも書き込みを拒否している。
- **作品の追加は「＋ 作品を追加」ボタン → モーダル**（`ItemCreateModal.vue`）。入力順は ①タイトル → ②説明 → ③ファイル → 分類（任意）。常時表示のドロップ枠は置かない。動画用に「動画がアップできないとき」の折りたたみメモがある（エラー時は自動で開く）。
- **画像の拡大**は `ImageLightbox.vue`（全画面。クリックで等倍、複数ページは ← → で送る、Esc で閉じる）を共用する。
- **`useToasts` が返すのは `showToast`**（`addToast` は存在しない。`const addToast = showToast;` と別名を付けて使う。`addToast` を分割代入すると実行時に「is not a function」で止まる）。
- 並べ替えは `sortablejs`。DOM は Vue が管理するため、`onEnd` で移動を元に戻してからデータを並べ替える（`Manage.vue` の `revertDom`）。

2026-11-07追記: ボタン名と案内文の変更は本番f9821e4c3へ反映済み。DB・機能・権限の変更なし。利用者による今回の画面確認待ち。

2026-11-07利用者「成功」: 統合BOX案内と作成内の流用/受信ボタン表示、受信選択見出しと閉じる操作の2点を画面確認済み。実際の依頼保存・一般公開の確認には拡張しない。

2026-11-07試用入口（未配備）: workHubMyJobRouteは既存server試用flag/本人SuperAdmin/非general/本人会社context一致を確認し、上段「仕事・案件」と内側「マイジョブ」を統合BOXへ揃える。条件外は従来index。デスクトップ/モバイル共通。統合BOXの従来BOXリンクと旧BOXの試用リンクは保持し、一般公開/サーバ認可は変更しない。

## ユーザー選択（権限付与）画面の標準形 ⚠️ 2026-11-07 追加

**ユーザーを選んで権限を与える画面（「この機能を使える人」のスイッチ、利用許可、対象者の選択など）は、必ず、申請者管理のメンバー設定と同じ形にする。**
独自のレイアウトや、独自の並び順・独自の保存方法を作らない（作り直しになる）。

| 項目 | 内容 |
|---|---|
| **見本（これを開いて確認してから作る）** | 本番 `https://sun-brain.co.jp/members/admin/applicants/transport`（ローカルは `/admin/applicants/transport`）。実装は `resources/js/Pages/Admin/Applicants/Show.vue` と `app/Http/Controllers/Admin/ApplicantController.php`。在席ボード管理（`Pages/Iruka/BoardSettings.vue`）も同じ構造 |
| **使用例** | `resources/js/Pages/Portfolio/Permissions.vue` ＋ `PortfolioPermissionController`（ポートフォリオの利用メンバー設定）。担当の絞り込みを足した例 |

### 画面の形（上から順に、この並びで）
1. `AppLayout`。`#header` は「← ○○に戻る」ボタン（`bg-gray-200`）＋ 見出し。`#tabs` に、そのエリアのナビ（Admin の画面なら `<AdminNavigationTabs active="…" />`）を渡す
2. 本文は **`mx-auto max-w-3xl rounded bg-white p-6 shadow`** のカード 1 枚。冒頭に、何が起きるスイッチかを説明する文（`text-sm text-gray-500`）と、「変更したら『保存する』を押してください」
3. **部署のチップ**（「すべての部署」＋ 各部署。部署が無い会社では出さない）
4. **雇用区分のチップ**（「すべての区分」＋「正社員・契約社員」を 1 つにまとめる ＋ 派遣社員・業務委託は該当者がいるときだけ）
5. 必要なら、その機能に固有の絞り込みを、**同じチップの形で 1 行足す**（例: ポートフォリオの「担当」）。別の形（セレクトボックス等）にしない
6. 件数の行: 「使う人: **n** / 全員（表示中: m人）」＋ 右端に **「表示中をすべて ON」「表示中をすべて OFF」**
7. 表: 名前 / 部署 / 役職 / 区分（＋ その機能に必要な列）/ **スイッチ**。OFF の行は `bg-gray-50`、名前は薄く
8. 下部: 左に「✓ 保存しました」または「未保存の変更があります（n人）」、右に「キャンセル」と **赤の「保存する」**（変更があるときだけ押せる）

### スタイル（Admin の画面）
- チップ: `rounded-full px-3 py-1 text-xs font-medium`。選択中は `bg-red-600 text-white`、それ以外は `bg-gray-100 text-gray-600 hover:bg-gray-200`
- スイッチ: `h-5 w-9` の丸いトグル。ON は `bg-red-500`、OFF は `bg-gray-300`（`role="switch"` と `aria-checked` を付ける）
- 保存ボタン: `bg-red-600 text-white hover:bg-red-700 disabled:opacity-50`。色は、その画面が属するエリアのロール色に合わせる（Admin = 赤）

### 動き・データの形
- **スイッチは、押した瞬間には保存しない。** 動かした状態を画面内に持ち、「保存する」で**まとめて 1 回**送る（保存済みの状態と比べて、変更の有無・件数を出す）
- 未保存の変更があるままページを離れようとしたら、`beforeunload` で確認する
- 保存は `PUT/POST` ＋ JSON: リクエスト `items: [{ user_id, enabled }, ...]` → レスポンス `{ ok: true, changed: n }`。CSRF は meta タグから取る（`XSRF-TOKEN` クッキーは使わない）
- **サーバー側で、対象のユーザーだけを受け付ける**（会社・ロールの範囲外の `user_id` は無視する）。**変更のない行は触らない**（誰が・いつ変えたか＝`changed_by` / `granted_by` を残すため）
- 「OFF にすると困る人」（途中の申請がある人など）がいれば、保存前に `confirm` で名前を挙げて確認する
- **ロールの力で、スイッチに関わらず常に使える人（ポートフォリオでは SuperAdmin だけ）も、会社と部署が設定されていれば、一覧に名前を出す**（誰がいるか分かるように）。スイッチは **ON のまま固定（`disabled`・`cursor-not-allowed opacity-50`）**にし、名前の横にロールのバッジ（SuperAdmin=黄 / Admin=赤）を付ける。「表示中をすべて ON/OFF」・保存のリクエストには含めず、サーバー側でも無視する。会社・部署が未設定の管理用アカウントは出さない。**Admin のように、スイッチで決める人は、部署が未設定でも出し、スイッチを操作できるようにする**（ポートフォリオの Admin は未設定なら自動 ON。既定値が ON の人は、保存時に「既定値と同じ」なら行を作らない）（スイッチで決める人は、一覧に出ないと許可を与えられないため）。実装例: `PortfolioPermissionController`（`ALWAYS_ON_ROLES` / `listedUsers()` / `switchableUsers()`）

### 並び順（メンバー一覧・メンバーを選ぶドロップダウン、すべて共通）
**雇用区分（正社員 → 契約社員 → 派遣社員 → 業務委託）→ 役職（役員・次長・課長…の `position_titles.sort_order`。役職称号管理で決めた任意の順番。役職なしは最後）→ 氏名。**
- 並べ替えは**サーバー側**で行い、画面側では並べ替えない。実装は `ApplicantController::show` の `sortBy([...])`。ポートフォリオ側には同じ並びを `App\Services\Portfolio\MemberList`（`sort()` と `row()`）に切り出してある。**3 画面目以降で使うなら、`app/Services/` の共通の場所へ移して共用する**
- 部署の並びは `Department::orderBy('sort_order')->orderBy('id')`
- ユーザー管理画面（`User::ordered()`＝ドラッグで並べ替える `users.sort_order`）とは**別の並び**。権限を与える画面では、上の「役職順」を使う

### NG
- スイッチを 1 つ押すたびに保存して、トーストを出す（旧ポートフォリオ設定画面がこれで作り直しになった）
- ロール（User / Coordinator）での絞り込み（役割ではなく、**部署・雇用区分・担当**で絞る）
- 独自のテーブルレイアウト、独自の色、独自の並び順
- 一覧に全員を出して、絞り込み（部署・区分のチップ）を付けない

**ユーザーを選ぶ（権限を与える）必要が出たら、まず本番の `admin/applicants/transport` を開き、この形をそのまま写す。**

2026-11-07新規モーダルの旧形式入口（未配備）: 過去流用/受信登録と同列の右寄せに「旧形式で作成」。狭幅では折返す。新規試用だけで表示し、日付/開始終了時刻を既存の旧新規pageへGETで引継ぐ。他の入力があれば切替前に非転記を確認し、取消で入力を保持。編集/続き/選択済み流用は通常新規へ切り替えない。

## タブ付きの管理画面（チームルーム型）と、登録者のカード一覧 ⚠️ 2026-11-08 追加

複数の画面を行き来する管理画面（ポートフォリオ管理など）は、**チームルーム（`Pages/TeamRoom/Show.vue`）と同じ、下線つきの固定タブ**で分ける。1 画面に、メンバーの選択・一覧・設定を詰め込まない。

- **タブバー**: `sticky top-0 z-20 rounded-t bg-white px-6 pt-4 shadow-md` の中に `flex gap-1 overflow-x-auto border-b border-gray-200`。タブは `whitespace-nowrap px-4 py-2 text-sm font-medium border-b-2 -mb-px`。**有効なタブの色は、その画面が属するエリアのロール色**（Admin エリア = `border-red-500 text-red-700`。チームルームは青紫だが、管理エリアは赤にそろえる）。それ以外は `border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300`
- **タブはページ（URL）で分ける**（Inertia の `Link`）。戻る・再読み込みでも同じタブに戻れ、SuperAdmin だけに出すタブ（権限画面など）をサーバー側でも守れる。実装: `Components/Portfolio/PortfolioAdminTabs.vue`
- タブバーの直下に、**同じ幅のカード**（`rounded-b bg-white px-6 pb-6 pt-5 shadow-md`）を置く。外枠は `mx-auto max-w-5xl`。申請者管理の標準形（ユーザー選択画面）をタブの中に入れるときも、中身はそのままで、外枠だけをこの幅にそろえる
- **登録者のカード一覧**（`Pages/Portfolio/Overview.vue` / `Components/Portfolio/MemberCard.vue`）: 人ごとのカードを縦に並べる。カードには、名前・部署・役職・ロールのバッジ、件数の内訳、最終更新（ブラウザのローカル日付で相対表示。`toLocaleDateString('sv-SE')` で比べ、UTC のずれを出さない）、サムネイル最大 4 点（クリックで 1 人分のページへ）、「+N」を出す。**カードの余白・名前・サムネイルのどれをクリックしても 1 人分のページへ遷移**し、カード内のボタン・リンク（`a, button`）はそれぞれの動きを優先する
- 並び順・部署のチップは「ユーザー選択（権限付与）画面の標準形」と同じ（雇用区分 → 役職の設定順 → 氏名）。**作品のある人を上に、まだ無い人は下に名前だけ薄く**並べる
- 管理者が直せない問題（アップ中・変換中のまま残った作品、変換に失敗した作品）は、カードの左に赤い線と「⚠ 要確認」を出し、本人に修正・削除を依頼するきっかけにする
- 1 人分のページは、ヘッダーに「← 一覧へ」の戻るボタンを置く（申請者管理の `Show.vue` と同じ）。選択のためのプルダウンは持たない（選ぶのは一覧のカード）
