# IRUKA1_PROMPT.md — 新セッション開始用プロンプト

---

## 貼り付け用プロンプト

```
イルカ（在席管理）機能の実装を続けます。
設計ドキュメントは z_instructions/IRUKA_PLAN1.md、進捗は z_instructions/IRUKA_MANAGER1.md を参照してください。

## 機能概要
社内メンバーの在席ステータスをリアルタイム（30秒ポーリング）で確認・変更できる機能です。
参考サイト: https://iruca.co/rooms/sample

## 主な仕様
- 全ロール・全ユーザーが全員のステータスを閲覧・変更可能
- 部署フィルターボタンで絞り込み（情報出版/製版/オンデマンド/全部署）
- ヘッダー（全ページ共通）: 🐬アイコン + 自分のステータス → クリックでモーダル
- モーダル: 自分→名前・ひとこと・ステータス変更可 / 他人→ステータスのみ変更可
- ダッシュボード: Userはカレンダー+イルカタブ切替 / 他ロールはイルカボードに置換
- 退社ステータス設定時に当日の日報未作成なら Diary + WorkRecord を自動作成

## DB
新規テーブル: user_presence_statuses
  - user_id (UNIQUE, 1ユーザー1行)
  - status (varchar50, default 'present')
  - comment (varchar200, nullable)
  - updated_by_id (nullable FK)
  - status_source ('manual' or 'calendar')

## ステータス一覧（16種）
present, present_kodai, left, telework, paid_leave, half_am, half_pm,
out, out_nr, moving, late, early_leave, away, meeting, train_delay, special_leave

## 退社自動日報ロジック
- user_monthly_schedules の当日JSON → worktype_id 取得
- worktypes テーブルから start_time 取得
- Diary(content='') + WorkRecord(start=定時, end=現在時刻) を updateOrCreate
- 既存Diaryがある場合は何もしない

## 現在の進捗
→ z_instructions/IRUKA_MANAGER1.md の進捗表を確認してください

## 今回お願いする作業
（↓ここに実装してほしいPhaseを記入してから貼り付けてください）
Phase X: ○○の実装をお願いします。
```

---

## 設計サマリー（参照用）

### 関連ファイル（既存）
- `app/Http/Controllers/DiaryController.php` - upsertWorkRecord メソッドがある（退社自動日報の参考）
- `resources/js/layouts/AppLayout.vue` - ヘッダー追加場所
- `resources/js/Pages/Dashboard.vue` - User ダッシュボード（カレンダーあり、タブ追加対象）
- `resources/js/Pages/Admin/Dashboard.vue` - Admin ダッシュボード（置換対象）
- `resources/js/Pages/Coordinator/Dashboard.vue` - 置換対象
- `resources/js/Pages/Leader/Dashboard.vue` - 置換対象
- `resources/js/Pages/Clerk/Dashboard.vue` - 置換対象
- `resources/js/Pages/SuperAdmin/Dashboard.vue` - 置換対象
- `resources/js/Pages/Prepress/Dashboard.vue` - 置換対象

### worktypes テーブル（参考）
| id | name | start_time | end_time |
|---|---|---|---|
| 1 | A日程 | 09:00 | 17:30 |
| 2 | B日程 | 08:00 | 16:30 |
| 3 | C日程 | 10:00 | 18:30 |
| 4 | 夜勤 | 18:00 | 05:30 |

### departments テーブル（参考）
| id | name | code |
|---|---|---|
| 1 | 情報出版 | INFO |
| 2 | 製版 | SEIHAN |
| 3 | オンデマンド | ONDEMAND |

### user_monthly_schedules.schedule の解釈
JSON例: `{"15": 1, "16": 2}` → 15日=worktype_id 1(A日程)、16日=worktype_id 2(B日程)
