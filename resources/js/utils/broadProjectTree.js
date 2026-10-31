// A scoped tree is kept together so searching never separates a child from its parents.
export function treeRows(projects, query = '', showInactive = false, expanded = new Set()) {
    const byId = new Map(projects.map(p => [p.id, p]));
    const children = new Map();
    for (const p of projects) {
        const parent = byId.has(p.parent_id) ? p.parent_id : null;
        if (!children.has(parent)) children.set(parent, []);
        children.get(parent).push(p);
    }
    const terms = query.normalize('NFKC').toLocaleLowerCase().trim().split(/\s+/).filter(Boolean);
    const included = new Set();
    for (const p of projects) {
        const text = `${p.path || p.name} ${p.client?.name || ''}`.normalize('NFKC').toLocaleLowerCase();
        if ((!showInactive && !p.selectable) || !terms.every(t => text.includes(t))) continue;
        let node = p;
        const seen = new Set();
        while (node && !seen.has(node.id)) {
            seen.add(node.id); included.add(node.id); node = byId.get(node.parent_id);
        }
    }
    const rows = [];
    function visit(parent, depth, seen) {
        for (const p of children.get(parent) || []) {
            if (!included.has(p.id) || seen.has(p.id)) continue;
            const childCount = (children.get(p.id) || []).filter(c => included.has(c.id)).length;
            const open = terms.length > 0 || expanded.has(p.id);
            rows.push({ ...p, depth, childCount, open });
            if (open) visit(p.id, depth + 1, new Set([...seen, p.id]));
        }
    }
    visit(null, 0, new Set());
    return rows;
}

export function largeGroups(projects) {
    return projects.filter(p => p.parent_id == null);
}

export function siblings(projects, parentId = null) {
    return projects.filter(p => (p.parent_id ?? null) === parentId);
}

export function shiftOrder(ids, index, direction) {
    const target = index + direction;
    if (target < 0 || target >= ids.length) return [...ids];
    const next = [...ids];
    [next[index], next[target]] = [next[target], next[index]];
    return next;
}

export function broadProjectColor(prefix) {
    if (prefix.startsWith('user.')) return 'bg-blue-600 text-white';
    if (prefix.startsWith('coordinator.')) return 'bg-green-600 text-white';
    if (prefix.startsWith('leader.')) return 'bg-orange-600 text-white';
    return 'bg-yellow-500 text-gray-900';
}
