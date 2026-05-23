# SunBWork 修繕第4版 Claude向けプロンプトファイル — レスポンシブ対応
作成日: 2026-05-12

---

## このファイルの使い方

新しい Claude セッションを開始するとき、以下のプロンプトをそのまま貼り付けるか、
「REPAIR4_PROMPT.md を読んで修繕4を続けてください」と指示してください。

---

## セッション開始時のプロンプト（コピーして使用）

```
あなたはこれからSunBWorkプロジェクトの修繕作業（第4版：レスポンシブ対応）を行います。

まず以下のファイルを必ず順番に読んでください：
1. `/home/tchirosb/SunBWork/CLAUDE.md`（プロジェクト全体ルール・必読）
2. `/home/tchirosb/SunBWork/z_instructions/REPAIR_MANAGER4.md`（作業管理書・フロー・進捗一覧）
3. `/home/tchirosb/SunBWork/z_instructions/REPAIR_PLAN4.md`（各作業の詳細仕様・変更ファイル一覧）

読み終えたら、以下を報告してください：
- 現在の進捗状況（未着手・進行中・完了の件数）
- 次に着手すべき推奨作業（理由も添えて）

作業は REPAIR_MANAGER4.md に記載された「作業フロー（5ステップ）」と「安全ルール」に従って進めてください。
特に STEP 2（設計・方針の提示）でユーザーの「OK」を得るまで絶対に実装を始めないこと。

各R-xx作業の完了・進捗状況は必ず REPAIR_MANAGER4.md の進捗一覧に記録してください。
```

---

## 設計サマリー（Claude向け補足）

### この修繕の背景

スマートフォンでサイトを表示すると、全ロールのナビゲーションタブが縦長の崩れたボックスとして表示される。
特に CoordinatorNavigationTabs と SuperAdminNavigationTabs は `flex space-x-8`（折り返しなし）を使っているため最も深刻。
他は `flex flex-wrap gap-2` だが、多タブのため複数行になりモバイルでは使いにくい。

### 主な作業ID一覧

| ID | 内容 | 優先度 |
|----|------|--------|
| R-01 | 全タブコンポーネント（8ファイル）にモバイルドロップダウン追加 | 最高 |
| R-02 | ProjectJobs/Create.vue フォームレスポンシブ | 高 |
| R-03 | AssignmentForm.vue フォームレスポンシブ（要調査） | 中 |
| R-04 | Events/Create.vue フォームレスポンシブ（要調査） | 中 |
| R-05 | Calendar.vue モバイルデフォルトビュー変更 | 中 |
| R-06 | AppLayout ハンバーガーメニュー整理（R-01後に判断） | 低 |

### R-01 実装パターン（8ファイル共通）

モバイル（`sm:hidden`）では `<select>` を表示し、選択時に `router.get(href)` でナビゲート。
デスクトップ（`hidden sm:flex`）では現在のタブ表示を維持（スタイル変更なし）。

```vue
<!-- モバイル -->
<div class="sm:hidden">
  <select @change="e => { if (e.target.value) router.get(e.target.value) }"
          class="w-full rounded-md border border-[色]-300 bg-white px-3 py-2 text-sm ...">
    <option value="">— ページを選択 —</option>
    <option v-for="t in tabs" :key="t.key" :value="t.href" :selected="active === t.key">
      {{ t.label }}
    </option>
  </select>
</div>
<!-- デスクトップ -->
<nav class="hidden sm:flex flex-wrap gap-2">
  <!-- 既存のタブリンクそのまま -->
</nav>
```

`tabs` は `computed()` で定義し、v-if 相当の条件タブは `condition` フィールドで管理して `.filter(t => t.condition !== false)` で除外する。

### 各タブコンポーネントの個別ポイント

| コンポーネント | ロールカラー | 特記事項 |
|---|---|---|
| SuperAdminNavigationTabs | yellow | `space-x-8` → `flex-wrap gap-2` に。`route().has()` チェックを condition に |
| AdminNavigationTabs | red | `can()` 権限・`isRepresentative` を condition に |
| LeaderNavigationTabs | orange | `can()`・`isDepartmentLeader`・`isAdminOrAbove` を condition に |
| ClerkNavigationTabs | purple | タブ1件のみ（統一のため同パターン） |
| CoordinatorNavigationTabs | green | `space-x-8` → `flex-wrap gap-2` に。getter関数をtabs配列に取り込む |
| ProofCoordinatorNavigationTabs | pink | `pendingCount` プロップ → ラベルに `(N件)` を付与 |
| PrepressNavigationTabs | green-700 | 条件なし・シンプル |
| UserNavigationTabs | blue | `isProofMember` を condition に |

### 動作確認のポイント

- ブラウザの幅を 375px（iPhone SE相当）に縮小してドロップダウンが表示されることを確認
- 各ドロップダウンで現在のページが選択済みになっていることを確認
- 選択後に正しいページへ遷移することを確認
- 640px 以上でドロップダウンが消え、元のタブが表示されることを確認

### よくある落とし穴

- `route()` 関数の呼び出しが失敗するケース（CoordinatorNavigationTabs のgetter関数を参照）→ try/catch で囲む
- `computed()` 内で `route()` を呼ぶ際、ページ初期化前に呼ばれる場合がある → エラーを try/catch で握りつぶす
- `ProofCoordinatorNavigationTabs` の `pendingCount` ラベル → テンプレートリテラルで `${pendingCount}件` の形式

### 主要タブコンポーネントのファイルパス

```
resources/js/Components/Tabs/SuperAdminNavigationTabs.vue
resources/js/Components/Tabs/AdminNavigationTabs.vue
resources/js/Components/Tabs/LeaderNavigationTabs.vue
resources/js/Components/Tabs/ClerkNavigationTabs.vue
resources/js/Components/Tabs/CoordinatorNavigationTabs.vue
resources/js/Components/Tabs/ProofCoordinatorNavigationTabs.vue
resources/js/Components/Tabs/PrepressNavigationTabs.vue
resources/js/Components/Tabs/UserNavigationTabs.vue
```
