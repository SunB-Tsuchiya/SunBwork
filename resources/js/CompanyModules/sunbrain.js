export default {
  companyType: 'sunbrain',
  extraRoles: [
    {
      role: 'proof_coordinator',
      label: 'Proof Admin',
      group: 'beforeUser',
      routeName: 'proof_coordinator.dashboard',
      routePrefix: 'proof_coordinator.',
      activeColor: 'bg-pink-600 text-white font-semibold',
      textColor: 'text-pink-600 hover:text-pink-800',
      // SuperAdmin / Admin / proof_coordinator ロールのみ
      visibilityCheck: (auth) =>
        ['superadmin', 'admin', 'proof_coordinator'].includes(auth.user.user_role),
    },
    {
      role: 'prepress',
      label: 'Prepress',
      group: 'afterUser',
      routeName: 'prepress.dashboard',
      routePrefix: 'prepress.',
      activeColor: 'bg-green-700 text-white font-semibold',
      textColor: 'text-green-700 hover:text-green-900',
      // 製版部署のユーザー、または SuperAdmin / Admin
      visibilityCheck: (auth) =>
        ['superadmin', 'admin'].includes(auth.user.user_role) ||
        auth.user.isPrepressDepartment,
    },
  ],
}
