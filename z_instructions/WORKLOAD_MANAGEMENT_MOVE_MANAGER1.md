# 作業量分析の管理画面移設 進捗管理 1

更新: 2026-10-30 JST。計画 `WORKLOAD_MANAGEMENT_MOVE_PLAN1.md`、再開 `WORKLOAD_MANAGEMENT_MOVE1_PROMPT.md`。

| 工程 | 状態 | 証拠/次の確認 |
| --- | --- | --- |
| 現状調査 | 完了 | 新分析はSuperAdmin/INFO試用、旧Adminは自社・Leaderは所属ユニット。固定scheduleリンク/独立シェル/試用メニューを特定 |
| 計画の実装前確認 | 確認待ち | 利用者は置換作業自体を依頼済み。5ファイル以上/権限分離を伴うため計画3点を準備。既存閲覧範囲の維持と移設方針を確認する |
| コード実装 | 未着手 | 閲覧専用権限を分離。既存availableを一律緩和しない |
| 非破壊検証 | 未実施 | 元V3は既存証拠あり。今回のAdmin/Leader移設が検証済みという意味ではない |
| GitHub保存 | 管理資料のみ | 既存隔離ブランチ `review/evaluation-c7-20261030` で作業中 |
| さくら反映 | **未実施・別途許可が必要** | 最後に確認した既存版はf5a327635。ユーザーが今回も別途デプロイ許可と指定 |
| 利用者画面確認 | 未実施 | 各roleのprefix/色/レイアウト/権限/リンクを配備後に確認 |

**再開する1手:** 計画の実装前確認を得る。その後、共有メインを触らず隔離環境で閲覧スコープと読取APIの実装から始める。一般社員向け公開や月別コメント機能は今回実装しない。

2026-10-30 調査根拠: `WorkloadAnalyzerController::index/legacyIndex`、`DepartmentWorkController`、`PersonalWorkSchedule::available`、ロール別ルートとナビ、`AppLayout`と統合UI文書。ベースは直前専用ブランチb2be88749。共有メインは古い6af669c89で別作業変更があり、触らない。取得したorigin/mainはe94584ee3。既存の本人試用と管理用閲覧権限を分離する必要あり。
