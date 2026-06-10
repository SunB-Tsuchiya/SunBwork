# LABEL_MANAGER4.md — 宛先ラベルPDF生成ツール Phase 4 進捗管理

作成日: 2026-06-10

---

## 進捗一覧

| # | タスク | 状態 | メモ |
|---|---|---|---|
| 1 | PLAN4 / MANAGER4 / PROMPT4 作成 | ✅ 完了 | 2026-06-10 |
| 2 | OcrSpaceService に `recognizeFullPage` 追加 | ✅ 完了 | 2026-06-10 |
| 3 | `LabelItemPdfParser.php` 新規作成 | ✅ 完了 | 2026-06-10 |
| 4 | `LabelOcrController.php` 新規作成 | ✅ 完了 | 2026-06-10 |
| 5 | `routes/web.php` に `/label-ocr/analyze` 追加 | ✅ 完了 | 2026-06-10 |
| 6 | `LabelGenerator.vue`: OCR state / step 追加 | ✅ 完了 | 2026-06-10 |
| 7 | `LabelGenerator.vue`: アイテムPDFドロップゾーン UI | ✅ 完了 | 2026-06-10 |
| 8 | `LabelGenerator.vue`: OCR確認・編集モーダル UI | ✅ 完了 | 2026-06-10 |
| 9 | `LabelGenerator.vue`: シートフィルタ（原本・TS除外） | ✅ 完了 | 2026-06-10 |
| 10 | `LabelGenerator.vue`: `confirmedItems` を activeItems に優先適用 | ✅ 完了 | 2026-06-10 |
| 11 | `npm run build` | ✅ 完了 | エラーなし 13.10s |
| 12 | 実機テスト（アイテム.pdf OCR → Excel読み込み → PDF出力） | 🔲 未着手 | |
| 13 | さくら本番デプロイ | 🔲 未着手 | テスト完了後 |

---

## 作業ログ

| 日付 | 内容 |
|---|---|
| 2026-06-10 | Phase 4 設計確定。アイテム.pdf OCR フローへの根本的な方針変更。PLAN4/MANAGER4/PROMPT4 作成。 |
| 2026-06-10 | Phase 4 全実装完了。バックエンド3ファイル新規作成（LabelOcrController, LabelItemPdfParser, OcrSpaceService::recognizeFullPage）、ルート追加、LabelGenerator.vue 大幅変更（OCRドロップゾーン、確認モーダル、confirmedItems優先、シートフィルタ）。ビルドエラーなし。 |

---

## テストリスト（Phase 4）

- [ ] T4-01: アイテム.pdfをドロップ → OCR実行 → 確認モーダル表示
- [ ] T4-02: テスト名DB候補が正しく表示される（学習力育成テスト等）
- [ ] T4-03: アイテム一覧が正しく検出される（①②③...）
- [ ] T4-04: 「確定」後に Excel（s1_*.xls）を読み込める
- [ ] T4-05: 「原本」「テストサービス」シートが表示から除外される
- [ ] T4-06: PDF出力が正常に機能する（Phase 3 と同等以上）
- [ ] T4-07: 一式フラグが正しく機能する（一式PDF生成）
- [ ] T4-08: OCRが低精度でも手動修正できる
