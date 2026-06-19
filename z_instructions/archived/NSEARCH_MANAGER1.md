# NSEARCH_MANAGER1.md - NSystem検索改善 進捗管理

> 作成: 2026-06-19  
> 設計書: `z_instructions/NSEARCH_PLAN1.md`  
> 現在状態: 完了・アーカイブ対象

---

## 1. 作業ルール

1. 作業開始時に `AGENTS.md`、`NSEARCH_PLAN1.md`、本ファイルを読む。
2. ユーザーの実装承認を得るまで、下記の実装ファイルを変更しない。
3. 既存のClaude・ユーザー変更を上書きまたは巻き戻さない。
4. 各タスク完了時にステータスと作業ログを更新する。
5. 設計変更が必要な場合は、コードより先に `NSEARCH_PLAN1.md` を更新する。
6. Vue/JS変更後は `npm run build` を実行する。
7. ArtisanとPHPテストはLaravelコンテナ内で実行する。
8. 完了時に変更履歴、NSystemガイド、アーカイブ処理まで行う。

ステータス:

- `未着手`
- `作業中`
- `完了`
- `保留`
- `要確認`

---

## 2. 進捗一覧

| # | Phase | タスク | ステータス | 備考 |
| ---: | --- | --- | --- | --- |
| 1 | 設計 | 既存検索、ngram、画面、認証、UI規則の調査 | 完了 | 誤ヒット原因を特定 |
| 2 | 設計 | PLAN / MANAGER / PROMPT作成 | 完了 | 実装前文書のみ作成 |
| 3 | 承認 | ユーザーによる設計確認 | 完了 | 2026-06-19 承認済み |
| 4 | Phase 1 | 誤ヒット再現Featureテスト作成 | 完了 | exact/all/anyを対象 |
| 5 | Phase 1 | `NQuestionSearchRequest` 作成 | 完了 | API入力検証 |
| 6 | Phase 1 | `NQuestionSearchService` 作成 | 完了 | 検索・スニペット責務 |
| 7 | Phase 1 | Controllerと結果API実装 | 完了 | GuestAuth配下 |
| 8 | Phase 1 | 検索サーバーテスト実行 | 完了 | `%` `_` も検証 |
| 9 | Phase 2 | `NSystemDemoLayout.vue` 作成 | 完了 | 社内AppLayout非使用 |
| 10 | Phase 2 | `Search.vue` 作成 | 完了 | Inertiaページ |
| 11 | Phase 2 | 検索Vueコンポーネント作成 | 完了 | Filter/Card/Pagination |
| 12 | Phase 2 | debounce・IME・通信キャンセル実装 | 完了 | 300ms |
| 13 | Phase 3 | 一致箇所前後スニペット実装 | 完了 | HTMLを返さない |
| 14 | Phase 3 | 大問アンカーと直接遷移実装 | 完了 | route()利用 |
| 15 | Phase 4 | レスポンシブと状態表示調整 | 完了 | loading/empty/error |
| 16 | Phase 4 | アクセシビリティ確認 | 完了 | label/aria/focus |
| 17 | Phase 5 | NSystem検索テスト一式 | 完了 | 12 tests / 45 assertions |
| 18 | Phase 5 | `npm run build` | 完了 | 実在workspaceで成功 |
| 19 | Phase 5 | 動作確認 | 完了 | Inertia応答・実DB確認。GUI目視は環境にブラウザなし |
| 20 | 完了 | `NSYSTEM_GUIDE.md` 更新 | 完了 | 検索仕様を反映 |
| 21 | 完了 | ChangelogSeeder追記・実行 | 完了 | nsystem-search-1 |
| 22 | 完了 | PLAN/MANAGER/PROMPTをarchivedへ移動 | 完了 | 本更新後に移動 |

---

## 3. 設計判断

### 2026-06-19: 検索の既定動作

- 決定: 入力文字列が連続して存在する `exact` を既定とする。
- 理由: クライアントデモでは、結果が返った理由を説明できることを優先する。
- 補足: 広い検索は `all` / `any` として利用者が明示的に選択できる。

### 2026-06-19: Vue/Inertiaの適用範囲

- 決定: 検索画面をInertia/Vue化し、入力ごとの結果はJSON GET APIで取得する。
- 理由: リアルタイム操作を実現しつつ、入力ごとのページprops再取得を避ける。
- 対象外: 学校一覧と問題表示の全面移行。

### 2026-06-19: レイアウト例外

- 決定: NSystem検索では社内用 `AppLayout` ではなく専用 `NSystemDemoLayout` を使用する。
- 理由: `AppLayout` は社内ナビ、通知、Echo、keep-alive等を持ち、外部クライアント用ゲスト画面には不適切。
- 継承規則: 重複ラッパー禁止、route()の名前付き引数、レスポンシブ、カード規則、安全なテキスト描画。

### 2026-06-19: DB構造

- 決定: マイグレーションは追加せず、既存FULLTEXTを候補抽出に使う。
- 理由: 2,247件規模では外部検索エンジンや検索専用テーブルを追加せずに要件を満たせる。

---

## 4. 要確認事項

ユーザー承認時に、次を設計承認に含むものとして扱う。

- 検索画面のみInertia/Vue化する
- 社内用AppLayoutをNSystemには使用しない
- 既定検索は「そのまま含む」
- 「すべての語」「いずれかの語」を選択可能にする
- 問題本文のみを検索し、解答検索は今回対象外とする

仕様変更希望がある場合は、実装開始前に本欄と設計書を更新する。

---

## 5. 作業ログ

### 2026-06-19

- `NSYSTEM_GUIDE.md`、`CLAUDE.md`、`AGENTS.md` を確認。
- `CONSOLIDATED_01_layout_and_ui.md` を確認。
- NSystemのController、Model、migration、Blade、route、import処理を調査。
- ngram自然言語検索による部分token一致と、先頭200文字固定プレビューを誤ヒット原因として特定。
- 取り込み元JSONで「平安時代」「光合成」「方程式」の完全文字列と2文字gram候補の差を確認。
- `AppLayout` が社内ロールナビ、通知、Echo、keep-aliveを含むことを確認し、NSystem専用レイアウト方針を決定。
- `NSEARCH_PLAN1.md`、`NSEARCH_MANAGER1.md`、`NSEARCH1_PROMPT.md` を作成。
- コード実装、DB変更、build、テストは未実施。
- ユーザーから設計承認を受領し、Phase 1の実装を開始。
- Request、検索Service、JSON結果APIを実装。
- exact/all/any、絞り込み、LIKE特殊文字、ページング、スニペットのテストを追加。
- NSystem専用Inertiaレイアウトとリアルタイム検索Vue画面を実装。
- 300ms debounce、IME入力、通信キャンセル、URL状態保持を実装。
- 問題画面に大問アンカーと遷移時強調を追加。
- 実DBで「平安時代」の厳密検索が68件として正常に実行されることを確認。
- NSystemテスト12件（45 assertions）成功。
- `npm run build` 成功（既存コード由来のVite glob非推奨警告のみ）。
- ESLint対象5ファイルはエラー・警告なし。
- 実MySQLで exact「平安時代」68件、all「平安 京都」38件、any「平安 京都」221件、exact「10%」42件を確認。
- `NSYSTEM_GUIDE.md` と `CONSOLIDATED_01_layout_and_ui.md` を更新。
- ChangelogSeederへ `nsystem-search-1` を追加し、ローカルDBへ反映。
- 実行環境にGUIブラウザ/Playwright等がないため、PC・モバイルの目視確認は未実施。レスポンシブクラス、Inertia応答テスト、buildで検証。
