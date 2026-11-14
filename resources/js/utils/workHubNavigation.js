// The shared server flag supplies the INFO personal-work gate; keep the company-context restriction too.
export function workHubMyJobRoute(props = {}) {
    const auth = props?.auth || {};
    const companyId = Number(auth.user?.company_id);
    const context = auth.superAdminContextId;
    const enabled = Boolean(props?.userWorkHub?.enabled)
        && typeof auth.companyType === 'string' && auth.companyType !== '' && auth.companyType !== 'general'
        && Number.isInteger(companyId) && companyId > 0
        && (context == null || Number(context) === companyId);
    return enabled ? 'user.myjobbox.unified_preview' : 'user.myjobbox.index';
}
