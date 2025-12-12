/* 貼り付け済みの helper 内容 をここに入れる */
export async function inertiaFetch(url, init = {}) {
    const rel = typeof window !== 'undefined' && url && url.indexOf(window.location.origin) === 0 ? url.replace(window.location.origin, '') : url;
    const defaultInit = {
        credentials: 'same-origin',
        ...init,
    };
    let res;
    try {
        res = await fetch(rel, defaultInit);
    } catch (err) {
        throw err;
    }
    if (res.redirected && res.url) {
        window.location.href = res.url;
        return { navigated: true };
    }
    if (res.status === 409) {
        const inertiaLocation = res.headers.get('x-inertia-location') || res.headers.get('X-Inertia-Location') || null;
        const locationHeader = res.headers.get('location') || null;
        const dest = inertiaLocation || locationHeader;
        if (dest) {
            if (/^https?:\/\//i.test(dest)) {
                window.location.href = dest;
            } else {
                window.location.href = window.location.origin + dest;
            }
            return { navigated: true };
        }
        window.location.reload();
        return { navigated: true };
    }
    if (res.status >= 300 && res.status < 400) {
        const locationHeader = res.headers.get('location') || null;
        if (locationHeader) {
            if (/^https?:\/\//i.test(locationHeader)) {
                window.location.href = locationHeader;
            } else {
                window.location.href = window.location.origin + locationHeader;
            }
            return { navigated: true };
        }
    }
    return res;
}
export default inertiaFetch;
