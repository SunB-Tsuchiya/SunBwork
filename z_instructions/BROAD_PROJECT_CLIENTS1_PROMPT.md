# 再開: 大まかな案件・複数クライアント

AGENTS.md→JOB_EVALUATION_CONTROL1.md→MYJOB_UNIFIED_BOX_PLAN1/MANAGER1/1_PROMPT→BROAD_PROJECT_CLIENTS_PLAN1/MANAGER1を読む。

2026-11-06利用者判断: 細分類の増殖を避け、カレンダー等のまとまりに複数取引先があってよい。分類名・取引先名で検索。クライアント最上位に再分類する前案を置換。直前の4提案をOKと承認: 複数名検索、A/B/ほかN社表示、実案件の取引先優先・新式で取引先必須にしない、細分類を抑え共通順は管理側。担当者自動設定は不要。

基準543978c12の隔離feature/myjob-client-plan-20261106で3資料を作成。実装前の具体設計確認が次。一般公開/実DB更新は未承認。shared colayout-deployやCLAUDE.mdは編集禁止。現在の本番証拠0c9434f67、追加SSHなし。

実データExcelは/home/w229/SunBwork/storage/app/private/codex-client-review-20261106にありGitへ追加しない。336案件、未判定を含む66取引先区分、クライアント×最深分類候補135行、未分類77件。分類候補は確定リンクではない。会社関連company_clients未調査のためimport前に照合が必要。

既存BroadProject.client_idを壊さず対応表を追加する。親の配下集約と直接編集値を区別する。旧client_idと新client_idsの未指定保持、明示解除、他社/休眠、旧フォーム互換を設計どおり実装する。前回成功済みの続き・完了等の画面チェックを再要求しない。次は設計確認後にコード実装。配備前はDEPLOY_SAKURA.mdと正確なSSHコマンド承認を守る。
