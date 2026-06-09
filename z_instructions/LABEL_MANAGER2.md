# LABEL_MANAGER2.md — 宛先ラベルPDF生成ツール Phase 2 進捗管理

作成日: 2026-06-07

---

## 進捗一覧

| # | タスク | 状態 | 担当 | メモ |
|---|---|---|---|---|
| 1 | PLAN2/MANAGER2/PROMPT2 作成 | ✅ 完了 | Claude | 2026-06-07 |
| 2 | ユーザー設計確認 | ⏳ 待ち | ユーザー | PLAN2 確認後 OK なら着手 |
| 3 | Migration 4本作成 | 🔲 未着手 | Claude | — |
| 4 | Model 4本作成 | 🔲 未着手 | Claude | — |
| 5 | LabelMasterController 作成 | 🔲 未着手 | Claude | — |
| 6 | LabelMasterSeeder 作成 | 🔲 未着手 | Claude | school_master_draft.csv + txt ファイルを使用 |
| 7 | routes/web.php ルート追加 | 🔲 未着手 | Claude | — |
| 8 | migrate + seed 実行（ローカル） | 🔲 未着手 | Claude | — |
| 9 | LabelGenerator.vue DB連携改修 | 🔲 未着手 | Claude | ハードコード廃止・axios取得 |
| 10 | LabelGenerator.vue マスタ管理UI追加 | 🔲 未着手 | Claude | — |
| 11 | npm run build | 🔲 未着手 | Claude | — |
| 12 | 動作テスト（実ファイルで検証） | 🔲 未着手 | ユーザー | — |
| 13 | 本番デプロイ（migrate + seed） | 🔲 未着手 | Claude | さくら SSH |
| 14 | 担当者による教室マスタ確認・編集 | 🔲 未着手 | 担当者 | AS コード重複を含め要確認 |

---

## ⚠️ 確認事項

### AS コード（渋谷・表参道）
- DB には `AS_1`（渋谷校）`AS_2`（表参道校）として登録
- Excel パース時の `${code}_${rowIndex}` ロジックとどう対応させるか要検討
- **→ 担当者との話し合い後に最終コードを決定**

### マスタ編集権限
- 現状案: 全ログインユーザーが編集可能
- 必要であれば Admin/Coordinator のみに制限可能
- **→ 要確認**

---

## 作業ログ

| 日付 | 内容 |
|---|---|
| 2026-06-07 | FileMaker スクリーンショット・テスト名/科目/内容 txt を解析。DB化方針を策定。 |
| 2026-06-07 | s1_*.xls 128ファイルから教室コード→名前を全抽出（170件）。school_master_draft.csv 作成。 |
| 2026-06-07 | LABEL_PLAN2.md / LABEL_MANAGER2.md / LABEL2_PROMPT.md 作成。ユーザー確認待ち。 |
| 2026-06-07 | Phase 2 全実装完了。Migration 4本・Model 4本・LabelMasterController・LabelMasterSeeder・routes/web.php 更新。migrate + seed 実行（175校・52テスト名・26科目・35内容）。LabelGenerator.vue にDB連携（onMounted・axios）・マスタ管理タブ（CRUD）・テスト名datalist追加。ビルド完了。 |
| 2026-06-07 | Phase 2.5 実装完了。LabelGenerator.vue に以下を追加しビルド完了: ①Excel凡例列（col 13/15/9）からアイテム自動検出（extractLegendItems）・detectedItems state・activeItems computed（PRESETフォールバック付き）; ②学年ラベルオーバーライドUI（gradeLabelOverrides・マイファースト対応）; ③アイテム編集UI（showItemEditor・追加/削除/編集）; ④テスト名抽出バグ修正（月DD・DD日の日付範囲形式対応）; ⑤学年テキスト自動縮小（drawLabel grade auto-shrink）; ⑥shortName略称の日付範囲パターン対応。 |

---

## 判断ログ（Claude の推論・設計根拠）

### 「LocalStorage ではなく DB にする」判断
- ユーザー指摘: 複数人作業のため LocalStorage は不可
- LocalStorage はデバイス固有でありチーム共有不可
- **→ Laravel DB + API が正解**

### エリア分類
- FileMaker の「エリア別部数集計」に合わせて area カラムを設計
- 関東（ルートA1〜I2）/ 東海 / 関西 / 中国 / 四国 / 九州・沖縄 / 北海道
- FileMaker では「本部」と表記されていたが、これは「関東」エリアの意味と判断

### AS コード重複の対処
- 現行 Phase 1 では `${code}_${rowIdx}` で一時的に別キーを生成
- DB では `AS_1` / `AS_2` と明示的に別コードにして、Excel パース側でも同様に対応予定
- 根本的な解決は担当者がコードを確定するまで保留
