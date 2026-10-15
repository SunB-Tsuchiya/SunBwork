# COLAYOUT1_PROMPT.md — 新セッション開始用プロンプト

以下を新しいセッションの最初にそのまま貼り付けて使用すること。

---

```
会社別レイアウト分離プロジェクト（COLAYOUT）を継続します。
まず z_instructions/COLAYOUT_PLAN1.md と COLAYOUT_MANAGER1.md を読んでください。
（背景は z_instructions/COTYPE_PLAN1.md、CLAUDE.md の作業ルールも守ること）

## 目的
サンエー印刷（companies.company_type = 'general'）にも社内ポータルを使わせるため、
ロールボタン・タブメニューを会社タイプ別に出し分ける。デザイン・ルール・共通機能は共通。

## 絶対原則
サン・ブレーン（company_type = 'sunbrain'）は毎日使われている本番。見た目・挙動を一切変えない。
→ 変更は必ず general のときだけ効くガード方式。既存のサン・ブレーン用マークアップは書き換えない。
→ URL ガードは company_type:sunbrain ではなく deny_general（general だけ拒否）を使う。

## 確定仕様（要約）
- ロールは既存流用。総務/経理は clerk、経理は部署名に「経理」を含むか（auth.user.isAccountingClerk）で判定
- general では Leader / Coordinator / Proof Admin / Prepress を非表示
- general のロール行: 管理者 / 総務 or 経理 / ユーザー（SuperAdmin は + SuperAdmin）
- タブは許可リスト方式（PLAN 3.2）。売上分析は general では出さない
- 実装済みファイルは PLAN 5 の一覧を参照

## 現状
Phase 2・3（実装）完了、npm run build 済み。Phase 4（ブラウザ確認）はユーザーが実施。
Phase 5（デプロイ）は未着手＝ユーザー指示があるまで実施しない。

## 後回し事項
MANAGER の「後回し事項（D-1〜D-9）」に記録済み。特に D-1（URL 直打ち対策）は共用ルートの洗い出しが前提。
ユーザーから「後回し分をやって」と言われたら、該当 ID の影響範囲を先に提示して確認を取ってから実装する。
新たに後回しにした事項は MANAGER の表に D-10 以降として必ず追記すること。

## 注意
- ローカル DB は整備済み: company 2 = sunbrain / company 3 = サンエー印刷(general) とダミー admin・総務・経理（パスワード password。MANAGER の作業ログ参照）
- 本番デプロイ前に本番の companies.company_type を確認（SSH はユーザー確認後）
- さくらデプロイは DEPLOY_SAKURA.md と CLAUDE.md の VITE_APP_BASE_PATH 手順を厳守
- Docker: docker compose up -d / exec は --user sail
- ブラウザ操作の動作確認はユーザーが行う
```
