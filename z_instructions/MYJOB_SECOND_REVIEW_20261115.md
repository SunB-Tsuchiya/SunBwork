# 新マイジョブ 再レビュー（2026-11-15）

対象71cc6fa0e。前回修正の新規保存/通常編集と2画面競合拒否は利用者「成功」で実画面確認済み。今回アプリ変更・配備・本番データ操作なし。

## 追加指摘 P2

DepartmentWorkController::mine/update/destroy はpersonalAvailableとuser_idのみを検証し、entryのcompany_id/department_idを現在所属に制限していない。WorkJobReader::resolveは同じ本人でも会社/部署が違うentryを拒否する。したがって異動後など、本人IDは同じだが保存時の会社/部署が現在と違う行が残る場合、詳細やBOXでは参照不可なのに、カレンダー取得では件名/数量/結果/edit_versionを返し、直接API経由では編集/削除も通る。別ユーザーの記録にアクセスできる問題ではない。

根拠: app/Http/Controllers/Schedule/DepartmentWorkController.php のmine(user_id絞り)、update(所有者のみのチェック)、destroy(所有者のみのチェック)。比較対象 app/Services/WorkJobReader.php:21-27。

修正案: 本人記録の会社/部署/会社コンテキスト判定を詳細側と共有し、カレンダー取得のqueryにも同一scopeを適用する。更新/削除ではロック後の行で再検証。異動前履歴を残す制度を選ぶなら、詳細/BOXも含めた明示的な別設計とし、今回の現行権限と混ぜない。

## 検証

tests/Integration/MyJobSecondReviewTest.php のtest_second_review_で、他会社/部署の同一所有者行をカレンダーが返し、詳細は拒否、カレンダーAPIの更新/削除が成功することを再現。前回修正の5ケースと合わせ6件29断言成功。この成功は追加指摘の不具合再現を含む。異動の実業務データや別会社の本番記録は参照していない。

前回3件の修正（古い編集拒否、旧NULL案件の編集、新規再送防止）はコードと合成テストで再確認し、この範囲の退行は発見していない。SQLiteでの検証であり、本番MySQLの同時トランザクション負荷を実測したものではない。

次: 追加指摘のscope統一修正を利用者と相談。配備と画面確認は別途。
