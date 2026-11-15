# 新マイジョブ レビュー指摘修正 設計1

2026-11-15。基準2602ee504。レビューMYJOB_REVIEW_20261115.md。利用者は1→2→3の順に修正を承認。共有mainと他作業は変更しない。

## 1 通常編集の競合防止

PersonalWorkEntryの通常入力項目から安定した照合値を生成し、詳細/カレンダーAPIへedit_versionとして渡す。通常更新はtransaction内で行ロックして最新照合値と必須edit_versionを比較。不一致は409で入力を残し、再読込を案内。比較対象は通常入力項目（日時・件名・分類・公開範囲・案件・数量・結果）とし、完了や詳細ファイルの独立更新とは干渉させない。日時移動も同じ照合を必須とする。既存行のバージョン列追加や一括更新は不要。

## 2 旧会社NULL案件の引用後編集

認可済み既存entryのproject_job_idをvalidatedEntryのsourceProjectIdとして渡す。同じ案件保持だけ旧NULL会社例外を使い、別案件の新規選択/他社案件/他人の作業は従来の拒否を維持。

## 3 新規保存の再送重複防止

新規作成のrequest_token（UUID）をフォームを開いた時に発行し、エラー後再試行でも保持。本人ユーザー行をtransactionでロックし、同一user+tokenの保存受付記録を確認。通常/独立引用のvalidated payloadと引用元のhashが一致する再送は既存IDを返す。不一致は422、削除済みは410。同名同時刻でも別tokenは別仕事として登録。専用work_job_creation_receiptsテーブルをmigrationで追加しuser+token keyをuniqueにする。既存の続き作成receiptとは混ぜない。

## 対象と検証

Controller/Service/モデル/新migration、QuickWorkEntryModal、UserCalendar、関連API投影、合成Integrationと実Vueテスト。既存3再現を修正後条件に変え、古い画面409/同一最新保存/旧NULL案件編集/別会社拒否/再送同一ID/内容違い拒否/別token別作成/削除済再送を検証。既存の続き/完了/詳細/通知テストと本番用buildを通す。

DB操作は新テーブル追加だけ。本番migrationは固定差分配備の承認後に--forceで実行。配備前に本番版・他作業照合、失敗なら停止。実装/コード検証/配備/利用者画面確認は別状態。成功済み試用全体を再試験しない。
