// The shared server flag supplies the INFO trial gate; keep the route's company-context restriction too.
export function workHubMyJobRoute(props = {}) {
    const auth = props?.auth || {};
    const companyId = Number(auth.user?.company_id);
    const context = auth.superAdminContextId;
    const trial = Boolean(props?.userWorkHub?.enabled)
        && auth.user?.user_role === 'superadmin'
        && typeof auth.companyType === 'string' && auth.companyType !== '' && auth.companyType !== 'general'
        && Number.isInteger(companyId) && companyId > 0
        && (context == null || Number(context) === companyId);
    return trial ? 'user.myjobbox.unified_preview' : 'user.myjobbox.index';
}
