# 情報出版 部署の作業 表示対象管理 設計1

2026-11-16。基準c5e6d7d97（直前本番9c187136c、画面確認済みのモーダル修正を含む）。専用branch feature/info-department-visibility-20261116。共有mainのユーザー/Claude作業は保護する。

## 確定要望と提案

確定: サン・ブレーン情報出版だけを対象に、Adminから部署の作業予定/みんなの作業記録の一覧に載せる人を選ぶ。利用者の回答は「その人を一覧に載せる／載せない」。ページを閲覧できる権限の切替とは区別する。参考はadmin/applicants/transportのユーザー別スイッチ・まとめ保存。

提案: Adminの権限管理に「部署の作業の表示対象」を追加し、情報出版の対象者を氏名/雇用区分等で一覧。各人に「作業予定に表示」「作業記録に表示」の独立2スイッチを設け、まとめて保存。初期は現在どおり全員表示（未設定=true）。初回に実際の記録や人物の状態を推測してOFFへしない。

## 範囲

- Adminは実所属会社、SuperAdminは選択中会社。サン・ブレーン会社かつ部署code=INFOの対象者だけをGET/更新とも許可。候補は参考画面と同じくghost/ghost_owner_id付き仮ユーザーを除く。対象者一覧の並び/スイッチ/保存状態は既存申請者管理のパターンを使う。
- サンエーにはメニューを追加せず、URL直指定でも拒否。他部署・別会社IDを更新に混ぜたリクエストは全体を422で拒否し、部分更新しない。一般User/Leader/Coordinatorには管理操作を開かない。
- 予定一覧GETと記録GETのサーバクエリに表示設定を適用。画面だけで隠さず、予定GETのuser_id指定でも非表示者は取得できない。通常共有予定表/個人カレンダー/マイジョブ保存先は変更しない。
- みんなの作業記録の一覧と、本人だけのmine（時間/未分類/空き案内）を分離。自分を一覧から除いても本人の数値が0にならないよう本人計算は維持。管理分析の対象/点数/順位/勤怠/日報権限は変更しない。
- 設定未作成の行は表示。過去日にも現在の表示設定を適用。表示OFFはデータ削除/本人入力禁止/閲覧禁止を意味しない。

## DB/保存

新テーブルdepartment_work_display_settings: id、user_id、company_id、department_id、show_schedule(boolean default true)、show_records(boolean default true)、changed_by(nullable actor)、timestamps。user+company+departmentをunique。設定は現在所属に限定して適用し、異動前の会社部署の設定を引き継がない。更新は対象検証後のtransaction内で実行し、変化しない行の監査日時/変更者は触らない。古い管理画面の保存で他の管理者の変更を黙って戻さないよう、取得時の設定版を照合して競合は409を返す。返却は対象者/設定/版だけ。

## 対象候補

migration/Model/設定Service、Admin Controllerと新Vue、routes/Ziggy、AdminSunbrainNavigationTabs、DepartmentWorkController::index、DepartmentWorkRecords、関連テスト・統合資料。新UIはCONSOLIDATED_01_layout_and_uiを読み、AppLayout/Admin赤色/既存表とスイッチを踏襲。不要な二重レイアウトを足さない。

## 検証/配備

合成データでAdmin/SuperAdmin/会社未選択/サンエー/他部署/他会社/他ロール、独立2フラグ、未設定表示、取得user_id迂回拒否、本人mine保持、管理分析不変、異動で旧設定不適用、競合/全体拒否/まとめ保存と変更なし監査保持を検証。Vueは保存失敗の入力保持/二重送信防止、ビルド/ルート生成/manifest参照を確認。
配備は本番版/他作業/固定差分を確認し、専用migrationだけ--path/--forceで実行。既存の未適用migrationをまとめて実行しない。SSH前は正確なコマンドと利用者確認が必要。実装/配備/コード検証/画面確認を分離し、成功済みの一般入力や日報注意の試験を反復しない。

次: この具体設計の確認後に実装。現段階でアプリコード・DB・本番表示は変更していない。
