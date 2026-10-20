# TRAINS2_PROMPT.md — 交通費精算（支流）新セッション用プロンプト

下の「▼ここから」以降を、新しい Claude のセッション冒頭にそのまま貼る。

---
▼ここから

あなたは SunBWork（Laravel 11 / Vue 3 / Inertia、印刷・組版会社の社内メンバー管理サイト）で、**交通費精算機能**を仕上げる担当です。別の Claude が同じプロジェクトで総務の「年末調整」などの本流を進めており、あなたは支流として並行します。

## まず読むもの（この順）
1. `/home/w229/SunBwork/CLAUDE.md`（プロジェクトの規約。日本語で返信・質問は 1 つずつ・設計を先に示して確認・ブラウザ確認はユーザーが行う）
2. `z_instructions/TRAINS_PLAN2.md`（今回の目的・段階・配置の確認事項・衝突防止・分離デプロイ方式）**特に §6**
3. `z_instructions/TRAINS_MANAGER2.md`（進捗。作業のたびに更新する）
4. `z_instructions/TRAINS_PLAN1.md` / `TRAINS_MANAGER1.md` / `TRAINS1_PROMPT.md`（フェーズ1の設計と実績）
5. `z_instructions/CONSOLIDATED_01_layout_and_ui.md`（新規ページ前に必須）、日付を扱うので CLAUDE.md の「UTC / JST 混在ルール」
6. 既存の実装: `app/Http/Controllers/Billing/Transport/*`、`app/Models/Transport*.php`、`resources/js/Pages/SuperAdmin/Billing/Transport/*.vue`、`routes/web.php` の `billing/transport`

## 目的
SuperAdmin 専用の「入力申請」（`/superadmin/billing/transport`）と「受理画面」（`.../transport/list`）は途中で、申請画面としてこなれていない。SuperAdmin 上で調整し、うまく動いたら、**ユーザーには「申請」ボタンから行ける交通費の項目、総務には管理画面**へ移行する。

## 最初にやること
- 既存の 3 画面と Controller を読み、**不足・使いにくい点を一覧化してユーザーに示す**（ブラウザでの確認はユーザーが行うので、画面の挙動は実装から読み取る）
- PLAN2 §4 の配置の確認事項（サンエー印刷専用か両社共通か／入口／権限／経理を含めるか）を、**1 つずつ**ユーザーに質問する。答えが出るまで移行（段階 D）の実装は始めない
- 設計（方針・影響範囲・ファイル一覧）を示して確認を取ってから実装する

## 絶対に守ること（詳細は PLAN2 §6）
- ローカル DB のユーザーの実データを消さない・更新しない（テストは「確認用」名で、作った ID だけ削除。条件削除禁止。全テーブルを作り直す系のコマンドは禁止）
- 本流が使うファイル（`routes/suna.php`、`app/**/SunA/**`、年末調整関連、`CLAUDE.md`、共有の `AppLayout.vue` / `*NavigationTabs.vue` / `routes/web.php` / `ziggy.js` / `ChangelogSeeder`）は必要最小限の追記のみ。編集前に `git diff` で他者の未コミット変更を確認
- `git add` は自分のファイルを明示パスで。`git add -A` / `git stash` / `git reset` / `git checkout .` は禁止。作業ディレクトリは `colayout-deploy` ブランチで、他の作業の未コミット変更を含むことがある
- 本番デプロイは**ユーザーが明示的に指示したときだけ**。`DEPLOY_SAKURA.md` と PLAN2 の「分離デプロイ方式」に従い、push と SSH コマンドは事前にユーザーへ提示して承認を得る
- Vue/JS を変えたら `npm run build`。artisan は `docker compose exec -T --user sail laravel bash -lc "..."`
- 日本語フォームのルール（全角/半角・金額カンマ・日付 1 欄入力・例の常時表示）は CLAUDE.md を正とする

## 作業完了時
`TRAINS_MANAGER2.md` の「本流への報告」欄を埋め、変更ファイル・追加 migration・デプロイ状況・本番で必要な手作業・本流に影響し得る変更・後回し事項を、ユーザーが本流の Claude へ貼れる形で出力する。更新ログ（`ChangelogSeeder`）と `CONSOLIDATED_*.md` の追記も行い、完了したら PLAN2/MANAGER2/PROMPT を `z_instructions/archived/` へ移す（後回し事項が残る場合は移さない）。
