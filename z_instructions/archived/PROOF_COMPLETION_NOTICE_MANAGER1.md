# 校正完了通知：管理

2026-11-10。保存基準511c8ec41、本番読取19d6c07da。隔離/tmp/sunbwork-screen-confirm-20261110、feature/myjob-screen-confirm-20261110。

- 調査：完了済み校正依頼162/通知0、旧カレンダー経路の通知呼出し漏れ確認。実例は旧元割当9729、新式元参照NULL。
- 要件：依頼者へ完了通知を送る（利用者確定）。
- 具体設計：PLAN1を作成、確認待ち。
- 実装/合成検証/配備/修正後実通知：未実施。
- 過去通知の補正：未承認・対象外。
- 一般公開：未承認。

次の一手：具体設計の確認後、合成の一般User旧カレンダー経路で再現し修正。旧BOX上部操作/統合メニュー/モーダル開閉の成功試験は反復しない。

診断スクリプト/tmp/proof-event-11516-audit-20261110.php、SHA256 f5266ca4ffed413d220841bd38d63b3e49413fdc9aa010231fec96a508115b4b。承認済み限定SSHでexit0、mysql名一致/READ ONLY rollback。業務本文/氏名/売上/ログを取得せず、更新/送信なし。


## 2026-11-10 具体設計承認・通知欠落再現・実装と合成検証成功

確定: 利用者yesでPROOF_COMPLETION_NOTICE_PLAN1を承認。一般Userの旧カレンダーで校正状態completed/通知0となる失敗を実際に再現（修正前1件4断言/期待通知1に対し0）。共通ProofRequestCompletionを追加し、依頼行lock/元校正マーク/校正状態/strict通知を同一transactionで確定。再操作の過去補正なし。案件/担当/窓口/会社/作成時刻を照合し、候補複数のlatest選択を禁止。同じ案件の普通の作業割当を校正専用画面で一括完了しない。

旧カレンダーの代理操作fallbackも共通処理へ接続。本人の旧カレンダー/MyJob/JobBoxでは既存LegacyReceivedJobCompletionを校正の明示参照にも適用し、既存WorkJobLegacyCompletionとcommit後outboxを利用。直接校正割当は通常依頼の取り込み検証と区別して既存の所有者/会社/部署完了済み照合でsync。これは追加調査で影響先に入ったLegacyReceivedJobCompletion/WorkJobCompletionSyncの修正理由。新式の入力/BOX認可は緩和せず、typed元系列の既存guardも維持。通常自己割当通知抑止・進行表通知・銀本outboxは維持。校正専用User/窓口完了も共通化し、新式元の自動完了を追加しない。

最終Integration104件1122断言すべて成功。旧カレンダー実Controllerの再現修正、入口をまたぐ再操作、通知保存失敗時のジョブ/校正/予定/元マーク/outbox rollbackとToastなし、複数候補、窓口違い、後発依頼、直接proof/旧coordinator参照、無関係作業の維持、別会社を検証。既存新式元未完了維持/入力/通常依頼/会社context/系列も全体成功。合成fixtureに本番にあるproof_coordinator_idを追加し旧『校正は専用fallback』期待を新要件へ更新。SQLite memory/network noneのみ、実DBを試験に使用しない。ログ/tmp/proof-completion-notice-tests-20261110.log。

Changelogは専用ProofCompletionNoticeChangelogSeeder（version proof-notify-20261110）と親呼出し1行のみ。PHPのみでVue/JS/routes/資産/DB列/依存の変更なし、build不要。未配備・修正後の実通知未確認。過去162の再送は未承認のまま。次の一手は調査時本番19d6c07daを取り込み他作業保持を照合、最新本番状態を読み取り固定配備準備へ。


## 2026-11-10 本番19d6保持統合・最終106件1133断言成功、未配備

保存6f34d53b0の修正を本番調査19d6c07da7a7cee072c59daf75cc8fc78ea7a3dfへ無衝突merge（e655d0a91）。営業分析・Excel出力等の他作業を保持。ChangelogSeederは他の登録を残し専用Seeder呼出し1行のみ。manifestは19dとbyte一致、493entry/524参照/欠落0/members base、SHA256 7d4c433e666f000ae6342814bc7aa8a369830e264f366fb607f95019431ce43c。Vue/JS/routes/設定/依存/migration/資産は19d比変更0。build不要。

追加の旧元作業校正済みマーク/未完了維持、新式元参照つき校正ジョブをカレンダーから完了して通知/元未完了維持の合成検証も成功。通常proofには一般completed通知を重ねず専用proof_completedを使用、進行表通知は従来維持。窓口違い/後発依頼の誤紐付け、直接proof outbox、他社、複数割当、元作業保持も検証。系列再照合signatureにjob_type/coordinator_assignment_idも含める。

最終Integration106件1133断言すべて成功（/tmp/proof-completion-notice-final-tests-20261110.log）。PHP10ファイル構文成功、diff --check成功。修正前失敗の証拠と分けて記録。コード検証完了、本番配備/修正後実通知受信/管理者レビューは未実施。過去162の通知再送をしない。共有Claude作業未編集、新式一般公開未承認。

次の一手: 最新の本番版と作業状態だけを以下の読み取り専用SSHで取得する承認。19dを現在も本番最新と決めつけない。結果を保持した固定元/先/許可差分の配備スクリプトを準備して別途正確なSSH承認を得る。
ssh -T silverlamb759@silverlamb759.sakura.ne.jp 'cd ~/SunBWork && git rev-parse HEAD && git branch --show-current && git status --short --untracked-files=all -- . ":!public/build" && git diff --name-only -- public/build/manifest.json'


## 2026-11-10 最新本番状態照合・固定配備手順準備（未配備）

以前承認済みと同じread-only状態SSHを再提示して実行。利用者の追加yesも受領。exit0、本番は19d6c07da7a7cee072c59daf75cc8fc78ea7a3df/main、追跡変更なし/既知未追跡5件のみ/manifest差分なし。したがって直前の「read承認待ち」は置換。追加yesを未提示の配備コマンドの承認とは扱わない。

専用ブランチへ4ec6f0bb6c734252dc0c0084054cb6e27c8d86f1を保存・push。最終106件1133断言/PHP10構文/493entry524資産保持済み。固定配備スクリプト/tmp/proof-completion-notice-deploy-20261110.sh、SHA256 212a4b68e7bd1deb56b74e4b07612042bce70a3470cf29d15172ad7f0eba29de。sh -n成功。同一コピーstorage/app/private/codex-proof-completion-notice-20261110/。

固定元19d6→固定先4ec6。main/HEAD/追跡変更なし/既知未追跡5件/manifest hashを照合。専用branchをfetchし両ancestor・許可20ファイル・削除なし・資産/JS/routes/config/依存/migration/AGENTS/CLAUDE不変を確認。親Changelog差分は追加1行のみ、PHP10ファイルを配備前lint。ff-only後HEAD/493entry524資産/欠落0/manifest一致・既定mysqlを検証し、専用ProofCompletionNoticeChangelogSeeder --forceだけを実行、最終作業状態を再照合する。他作業の営業分析・出力を保持。migrationなし/業務データの変更・試験完了・過去通知再送なし。

次の一手（配備はまだ未承認・未実行）:
ssh -T silverlamb759@silverlamb759.sakura.ne.jp 'sh -s' < /tmp/proof-completion-notice-deploy-20261110.sh

配備後に新たに完了する校正の依頼者通知1件の実受信だけを確認する。既に成功した依頼送信/窓口受信/レイアウト等を繰り返させない。過去162へは再送しない。一般公開未承認。


## 2026-11-10 校正完了通知修正を限定配備成功・実受信確認待ち

確定: 利用者yesで提示済み専用SSHを承認。同一SHA256 212a4b68e7bd1deb56b74e4b07612042bce70a3470cf29d15172ad7f0eba29deの/tmp/proof-completion-notice-deploy-20261110.shを実行しexit0。固定元19d6/main/追跡変更なし/既知未追跡5件/manifest、許可20ファイル/削除なし/画面資産・JS・routes・設定・依存・migration等不変、親Changelog追加1行・PHP10構文を検証して4ec6f0bb6c734252dc0c0084054cb6e27c8d86f1へff-only。manifest493entry/524参照/欠落0/hash一致、既定mysql検証後に専用ProofCompletionNoticeChangelogSeeder --forceだけ実行しDONE、最終追跡変更なし・既知未追跡5件を保持。他作業の営業更新も保持。DB構造変更/業務データの試験操作/校正送信/過去通知補正はなし。

実装/合成106件1133断言/本番配備完了。利用者による修正後の校正完了通知実受信・重複確認は未確認。次の一手は、次に完了する未完了の校正1件で、校正依頼者の通知画面へ校正完了通知が1件届くことだけを確認してもらう。新規依頼送信/窓口受信/上部操作配置など成功済みの試験を反復しない。既に完了した11516/校正162の再操作による過去通知の補正はしない。一般公開未承認、試用制限維持。

回答形式: 通知が1件届けば『成功』、届かない/複数の場合は対象URLと件数。校正専用通知が対象であり通常自己割当の完了通知を新たに有効化した意味ではない。


## 2026-11-11 実受信1件成功・独立工程完了

利用者「成功」で次に完了した校正1件の依頼者向け完了通知1件を確認。合成106件1133断言/限定配備4ec6f0bb6/実受信の3状態が揃った。過去162の再送なし。一般公開未承認。親工程の権限・会社部署/進行管理/Leaderレビューは別に残す。
