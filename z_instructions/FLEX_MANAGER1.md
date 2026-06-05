# FLEX_MANAGER1.md — 部署別ジョブフィールド柔軟化 進捗管理

## 作業ステータス凡例
- ⬜ 未着手 / 🔄 作業中 / ✅ 完了 / ⏸ 保留

---

## フェーズ進捗テーブル

| Phase | 内容 | ステータス | 完了日 |
|---|---|---|---|
| Phase 1 | DB・モデル | ✅ | 2026-06-04 |
| Phase 2 | workload-setting グループ対応 | ✅ | 2026-06-04 |
| Phase 3 | Admin 設定 UI | ✅ | 2026-06-04 |
| Phase 4 | Leader 設定 UI | ✅ | 2026-06-04 |
| Phase 5 | AssignmentForm.vue 統合（ラベル・表示制御） | ✅ | 2026-06-04 |
| Phase 6 | WorkloadAnalyzer 統合 | ✅ | 2026-06-05 |
| Phase 7 | 汎用アイテムプール（job_field_options）対応 | ✅ | 2026-06-05 |

---

## タスク詳細

### Phase 1: DB・モデル
| # | タスク | 状態 |
|---|---|---|
| 1-1 | `department_field_configs` migration 作成 | ⬜ |
| 1-2 | `stages` に `company_id`・`group` 追加 migration | ⬜ |
| 1-3 | `DepartmentFieldConfig` モデル作成 | ⬜ |
| 1-4 | `Stage` モデル fillable 更新 | ⬜ |
| 1-5 | `Department` モデルに `fieldConfigs()` 追加 | ⬜ |
| 1-6 | ローカルで `php artisan migrate` 実行・確認 | ⬜ |

### Phase 2: workload-setting グループ対応
| # | タスク | 状態 |
|---|---|---|
| 2-1 | `WorkloadSettingController` に stages の `group` バリデーション追加 | ⬜ |
| 2-2 | WorkloadSetting EditUI に stages の group 入力欄追加 | ⬜ |
| 2-3 | DTP用 stages に group 値を初期セット（Seeder or 手動） | ⬜ |

### Phase 3: Admin 設定 UI
| # | タスク | 状態 |
|---|---|---|
| 3-1 | `DepartmentFieldConfigForm.vue` 共通コンポーネント作成 | ⬜ |
| 3-2 | `Admin/DepartmentController` に GET/POST メソッド追加 | ⬜ |
| 3-3 | `Admin/Departments/FieldConfig.vue` ページ作成 | ⬜ |
| 3-4 | `Admin/Departments/Index.vue` にフィールド設定ボタン追加 | ⬜ |
| 3-5 | `routes/web.php` に Admin routes 追加 | ⬜ |
| 3-6 | `npm run build` & 動作確認 | ⬜ |

### Phase 4: Leader 設定 UI
| # | タスク | 状態 |
|---|---|---|
| 4-1 | Leader 用コントローラーメソッド追加 | ⬜ |
| 4-2 | `Leader/DepartmentFieldConfig.vue` ページ作成 | ⬜ |
| 4-3 | Leader ナビに設定リンク追加 | ⬜ |
| 4-4 | `routes/web.php` に Leader routes 追加 | ⬜ |
| 4-5 | `npm run build` & 動作確認 | ⬜ |

### Phase 5: AssignmentForm.vue 統合
| # | タスク | 状態 |
|---|---|---|
| 5-1 | `CompositeJobAssignmentController` で field configs を渡す | ⬜ |
| 5-2 | `AssignmentForm.vue` にスロット config props 追加 | ⬜ |
| 5-3 | スロットのラベル動的表示実装 | ⬜ |
| 5-4 | 無効スロットの非表示実装 | ⬜ |
| 5-5 | ドロップダウン選択肢フィルタリング実装 | ⬜ |
| 5-6 | `department_id` 変更時のリアクティブ対応 | ⬜ |
| 5-7 | `npm run build` & DTP部署で既存動作の回帰確認 | ⬜ |

### Phase 6: WorkloadAnalyzer 統合
| # | タスク | 状態 |
|---|---|---|
| 6-1 | WorkloadAnalyzer にカスタムラベル取得ロジック追加 | ⬜ |
| 6-2 | Index.vue の表示カラム名をカスタムラベルで上書き | ⬜ |
| 6-3 | `npm run build` & 動作確認 | ⬜ |

### Phase 7: 汎用アイテムプール（job_field_options）
| # | タスク | 状態 |
|---|---|---|
| 7-1 | `job_field_options` テーブル migration 作成 | ⬜ |
| 7-2 | `project_job_assignments` に `field_*_val` 追加 migration | ⬜ |
| 7-3 | `department_field_configs` に `source` / `source_group` 追加 migration | ⬜ |
| 7-4 | `JobFieldOption` モデル作成 | ⬜ |
| 7-5 | `ProjectJobAssignment` モデル fillable/casts 更新 | ⬜ |
| 7-6 | `WorkloadSettingController` に `job_field_options` タイプ追加 | ⬜ |
| 7-7 | `HandleInertiaRequests` に `jobFieldOptions` 共有データ追加 | ⬜ |
| 7-8 | `DepartmentFieldConfigForm.vue` に source/source_group 選択 UI 追加 | ⬜ |
| 7-9 | `AssignmentForm.vue` の source 対応（表示・保存先切り替え） | ⬜ |
| 7-10 | `npm run build` & 動作確認 | ⬜ |

---

## 作業ログ

| 日付 | 内容 | 担当 |
|---|---|---|
| 2026-06-04 | 設計完了・PLAN/MANAGER/PROMPT 作成 | Claude |
| 2026-06-04 | Phase 1〜5 + build 完了 | Claude |
| 2026-06-05 | Phase 7（汎用アイテムプール）設計・PLAN/MANAGER 追記 | Claude |
| 2026-06-05 | Phase 7 実装完了・動作確認OK | Claude |
| 2026-06-05 | Phase 6 実装完了（WorkloadAnalyzer カスタムラベル対応）| Claude |

---

## 判断・変更事項ログ

| 日付 | 内容 |
|---|---|
| 2026-06-04 | スロット4固定、ドロップダウン型のみ、ラベル部署別変更可、設定者 Leader＋Admin に決定 |
| 2026-06-04 | 数量スロットはオン/オフのみ（数値入力は変更しない）に決定 |
| 2026-06-04 | `allowed_item_ids` は JSON 型で保持（正規化テーブルより運用が簡潔なため）|

---

## 完了後の作業チェックリスト

- [ ] ChangelogSeeder にバージョン追記
- [ ] `CONSOLIDATED_09_domain_rules.md` に部署別フィールド設定ルールを追記
- [ ] `CONSOLIDATED_05_calendar_and_jobbox.md` の AssignmentForm 説明を更新
- [ ] FLEX_PLAN1 / FLEX_MANAGER1 / FLEX1_PROMPT を `z_instructions/archived/` に移動
