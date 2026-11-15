# 新しいマイジョブのレビュー（2026-11-15）

対象: feature/info-department-myjob-plan-20261114、2602ee504。本番へ反映した同版のソースを静的レビュー。一般User公開後の新入力、統合BOX、編集、続き、完了、旧形式接続を確認。成功済み実画面の再試験や本番DB操作・配備はしていない。

## 指摘

1. P1: 通常編集とカレンダードラッグ保存に競合検知がない。A画面で件名/分類を更新後、更新前に開いたBカレンダーで時間をドラッグすると、Bが保持している古い件名/分類/数量等を全送信して上書きする。DepartmentWorkController::update(380-388)、UserCalendar(291-295)。詳細ファイルにはdetails_version検査があるが通常項目にはない。対策: 通常項目の版番号による409拒否、日時移動専用の更新項目制限。
2. P2: 旧company_id=NULL案件から引用した新形式ジョブは作成できるが通常編集で403。UnifiedMyJobBoxControllerは引用元project IDをvalidatedEntryへ渡し会社NULL例外を適用する一方、updateは既存entryだけ渡しsourceProjectIdを渡さない。DepartmentWorkController(385,447-450)。対策: 所有者認可した既存ジョブの同じ案件を保持する場合に限り旧会社NULL参照を許可。新たな別案件選択の権限は広げない。
3. P2: 通常新規保存にリトライ識別がない。サーバ保存成功後に応答だけ失われ、ユーザーが再度保存すると同一作業が2行になる。QuickWorkEntryModal(通常create分岐)・DepartmentWorkController::store(367-377)・UnifiedMyJobBoxController引用create。続き作成にはrequest_token/receiptがある。対策: 新規作成にもトークンを付け、同一ユーザー/同一内容の再試行は同じIDを返す。件名・日時一致による自動結合は使わない。

## 再現証拠

tests/Integration/MyJobReviewProbeTest.php のtest_review_3件をネットワークなしDocker、DB_CONNECTION=sqlite、DB_DATABASE=:memory:で実行。3件9断言成功は、上記の不具合が実際に起きることを確認した結果。修正済みを意味しない。既存Integrationの合成スキーマとcontroller/serviceを使い、重複2行・引用後403・古い件名への巻戻りを確認。

続きの作成/完了は祖先ロック・所有者/会社部署認可・request_tokenを持ち、単純なID一致の結合を避けていることを確認。今回確認した範囲で追加の確定指摘なし。全機能・全競合の無欠陥保証ではない。

次の推奨順: 1の上書き防止、2の旧案件編集、3の再送重複防止。今回アプリ修正/追加配備なし。
