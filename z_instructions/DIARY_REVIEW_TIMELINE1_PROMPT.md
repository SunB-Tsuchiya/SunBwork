# 再開: 日報閲覧 新マイジョブ反映
AGENTS→JOB_EVALUATION_CONTROL1→DIARY_REVIEW_TIMELINE_PLAN1/MANAGER1→DIARY_REVIEW_TIMELINE_AUDIT_20261127を読む。
利用者は修正開始を承認。具体設計は確認待ち。認可済みの日報showから対象者・対象日の新形式配列をpropsで返す案。mineをtrueにするだけでは閲覧者自身が混ざるため禁止。本文/実績/評価/完了/通知は不変。会社/部署/日報閲覧許可と非公開・機密の既存伏せ字を保護。
現在/tmp/sunbwork-diary-view-audit-20261127は資料用sparse。前回保存aac8f2b6d、本番3630c79ab、origin/main b1d57e1dd/主作業985e6fffbは異なるため本番と混同しない。Claude/ziggy/売上の主作業を保護。具体設計承認後に必要ソースを隔離展開して実装/合成・実Vue検証、配備前に正確SSH承認と固定差分を用意する。
実装/コード検証/配備/今回利用者確認は未実施。次は具体設計確認。既確認の工程確定や校正は再確認しない。TODO繰り返し4条件は保留。
