# TRAINS_MANAGER2.md — 交通費精算（支流）進捗管理

設計: `TRAINS_PLAN2.md` / 新セッション用: `TRAINS2_PROMPT.md` / 既存: `TRAINS_MANAGER1.md`（フェーズ1の実績）

## ステータス
| 段階 | 状態 | 備考 |
|---|---|---|
| A 現状調査・課題整理 | ✅ | 課題19項目を提示。配置4項目は確認済み（決定事項3〜6） |
| B 申請画面の改善（SuperAdmin 上） | 🟡 実装・自動テスト済み／ユーザーのブラウザ確認待ち | 通知は未実装（D-1） |
| C 受理（管理）画面の完成 | 🟡 実装・自動テスト済み／ユーザーのブラウザ確認中（C-1〜C-7 OK。通知とハブの確認待ち） | 総務・経理の画面は「申請」ハブ → 交通費（`/clerk/billing/transport`）。SuperAdmin の「交通費一覧」は同じ画面。通知は「お知らせ」 |
| D ユーザー・総務への移行 | 🟡 実装・自動テスト済み／ユーザーのブラウザ確認待ち | 総務側は段階 C で完了。申請者側: `user.billing.transport.*`＋ハブ `user.applications.index` |
| E 仕上げ・本流への報告 | ⬜ | D-10 は実装済み（確認待ち）。残り: 旧画面の削除（D-16）・更新ログ・CONSOLIDATED・本番デプロイ・本流への報告 |

## 決定事項
| # | 内容 | 日付 |
|---|---|---|
| 1 | 交通費は本流の支流として別 Claude が進める。SuperAdmin で調整し、うまく動いたらユーザー（申請ボタン）・総務（管理画面）へ移行 | 2026-10-18 |
| 2 | 駅すぱあと連携（Ph2）は買い切りキー購入後。指示があるまで着手しない | 2026-05-24 |
| 3 | 配置: 両社共通（サンエー印刷・サン・ブレーン）。SunA には置かず `Billing/Transport` を拡張、テーブルは `transport_*` のまま | 2026-10-18 |
| 4 | 入口: 独立タブ／ボタン（`suna_ga_application_types` には載せない） | 2026-10-18 |
| 5 | 受理権限: Clerk ロール全員（個別の権限テーブルは作らない） | 2026-10-18 |
| 7 | 申請単位は「ユーザー × 対象月」で1ヶ月1申請。期間は月初〜月末（8月分=8/1〜8/31）。範囲外の発生日は入力エラー。従来の締め日11日は廃止 | 2026-10-18 |
| 8 | 紙で印刷して受理する運用が続く。申請時にデータ＋印刷用PDFを総務へ送る。出力は雛形 `SB_trains.xlsx`（Excel）と Blade の PDF。25行超は複数枚に自動継続 | 2026-10-18 |
| 9 | 部門は会社ごと: 選択肢＝その会社に登録された有効な部署＋「共通」。初期値は本人の所属。用紙はサンエー印刷もサン・ブレーン様式（SB_trains）を使い、部門欄だけ差し替え（サン・ブレーン=数字コードつき5択、他社=登録部署名を1つ表示）。サンエー独自様式が決まったら雛形を会社別に分ける | 2026-10-18 |
| 10 | 入力補助は C 案で実装: ①まとめて貼り付け（Excel 行・タブ/カンマ区切り、確認画面つき）②登録した行先＋候補（登録と過去12か月の履歴）＋クイック入力 ③複数日に追加・複製・Enter で次の行。④Excel ファイル取込は見送り（D-11） | 2026-10-20 |
| 11 | 総務・経理の検算は B 案で実装: 運賃マスタ（検査済み/未検査/登録したい・検算ボタン・申請から自動で未検査に溜める）＋申請の照合（行ごと）＋自動チェック（重複・休日・金額の異常・区間の誤り・前回との違い）＋行ごとの確認チェック＋差戻しコメントへの行指定。C 案の 7〜9（未申請者の一覧・定期券区間・明細CSV）は未実装（D-14） | 2026-10-20 |
| 12 | 段階 D: 申請者の入口は、ユーザー用のタブメニューの「申請」→「交通費」（`/user/applications` → `/user/billing/transport`）。機能は全ロールが自分の分を使える。確認期間中は「申請」タブの表示を SuperAdmin・Admin・Clerk だけに限定（`UserNavigationTabs.vue` の `APPLY_TAB_ROLES`。全員に広げるときは `showApply` を常に true に）。SuperAdmin 用の旧ボタン・画面は、移行の確認後に削除する（D-16） | 2026-10-20 |
| 6 | 経理を含める: 総務・経理の両方の Clerk が受理できる（clerkKind で分けない） | 2026-10-18 |

## 後回し事項（Deferred Backlog）
- D-1: （解消 2026-10-20）通知は「お知らせ」（`Announcement`）で実装（当初は社内メッセージだったが、ユーザーが見る画面ではないため切り替え）。申請・再申請・期限後の依頼→同じ会社の Clerk・Admin と SuperAdmin、受理/差戻し/請求済み/期限の許可・見送り→申請者
- D-2: （解消 2026-10-20）旧「請求データ作成」の画面・ルート・`BillingRequestController`・`TransportBillingRequest`・旧PDFを削除（DB のテーブル `transport_billing_requests` と列 `transport_expenses.billing_request_id` は、既存データ保護のため残す。不要と確認できたら、別の migration で削除）
- D-3: （解消 2026-10-20）旧 `billing_pdf.blade.php` ごと削除
- D-8: お知らせ（`announcements.index` / `show`）を開くと、Clerk でもユーザー用タブになる（共通レイアウトが、ルートの接頭辞でタブを決めているため）。ユーザーから「後の修正でよい」との指示。共通レイアウトに関わるので、別途相談して直す
- D-9: サンエー印刷の旧「申請一覧」（`clerk.general_affairs.applications.index`、`SunA/GeneralAffairs/Applications/Index.vue`）は、Clerk の「申請」タブからは使わなくなった（ハブ `clerk.applications.index` に付け替え）。URL は残してある。年末調整の側の担当が申請一覧を変えたときは、ハブ（`Billing/Applications/Index.vue`）にも反映が必要
- D-10: （実装済み 2026-10-20）「当月末日を過ぎたら申請不可」。仕様・決定・実装メモは PLAN2 §8。ローカルで過去の月を試すときは `.env` の `TRANSPORT_ENFORCE_DEADLINE=false`（本番は true のまま）
- D-11: 入力補助の④「Excel ファイルを選んで取り込む」は未実装（貼り付けで代用できる）。必要になったら、貼り付けと同じ確認画面を使って作る
- D-12: 「よく使う行き先」の登録・候補・クイック入力は、現状 SuperAdmin のルート（`superadmin.billing.transport.presets.*`）。段階 D（ユーザーの「申請」への移行）で、ユーザー用ルートに付け替える。候補は、ひらがなからカタカナの行先には当たるが、漢字の行先は読みのデータが無いので、ひらがなでは当たらない
- D-13: 自動保存の既知の制約: 同じ月を 2 つのタブで同時に開いて編集すると、あとから保存したほうで上書きされる（排他制御はしていない）。長い文字の縮小は、PDF は 10pt → 下限 5.5pt、それでも入らない分は切れる（Excel は「縮小して全体を表示」）
- D-14: 検算の C 案の分（未申請者の一覧とお知らせでの催促／定期券区間のチェック／明細単位の CSV）は未実装。金額の上限の目安は `config/transport.php`（`TRANSPORT_MAX_ROW_AMOUNT`、既定 5,000 円）で変更。運賃の「要再確認」は検査から 365 日（`TRANSPORT_RECHECK_AFTER_DAYS`）。会社ごとに画面で変えたくなったら設定テーブルを作る
- D-15: 運賃マスタの `use_count` は「申請された回数の目安」（再申請でも加算される）。駅すぱあと API を入れるときは、`source='ekispert'` で検査済みを自動登録し、台帳の画面に「駅すぱあとで確認」ボタンを足す
- D-16: （解消 2026-10-20）SuperAdmin 用の交通費の画面を、すべて削除（交通費入力・旧請求データ・交通費一覧・運賃マスタの SuperAdmin 用の入口・タブ）。SuperAdmin も、総務・経理（Clerk）と同じ画面（`clerk.billing.transport.*`）と、ユーザー用の入口（`user.billing.transport.*`）を使う。残件: 「申請」タブの表示範囲（`UserNavigationTabs.vue` の `APPLY_TAB_ROLES`）を、いつ全員に広げるか
- D-5: 原本の「申請者」欄にある印影（PDF・Excel とも未対応。原本 xlsx の印影は個人の印鑑画像なので、雛形からは除去済み）。以前のメモ: 原本の「申請者」欄にある印影（赤い丸印）は未対応。電子印影の扱い（登録・自動押印の要否）は未決定
- D-6: （解消 2026-10-19）Excel も原本 `SB交通費金銭請求伝票_23新.xlsx` の新様式（30 行）に切り替え済み
- D-7: dompdf の日本語フォント（NotoSansJP）をサブセット化すると、Chrome 等で日本語が表示されない既存不具合を確認。交通費の PDF は `enable_font_subsetting=false` で回避済み。dompdf を使っているのは交通費のみ（`app/` 内）。旧請求書PDF（`BillingRequestController::exportPdf`、D-2 で廃止予定）は未対応。`config/dompdf.php` は true のまま
- D-4: （解消 2026-10-20）旧 `List.vue`・`ExpenseListController` は削除し、`Pages/Billing/Transport/Manage/{Index,Show}.vue` + `ManageController` に置き換え

## 本番に必要な手作業（デプロイ時）
- migration あり: `2026_10_18_120000_add_monthly_review_columns_to_transport_expenses`（status を文字列化＋列追加＋ユーザー×月の一意制約）
- **デプロイ前に本番の `transport_expenses` に同一ユーザー・同一月の重複が無いか確認**（重複があると migration は例外で止まる。止まるだけでデータは壊さない）。SSH はユーザー承認後
- migration あり（2本目）: `2026_10_18_130000_add_company_and_charge_department_to_transport_expenses`（`company_id`・`charge_department_id` 追加。既存行は申請者の会社・旧コードと同名の部署で補完）。**本番に交通費データがある場合、旧コード 20「制作」に対応する部署が会社登録に無いと、その行の部門は「共通」になる**
- migration あり（3本目）: `2026_10_19_100000_add_base_amount_and_auto_double_to_transport_expense_items`（明細に片道の金額・×2 を保存）。既存行は NULL/false のまま（見え方は変わらない）
- migration あり（4本目）: `2026_10_20_100000_create_transport_expense_events_table`（状態変更の履歴）
- migration あり（8本目）: `2026_10_21_130000_add_request_columns_to_transport_deadline_extensions`（期限の解除の「依頼」の状態 status ほか。既存行は granted）
- migration あり（7本目）: `2026_10_21_120000_create_transport_deadline_extensions_table`（期限の解除の記録）。**本番の `.env` に `TRANSPORT_ENFORCE_DEADLINE` を書かない（既定の true で動く）。false にすると、期限が効かなくなる**
- migration あり（6本目）: `2026_10_21_100000_create_transport_fares_table`（運賃マスタ `transport_fares`＋明細の `checked_at`/`checked_by`）
- migration あり（5本目）: `2026_10_20_120000_create_transport_presets_table`（よく使う行き先）
- ローカルで他作業の未実行 migration が溜まっているため、ローカル実行は必ず `--path=` で自分の migration だけ指定
- ziggy.js: ローカルで再生成済みだが、本流（年末調整）のルートも反映されている。分離デプロイでは origin/main のルートと突き合わせて、自分の差分（transport の update 削除・submit/withdraw 追加）だけを使う

## 作業ログ
| 日付 | 内容 |
|---|---|
| 2026-10-18 | 支流として開始。本流の Claude が PLAN2 / MANAGER2 / PROMPT を作成 |
| 2026-10-18 | 段階B実装: migration（列追加・一意制約・status文字列化）、`SaveExpenseRequest`（サーバー整形・月範囲検証）、`TransportExpenseExporter`（Excel複数シート／PDF複数ページ・小計/合計）、`ExpenseController` 全面改修（月単位・保存/申請/取り下げ/削除・所有者チェック）、`Index.vue`/`ExpenseRow.vue`/`transportNormalize.js` 作り直し。PDF の太字による文字化け（既存不具合）を修正。Pest 8件パス（`tests/Feature/Billing/TransportExpenseMonthlyTest.php`）。npm run build 済み |
| 2026-10-20 | 不具合修正: 自動保存でエラーになったあと、保存済みと同じ値に直すと、赤いエラー表示が残る問題を修正（保存済みの内容に戻ったら、エラー表示を消す）。ユーザー要望: 明細の行頭に行番号（申請者の入力画面と、総務・経理の詳細画面）。ユーザー確認 I-1〜I-9 OK |
| 2026-10-20 | ユーザー要望: ①長い文字は、PDF はセルに収まるまで文字を 0.5pt 刻みで小さく（`TransportExpenseExporter::fitFontSize`、縦位置も補正）、Excel は「縮小して全体を表示」。②自動保存: 入力が止まって 1.5 秒で下書き保存（`POST autosave`・JSON・画面は書き換えない・申請はしない）、状態表示（入力中／保存中／自動保存しました／できません）、末尾の空行は保存しない、保存できない状態での離脱（閉じる・タブ移動・月の切替）と、PDF/Excel 出力前は、先に自動保存し、できなければ確認。Pest 34件パス・ビルド済み |
| 2026-10-20 | 入力補助（C案）を実装: `transportInput.js`（貼り付けの読み取り・日の指定・クイック入力・候補の当てはめ。Node で実例 20 項目を確認）、`PasteImportModal`/`MultiDateModal`/`PresetManagerModal`、`ExpenseRow`（複製・複数日・登録・Enter で次の行・行先の候補）、`Index.vue`（入力補助の枠・クイック入力）、`TransportPreset`/`PresetController`、候補データ（登録＋過去12か月の履歴）、migration（presets）。入力中の行が再読み込みで消えないよう、画面の再読み込み判定を「中身が変わったときだけ」に変更。Pest 28件パス・ビルド済み・ziggy 再生成 |
| 2026-10-20 | ユーザー指示で変更: 通知を社内メッセージから「お知らせ」（Announcement）に切り替え（本文に「申請→交通費」の案内。URL は入れない）。Clerk の「申請」タブを全 Clerk（両社・総務/経理）に出してハブ `clerk.applications.index`（`ApplicationHubController`・`Billing/Applications/Index.vue`）に付け替え。ハブは交通費カード（申請済み件数バッジ）＋サンエー印刷の総務のみ申請の種類（年末調整など）。「交通費精算」独立タブは廃止。Pest 23件パス・ビルド済み・ziggy 再生成 |
| 2026-10-20 | 段階C実装: 履歴表 `transport_expense_events`、`TransportExpenseWorkflow`（状態遷移・再確認・履歴・通知）、`TransportNotifier`（社内メッセージ）、`ManageController`（一覧・詳細・受理/差戻し/請求済み/取り消し・月次CSV）、ルート（`clerk.billing.transport.*` と `superadmin.billing.transport.manage.*`、`list` は管理画面に付け替え）、`ClerkNavigationTabs` に「交通費精算」追記（general の許可リストにも追加）、申請者画面に履歴表示。旧 `List.vue`/`ExpenseListController` 削除。Pest 22件パス（`TransportExpenseManageTest` 8件）・ビルド済み・ziggy 再生成 |
| 2026-10-19 | Excel を原本 `SB交通費金銭請求伝票_23新.xlsx` の新様式に切り替え: 原本から旧様式シート・印影画像・サンプル値を除いた雛形 `resources/billing/transport_expense_template.xlsx` を作成（要コミット）。30行/枚、計=37行目、○ は原本の楕円図形が読み込めないため GD の透過PNGで描き直し（GD 無しなら太字・下線にフォールバック）。日付は日付の値のまま、金額の書式は雛形のまま。原本とのセル値・書式・列幅・行高・印刷設定の突き合わせで差は無し。Pest 14件パス |
| 2026-10-19 | PDF を原本（SB交通費金銭請求伝票_23新.pdf）に合わせて作り直し: 表をやめ罫線と文字を座標配置（原本の寸法を A4 に縮小）、1枚30行、所属=会社名、部門は選択コードを○囲み（他社は部署名を○囲み）、区間は出発/到着を左右に振り分け、空行にも「－」、計/小計/合計、押印欄。文字位置は原本との差1〜3pt以内。dompdf の日本語フォント サブセット化不具合を回避。Excel の所属も会社名に。Pest 14件パス |
| 2026-10-19 | ユーザー要望: 保存した往復の「片道金額」と「×2」を復元できるよう保存（明細に base_amount / auto_double を追加、サーバーで片道×2 を検算）。旧データは合計のまま表示。Pest 13件パス・ビルド済み |
| 2026-10-18 | ユーザー要望: 用件が「往復」のとき「×2」を自動チェック（新規行・用件を往復に切替時。保存済みの行を開くときは二重計上を避けるため自動チェックしない）。入力エラーを日本語化（SaveExpenseRequest::messages） |
| 2026-10-18 | 部門を会社別に変更（決定事項9）: `TransportCompany`（会社解決・部署一覧）、migration 2本目、Exporter の部門欄を様式で分岐、Index.vue の選択肢を会社の部署に。Pest 10件パス。ステップ1-4（部門の初期値）はサン・ブレーン側 OK／サンエー側は未確認 |
| 2026-10-18 | 段階A完了。配置の確認4項目（両社共通／独立タブ／Clerk全員／経理含む）を決定。次は設計提示→確認 |
| 2026-10-20 | 総務・経理の検算（B 案）: `TransportFare`（運賃マスタ。駅名の正規化）、`FareController`、`TransportFareChecker`（運賃の照合・重複・休日・金額の異常・区間の誤り・前回との違い・台帳での合計の自動計算）、`TransportFareStock`（申請時に未検査として自動ストック）、行ごとの確認チェックと「一致した行をまとめて確認済み」、差戻しコメントへの行の挿入、画面（運賃マスタ・詳細・一覧のバッジ・ハブのカード）。ユーザー指摘で運賃マスタの画面を最小化し、「運賃を調べる」を削除。確認の操作を表の下へ |
| 2026-10-20 | 申請書の右上に「from SunBWork　申請No.（番号）」（PDF・Excel）。申請 No. は一覧・詳細・申請者の画面にも表示 |
| 2026-10-20 | 段階 D: 申請者側ルートを共通クロージャ `$registerTransportApplicantRoutes` にまとめ、SuperAdmin 用とユーザー用（`user.billing.transport.*`）の両方に登録。入力画面を `Billing/Transport/Apply/Index.vue` に移して共用。ユーザー用ハブ `UserApplicationHubController`＋`UserIndex.vue`。`UserNavigationTabs` に「申請」タブ（SuperAdmin・Admin・Clerk のみ表示） |
| 2026-10-20 | 月末ルール（D-10）: `TransportDeadline`（期限＝月末 23:59:59 JST。差戻しは期限後でも再申請可。総務・経理の操作は影響なし。期限後は閲覧のみ・下書きは削除可・取り下げ不可）、`config/transport.php` の `enforce_deadline`（本番は true）。期限の解除は、ユーザー提案で「申請者が依頼 → 総務・経理が許可・却下」に: `DeadlineRequestController`、`DeadlineController::approve/reject`、`TransportNotifier`（依頼→総務・経理、許可/見送り→申請者）、`transport_deadline_extensions`（status: requested/granted/rejected）、申請者の画面（依頼フォーム・依頼中・見送り）、総務・経理の一覧（依頼の一覧＋許可/却下、履歴、直接許可）、ハブのバッジ。Pest 68件パス・ビルド済み・ziggy 再生成 |
| 2026-10-20 | 不具合修正（ユーザー指摘）: 申請・再申請・期限後の依頼の通知が、同じ会社の「Clerk」だけに送られ、SuperAdmin・Admin に届かなかった（本人が Clerk で、ほかに Clerk がいないと、誰にも届かない）。宛先を「同じ会社の Clerk・Admin ＋ SuperAdmin（会社を問わない。社長が両社を共同管理するため）」に変更（本人は除く、`TransportNotifier::managers`）。差戻しのあとの申請は、「再申請しました」の別の文面に（`TransportExpenseWorkflow` が差戻しからの申請かを判定）。Pest 69件パス |
| 2026-10-20 | ユーザー指示: 期限後の申請の依頼・許可は、先月分まで（それより前の月は、依頼も許可もできない）。`TransportDeadline::requestable`、`config/transport.php` の `request_months_back`（既定 1）、依頼・直接許可のサーバー側の検証、申請者の画面（先月より前の月は、依頼の案内の代わりに「支払いの対象外」と表示）、管理側の直接許可の月の選択肢を先月分だけに |
| 2026-10-20 | SuperAdmin 用の交通費の画面を削除（D-16）: 旧「交通費入力」・旧「請求データ」（`BillingRequestController`・`TransportBillingRequest`・`Billed.vue`・`billing_pdf`）・「交通費一覧」「運賃マスタ」の SuperAdmin 用ルート（`superadmin.billing.transport.*`）・`SuperAdminNavigationTabs` の 2 タブを削除。管理側の画面は Clerk 用（`clerk.billing.transport.*`）だけにして、SuperAdmin も同じ画面を使う。申請者側はユーザー用（`user.billing.transport.*`）だけ。テストのルート名を付け替え。Pest 71件パス |

## 本流への報告（作業完了時に記入）
- 変更ファイル:
- 追加 migration:
- デプロイ状況:
- 本番で必要な手作業:
- 本流に影響し得る変更:
- 後回し事項:
