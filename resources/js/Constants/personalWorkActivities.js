export const workCategories = [
    ['shinko', '進行管理'], ['eigyo', '営業'], ['dtp', '組版・DTP・制作'],
    ['kousei', '校正'], ['jimu', '事務'], ['other', 'その他'],
];

const commonActivities = [
    ['intake_allocation', '受付・仕事の配分・担当調整'],
    ['preparation_specs', '準備・仕様整理'],
    ['internal_improvement', '社内改善・システム開発'],
];

export const analysisActivityLabels = {
    scheduled_other: 'そのほか（業務）',
    my_job: 'マイジョブ・依頼ジョブ', outing: '業務外出・訪問', unspecified: '作業種類未指定',
};

export const workActivities = {
    dtp: [['new_creation', '新規作成'], ['revision', '修正'], ['data_preparation', 'データ整備'], ['delivery_check', '納品チェック'], ...commonActivities],
    kousei: [['proof_first', '初校校正'], ['proof_revision_heavy', '修正校正（重）'], ['proof_revision_light', '修正校正（軽）'], ['manuscript_check', '原稿チェック'], ...commonActivities],
    shinko: [['progress_coordination', '進行調整'], ['manuscript_check', '原稿チェック'], ['delivery_check', '入稿・納品チェック'], ['meeting', '連絡・打合せ'], ['office_work', '事務作業'], ...commonActivities],
    jimu: [['voucher_check', '伝票チェック'], ['document_creation', '書類作成'], ['document_processing', '書類処理'], ['orders_billing', '受発注・請求対応'], ['office_work', 'その他事務'], ...commonActivities],
    eigyo: [['sales_visit', '訪問・商談'], ['sales_proposal', '見積・提案'], ['sales_order', '受注・納期調整'], ['customer_contact', '顧客連絡'], ...commonActivities],
    other: [['other', 'その他'], ...commonActivities],
};

export const activityLabel = (code, category) =>
    analysisActivityLabels[code]
    ||
    workActivities[category]?.find(([key]) => key === code)?.[1]
    || Object.values(workActivities).flat().find(([key]) => key === code)?.[1]
    || code;
