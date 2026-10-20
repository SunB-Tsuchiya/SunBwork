# TRAINS_PLAN2.md — 交通費精算（支流プロジェクト）設計

作成日: 2026-10-18 / 親プロジェクト: SunBWork（本流＝サンエー印刷対応・総務「年末調整」など）
既存の資料: `TRAINS_PLAN1.md`（フェーズ1の詳細設計）/ `TRAINS_MANAGER1.md`（フェーズ1完了・フェーズ3未着手）/ `SB_trains.xlsx`（出力テンプレート）

## 1. 目的
SuperAdmin 専用で作った交通費の「入力申請」と「受理（一覧）」を、実運用できる申請画面に仕上げ、うまく動いたら次のように移行する。

| 利用者 | 移行後の入口 |
|---|---|
| ユーザー（従業員） | 「申請」ボタンから交通費の項目へ（申請者の画面） |
| 総務（clerk） | 管理画面（受理・確認・請求済み・出力） |
| SuperAdmin | 調整用に当面残す（残すか消すかは最後にユーザーが決める） |

## 2. 現状（2026-10-18 時点。本番さくらにも存在する）
- URL（本番）: `/members/superadmin/billing/transport`（入力）、`.../transport/list`（受理・一覧）、`.../transport/billed`（請求済み）
- ルート: `routes/web.php` の `billing/transport`（SuperAdmin グループ内、`superadmin.billing.transport.*`）
- Controller: `app/Http/Controllers/Billing/Transport/{ExpenseController, ExpenseListController, BillingRequestController}.php`
- Model: `TransportExpense` / `TransportExpenseItem` / `TransportBillingRequest`（テーブル `transport_expenses` / `transport_expense_items` / `transport_billing_requests`。migration は 2026_05_24_*）
- Vue: `resources/js/Pages/SuperAdmin/Billing/Transport/{Index,List,Billed}.vue`、タブは `SuperAdminNavigationTabs.vue`
- 出力: PDF/Excel（`SB_trains.xlsx` テンプレートに流し込み。PhpSpreadsheet + dompdf）
- 状態: draft / submitted のみ（`submit` 後の扱いは未検討＝「途中」）。駅すぱあと API（Ph2）は未着手（買い切りキー購入待ち）
- ユーザーの評価: 「途中になっている」「申請画面としてこなれていない」

## 3. 進め方（段階）
| 段階 | 内容 | 場所 |
|---|---|---|
| A 現状調査・課題整理 | 既存の3画面・Controller を読み、不足と使いにくい点を一覧化してユーザーに提示（質問は 1 つずつ） | — |
| B 申請画面の改善 | 入力のしやすさ（日本語フォームルール・下記 §5）、状態遷移（下書き→申請→受理/差戻し→請求済み）、通知 | SuperAdmin 画面で調整 |
| C 受理（管理）画面の完成 | 受理・差戻し・コメント・月次集計・出力 | SuperAdmin 画面で調整 |
| D ユーザー・総務への移行 | 下記 §4 の配置判断をユーザーに確認してから実装 | 申請メニュー／総務の画面 |
| E 仕上げ | 更新ログ（ChangelogSeeder）・CONSOLIDATED 追記・本流へ報告 | — |

駅すぱあと連携（Ph2）は購入待ちのため、ユーザーから指示があるまで着手しない。

## 4. 配置の判断（ユーザーに 1 つずつ確認してから決める。勝手に決めない）
- **サンエー印刷専用か、両社共通か**: 本流のルール（CLAUDE.md「SunA 配置ルール」）では、サンエー印刷専用機能は `SunA/{GeneralAffairs}` 配下・テーブル `suna_ga_*`。交通費を両社で使うなら共通機能なので SunA に置かない（既存の `Billing/Transport` を拡張）。**どちらかを最初に確認する**
- 入口: 本流で総務の「申請」メニュー（`suna_ga_application_types`、`feature_key` で機能を登録）と、ユーザー側の申請ボタンを作った。交通費をその仕組みに載せるか、独立タブにするかを確認する（載せるなら `ApplicationType` に `feature_key=transport` を登録）
- 権限: 総務の「受理」を誰ができるか（clerk 全員か、権限管理で個別か）。本流の「社員情報権限管理」（`suna_ga_staff_permissions`）と同じ方式を使うか確認
- ロール: Clerk = 総務/経理（general 会社では `clerkKind`）。経理を含めるかを確認

## 5. 守るルール（本流と共通。詳細は CLAUDE.md を正とする）
- 返信・コメントは日本語。質問は 1 つずつ。**設計を先に示して確認を取ってから実装**
- 日本語フォーム: 全角テキスト・半角数字・金額は円の整数＋入力中カンマ・日付は 1 欄入力（`19770905` 等）・例は入力欄の下に常時表示・複数人が開く画面は編集ロック。部品は `resources/js/Components/SunA/GeneralAffairs/Nencho/Fields/`（`normalize.js` の `v-norm` ほか）が参考になる（コピーせず、共通化が必要なら本流に相談）
- 日付は JST 前提（CLAUDE.md「UTC/JST 混在ルール」）。発生日・請求月は `date:Y-m-d` キャスト
- 新規ページ作成前に `CONSOLIDATED_01_layout_and_ui.md` を確認（AppLayout・戻るボタン・スロット）
- ナビゲーションは `route()`、CSRF は meta タグ（さくら本番で必須）
- Vue/JS を変えたら `npm run build`、artisan は `docker compose exec -T --user sail laravel bash -lc "..."`
- **ブラウザでの動作確認はユーザーが行う**（依頼されたときだけ操作する）
- 個人情報・金額を扱うので、権限のない人が他人の申請を見られないこと（Controller で user_id / 権限チェック）

## 6. 本流との共有・衝突防止（最重要）
本流（総務・年末調整）と同じリポジトリ・同じローカル DB・同じ本番を使う。次を厳守する。

1. **ローカル DB のユーザーの実データを消さない・更新しない。** テストデータは「確認用」名で作り、作った ID だけを削除する（条件削除禁止）。既存行がある場合は中止。全テーブルを作り直す・削除する系のコマンドは禁止（過去にローカル DB 全消去の事故あり）
2. **本流が触るファイルを必要最小限にしか変更しない。** 共有の衝突ポイント: `routes/web.php`（`routes/suna.php` は本流の領域）、`resources/js/ziggy.js`（ルートを足したら再生成）、`resources/js/layouts/AppLayout.vue`、`resources/js/Components/Tabs/*NavigationTabs.vue`、`HandleInertiaRequests.php`、`CLAUDE.md`、`ChangelogSeeder`、`CONSOLIDATED_*.md`。編集は追記中心にし、編集前に `git diff` で他者の未コミット変更を確認する
3. **コミット・push は自分のファイルだけを `git add <明示パス>` する。** `git add -A` / `git add .` / `git stash` / `git reset` / `git checkout .` は禁止（作業ディレクトリには本流など他の作業の未コミット変更が混ざることがある）
4. **本番デプロイはユーザーが明示的に指示したときだけ。** 手順は `DEPLOY_SAKURA.md`、かつ本流が 2026-10-18 に使った「分離デプロイ」方式（下記）を使う
5. 新しい migration は番号の衝突を避けるため、作成日時のタイムスタンプ（`2026_MM_DD_HHMMSS_`）で作り、既存の migration は編集しない。本番に migrate が必要なことを MANAGER に必ず記録する

### 分離デプロイ方式（他の作業を巻き込まない）
1. `git fetch origin` → `git worktree add -b trains2-deploy-<日付> /tmp/sunbwork-trains2-deploy-<日付> origin/main`
2. 自分のファイルだけをそのワークツリーへコピー（重なる共有ファイルは、自分の追記分の差分だけを `git apply` で適用）
3. ワークツリーに `node_modules` / `vendor` を symlink、`.env` をコピーして**ワークツリー側の `.env` だけ** `VITE_APP_BASE_PATH=/members` にして `npm run build`（ローカルの `.env` は空のまま。切り替え・戻しが不要）。ルートを足した場合の `ziggy.js` は、ローカルで再生成したものを使う（上流がルートを変えていないことを確認）
4. ソースとビルド（`public/build`）を別コミットにして、`git push origin <branch>:main`（non-fast-forward で拒否されたら `git fetch` → 重なりがないことを確認 → `git rebase origin/main` → 再 push）
5. さくら: 事前に `git rev-parse --short HEAD` で本番が origin/main の直前と一致するか確認 → ユーザーに SSH コマンドを提示して承認を得てから `git pull && php artisan migrate --force && php artisan config:clear && php artisan cache:clear`（ChangelogSeeder も反映するなら `--force` 付きで）

## 7. 本流への報告（作業完了時）
`z_instructions/TRAINS_MANAGER2.md` の「本流への報告」欄を埋め、ユーザーが本流の Claude に貼れる形で出す。内容: ①変更したファイル一覧（共有ファイルは変更箇所）②追加した migration ③デプロイ状況（本番反映済みか）④本番で必要な手作業（権限付与・設定登録など）⑤本流に影響し得る変更（共有部品・ナビ・ルート）⑥後回し事項

## 8. 最終仕様（2026-10-20 実装済み）: 当月末日を過ぎたら申請不可
2026-10-20 にユーザーから指示。**会社のルールとして、当月末日を過ぎたら申請できない。** 現在はテストのため、過去の月も入力・申請できる（意図的に未適用）。最終的には、過去の月の申請を不可にする。

**仕様**
- 対象月（`billing_month`）の末日（JST の 23:59:59）を過ぎたら、その月の交通費は新規の保存・申請ができない。
- 例: 8月分は 8/31 まで。9/1 になったら、8月分の申請・保存は不可。
- 対象月の選択肢（申請者の画面）も、過去の月は選べない／入力不可の表示にする。

**実装時の注意**
- 画面だけでなく**サーバー側で必ず検証する**（`ExpenseController::store`・`submit`、および `TransportExpenseWorkflow` の `submit`）。
- 日付は JST（Asia/Tokyo）基準。UTC との差で、末日の判定がずれないようにする（CLAUDE.md「UTC / JST 混在ルール」）。
- 既に申請済みのものを、総務が受理・差戻し・請求済みにする操作は、月末を過ぎても可能にする（制限するのは「申請者の申請」だけ）。
- テストの追加: 末日当日は可、翌日 0:00 以降は不可（`Carbon::setTestNow` で固定）。

**未決事項（実装前にユーザーに確認する）**
- ~~差戻しになったものを、月末を過ぎてから修正して再申請するのは可か不可か。~~ → **決定（2026-10-20）: 可。** 差戻しは総務からの依頼なので、月末を過ぎても、直して再申請できる（制限するのは、新規の保存・申請と、下書きの申請）。
- ~~期限後に、どうしても申請が必要な場合の救済は必要か。~~ → **決定（2026-10-20）: B。** 総務・経理が、特定の人・特定の月について「期限後でも申請してよい」と許可（期限の解除）できる。許可された人だけが、その月を保存・申請できる。許可の記録（誰が・いつ・誰の・どの月を・理由）を残す。代理入力は作らない。 **【変更（同日）】総務・経理の手間を減らすため、「申請者が依頼 → 総務・経理が許可・却下」の流れにした。** 申請者は、期限後の月の画面で、理由を書いて「期限後の申請を依頼する」（依頼中は取り下げられる。却下されたら、依頼し直せる）。総務・経理には、お知らせとバッジで知らせ、依頼の一覧で「許可」「却下」（コメント任意）だけ押す。結果は、申請者にお知らせ。依頼なしの直接の許可も残す（折りたたみ）。
- ~~月の途中で作った下書きが、月末を過ぎて残った場合の扱い／期限を過ぎた月を、申請者の画面でどう見せるか。~~ → **決定（2026-10-20）: A。** 閲覧のみ。入力欄は操作できず、「期限を過ぎているため、入力・申請できません」と表示。内容の確認・PDF/Excel の出力・下書きの削除はできる。総務・経理が期限を解除すれば、その月は、また入力・申請できる。

**実装メモ**: `TransportDeadline`（判定）、`DeadlineController`（解除・取り消し）、`transport_deadline_extensions`（解除の記録）、`config/transport.php` の `enforce_deadline`（環境変数 `TRANSPORT_ENFORCE_DEADLINE`。既定 true。ローカルで過去の月を試すときだけ false。**本番は必ず true**）。取り下げは、期限内（または解除）のときだけ（期限後に取り下げると、再申請できなくなるため）。

**期限後の依頼・許可の対象は、先月分まで（2026-10-20 ユーザー指示）。** 期限（月末）を過ぎたあとに、依頼・許可できるのは、先月分だけ。それより前の月は、依頼も許可もできない（支払いの対象外で、特別な扱いはしない）。過去の月の画面は、閲覧できる。設定: `config/transport.php` の `request_months_back`（環境変数 `TRANSPORT_REQUEST_MONTHS_BACK`、既定 1）。許可・却下の操作は、仕様の変更前に出された古い依頼にも使える。
