# 大まかな案件: 情報出版 Super Admin 試用計画

更新: 2026-10-13。利用者の現時点の指示と、名前必須・取引先と年度任意の回答に基づく。

## 目的と境界

細かな ProjectJob を起こす前にも、実際の仕事を安定した大まかな案件名で記録できるようにする。既存の「大まかな案件」区分は存在しない。既存 ProjectJob には大きくなり得る案件があるだけで、これを登録済みの大枠として扱わない。

今回: 情報出版の試用権限を持つ Super Admin が専用メニューで大まかな案件を登録・編集・休止でき、本人の簡易予定から選べる。名前は必須、取引先と年度は任意。取引先が未定でも登録できる。簡易予定は大まかな案件、従来の ProjectJob、仮名のいずれか一つを選ぶ。部署内表示と明示的 private の現行規則を維持し、試用分析でも従来どおり時刻を集計する。

後工程: (1) 既存 ProjectJob から大まかな案件を明示的に紐づける、(2) ProjectJob 登録時に大まかな案件を選ぶ、(3) Coordinator に簡易入力を公開するときに Coordinator メニューへ追加する。利用者はこれらを「実際にみなに触ってもらうとき」に実装するよう指定した。現段階で自動紐づけ・データ移行は行わない。

## 設計

- `broad_projects`: `company_id`, `department_id`, `name`, nullable `client_id`, nullable `fiscal_year`, `is_active`, timestamps。取引先は `company_clients` による利用会社所属を検証する。休止で履歴を保持し、削除 UI は作らない。
- `personal_work_entries.broad_project_id`: nullable FK。ProjectJob ID と仮名との同時指定を拒否する。休止済みを新規選択には出さず、既存予定の編集では選択済み項目を返す。
- サーバは Super Admin と INFO 部署の試用権限を確認し、会社・部署をまたぐ取得・更新を拒否する。案件名は同部署へ表示し、簡易予定の `private` は維持する。
- 関連ファイル: migration、`BroadProject` モデル、SuperAdmin コントローラと Inertia ページ、ルートとタブ、`DepartmentWorkController`、`QuickWorkEntryModal`、`PersonalWorkSchedule`、試用分析、変更履歴、統合資料。

## 段階と確認

1. 管理台帳・引継ぎ資料の誤った「既存大枠がある」を訂正し、保留項目を固定する。
2. DB とサーバの権限・入力検証、Super Admin の登録 UI を実装する。
3. 簡易予定の選択・表示・編集と分析参照を実装する。
4. Vue ビルド、PHP 構文・対象テスト、既存差分を確認する。さくら反映は `AGENTS.md` に従い SSH の正確なコマンドを示して確認を得る。反映後、利用者の画面確認を別記する。

利用者の「Super Admin メニューに入れる」「名前必須・取引先と年度任意」という今回の回答を、Large Work Protocol の実装確認として記録する。後工程は今回の実装承認に含めない。
