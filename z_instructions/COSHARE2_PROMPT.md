# COSHARE2_PROMPT — 新セッション開始用プロンプト

## 作業内容

クライアント管理の共有UI拡張（COSHARE第2弾）。設計済み・実装中。

1. **同一 client_code 共有確認**: Create.vue で他社既存コードを検出して「共有しますか？」モーダル
2. **編集モード**: Index.vue にロール別トグルUI
   - SuperAdmin → 会社間共有トグル（company_clients）
   - Admin/Leader/Coordinator → 自社部署間トグル（client_departments）

## 参照ファイル

- 設計: `z_instructions/COSHARE_PLAN2.md`
- 進捗: `z_instructions/COSHARE_MANAGER2.md`
- 前回: `z_instructions/COSHARE_PLAN1.md`
