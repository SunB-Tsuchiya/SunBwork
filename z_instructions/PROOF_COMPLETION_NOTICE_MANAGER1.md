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
