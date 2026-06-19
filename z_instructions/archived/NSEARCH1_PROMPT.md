# NSEARCH1_PROMPT.md - NSystem検索改善 再開用プロンプト

このファイルは、新しいCodexまたはClaudeセッションでNSystem検索改善を安全に再開するためのプロンプトである。

---

## 再開時メッセージ

NSystem検索改善（NSEARCH1）の作業を続けます。

最初に次のファイルを順番にすべて確認してください。

1. `/home/w229/SunBwork/AGENTS.md`
2. `/home/w229/SunBwork/CLAUDE.md`
3. `/home/w229/SunBwork/z_instructions/NSEARCH_PLAN1.md`
4. `/home/w229/SunBwork/z_instructions/NSEARCH_MANAGER1.md`
5. `/home/w229/SunBwork/z_instructions/NSYSTEM_GUIDE.md`
6. `/home/w229/SunBwork/z_instructions/CONSOLIDATED_01_layout_and_ui.md`

`NSEARCH_MANAGER1.md` の進捗一覧と最新作業ログを確認し、最初の未完了タスクから再開してください。作業のたびに進捗ステータスと作業ログを更新してください。

---

## 背景

SunBWork全体は印刷・組版会社の社内管理システムです。一方、`/n-demo` のNSystemは、中学入試問題をDB化した場合の検索・閲覧体験をクライアントへ提案するための独立したゲストデモです。

この境界を必ず守ってください。

- 社内データ、社内ロール、社内ナビゲーションをゲストへ露出しない
- NSystem関連名前空間とルートへ変更を限定する
- 既存のゲスト認証とデモページ管理を壊さない
- 将来NSystemだけを削除できる独立性を維持する
- ユーザー、Claude、他エージェントの既存変更を巻き戻さない

---

## 現在判明している問題

`NdemoController::search()` は、ngram FULLTEXTインデックスに対してLaravelの `whereFullText()` を自然言語モードで実行しています。

そのため「平安時代」が「平安」「安時」「時代」に分割され、「大正時代」など、入力文字列そのものを含まない問題まで返ります。また、現在の検索結果は本文先頭200文字しか表示しないため、一致箇所が後半にあると検索語が存在しないように見えます。

---

## 確定設計

- 検索画面はInertia/Vue化する
- 入力ごとの結果はGuestAuth配下のJSON GET APIで取得する
- 300ms debounce、IME composition対応、古い通信のキャンセルを実装する
- 既定は入力文字列が連続して存在する `exact`
- `all`（全語）と `any`（いずれか）も選択可能にする
- FULLTEXTは候補抽出に使い、最終判定はエスケープ済みリテラルLIKEで保証する
- 科目、学校、カテゴリで絞り込める
- 20件ページングとURL状態保持を行う
- 一致箇所を中心に前後約100文字を表示する
- スニペットはHTMLではなく before/match/after の安全な構造で返す
- 結果から対象大問へ直接移動する
- DB migrationは追加しない
- 解答検索と学校・問題ページの全面Inertia化は今回対象外

---

## レイアウト上の重要判断

`CONSOLIDATED_01_layout_and_ui.md` は通常の社内向けInertiaページに `AppLayout` を要求していますが、NSystemは外部クライアント向けゲストデモなので明示的な例外です。

社内用 `AppLayout` はロールナビ、通知、Echo購読、keep-alive等を含むため使用しません。代わりに `NSystemDemoLayout.vue` を作り、現在のNSystemの紺色ヘッダー、白いカード、カテゴリバッジを維持してください。

ただし、次の一般規則は守ってください。

- ページ側で `main`、`py-12`、`max-w-*` の重複ラッパーを作らない
- Ziggy `route()` のパラメータはオブジェクト形式にする
- レスポンシブ対応する
- ユーザー入力を `v-html` で描画しない
- NSystem専用部品以外へ不要な影響を出さない

---

## 実装予定ファイル

新規:

```text
app/Http/Requests/NSystem/NQuestionSearchRequest.php
app/Services/NSystem/NQuestionSearchService.php
resources/js/layouts/NSystemDemoLayout.vue
resources/js/Pages/NSystem/Search.vue
resources/js/Components/NSystem/SearchFilters.vue
resources/js/Components/NSystem/SearchResultCard.vue
resources/js/Components/NSystem/SearchPagination.vue
tests/Feature/NSystem/NQuestionSearchTest.php
tests/Unit/NSystem/NQuestionSearchServiceTest.php
```

変更:

```text
app/Http/Controllers/NSystem/NdemoController.php
routes/nsystem.php
resources/views/n_system/demo/school.blade.php
z_instructions/NSYSTEM_GUIDE.md
database/seeders/ChangelogSeeder.php
```

変更範囲が増える場合は、コード変更前に `NSEARCH_PLAN1.md` と `NSEARCH_MANAGER1.md` へ理由を記録してください。

---

## 検証

```bash
docker compose exec laravel bash -lc "php artisan test --filter=NQuestionSearch"
npm run build
docker compose exec laravel bash -lc "php artisan route:list --name=n-demo"
```

ArtisanとPHPテストはコンテナ内で実行してください。Vue/JS変更後は必ずbuildしてください。

作業完了後は次も実施してください。

- `ChangelogSeeder` への追記と実行
- `NSYSTEM_GUIDE.md` の更新
- 必要な場合のみ `CONSOLIDATED_01_layout_and_ui.md` へNSystem例外を追記
- PLAN / MANAGER / PROMPTを `z_instructions/archived/` へ移動

