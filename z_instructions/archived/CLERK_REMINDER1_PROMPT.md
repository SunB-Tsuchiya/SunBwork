# Clerkカレンダー・リマインダー 引き継ぎプロンプト

`AGENTS.md`、`CLAUDE.md`、`z_instructions/CLERK_REMINDER_PLAN1.md`、`z_instructions/CLERK_REMINDER_MANAGER1.md`、`z_instructions/CONSOLIDATED_01_layout_and_ui.md`、`z_instructions/CONSOLIDATED_05_calendar_and_jobbox.md`を読む。

目的は、交通費精算などの期限案内を通知一覧ではなく、全ユーザーが日々見る予定表へ表示すること。Clerkが会社単位で内容と表示開始日・終了日を管理する。

設計の要点:

- 表示先は一般ユーザーの`/calendar`だけ。Clerkの`/clerk/calendar`には表示しない。
- 位置はナビゲーション下、カレンダー本体の直前。「事務からのお知らせ：内容」を一段の帯で中央太字表示し、期間は表示しない。
- 色は設定画面で11色から選び、濃色を枠線、薄色を背景にする。
- 有効かつ`starts_on <= JSTの今日 <= ends_on`だけを表示。期間の両端を含む。閉じる・既読機能は設けない。
- Clerk上部メニューに「リマインダー設定」を追加し、一覧・新規作成・編集・有効切替・削除を実装する。
- DBは`clerk_calendar_reminders`。company_id、created_by、content、starts_on、ends_on、is_active、timestamps。
- 通知、events、clerk_eventsへレコードを生成しない。未読ランプや予定件数へ影響させない。
- 一般ユーザーの`/calendar`ではログインユーザーの所属会社で絞る。
- 他社IDは404。SuperAdminの会社解決は既存`ResolvesContextCompany`と予定日設定を踏襲する。
- UIはAppLayout、header内の戻るリンク、白カード、Clerk紫。独自main、重複幅ラッパー、ToastUnifiedを追加しない。

既存のClerk連続カレンダー、祝日、予定複製、予定日設定、お知らせ下書き修正、売上分析などの未コミット差分を保護する。`CLAUDE.md`は参照のみで編集しない。

テストは`Tests\TestCase`や`RefreshDatabase`を使わず、専用インメモリSQLiteで日付境界、会社分離、停止・再開を確認する。Vue変更後はbuildを行う。ローカルmigration前にDB接続先を確認し、追加migrationだけを適用する。実装完了後はChangelogSeederとCONSOLIDATED_01/05を更新し、PLAN/MANAGER/PROMPTを`z_instructions/archived/`へ移す。

ユーザー承認後にCodexが実装・自動検証・ローカルmigrationまで完了。再実装せず、MANAGERの画面確認項目から続ける。PHPテストは3 tests / 14 assertions、Vue build成功。Laravel接続はmysql、DBはsunbwork。ChangelogSeederとCONSOLIDATED_01/05も更新済み。
