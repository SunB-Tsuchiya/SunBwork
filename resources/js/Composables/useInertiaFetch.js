/**
 * inertiaFetch - Inertia-aware fetch helper
 * - 正規化: 与えられた URL を window.location.origin 基準で解析し、
 *   同一オリジンなら相対パスに変換して fetch を行います。
 * - credentials: 'include' を使いクッキーを確実に送信します。
 * - Inertia の 409 + X-Inertia-Location / 302 等のリダイレクトをハンドルします。
 */
export async function inertiaFetch(url, init = {}) {
    // Resolve URL against current origin; if same-origin, use relative path to ensure cookies included.
    let finalUrl = url;
    try {
        const resolved = new URL(url, window.location.origin);
        if (resolved.origin === window.location.origin) {
            finalUrl = resolved.pathname + resolved.search + resolved.hash;
        } else {
            finalUrl = resolved.toString();
        }
    } catch (e) {
        // If URL parsing fails, fall back to given url
        finalUrl = url;
    }

    const defaultInit = {
        // include にすると same-origin・cross-origin 関係なくクッキー送信を試みる（CORS 側の制約は別）
        credentials: 'include',
        ...init,
    };

    let res;
    try {
        res = await fetch(finalUrl, defaultInit);
    } catch (err) {
        // ネットワークエラーはそのまま投げる（呼び出し元でハンドリング）
        throw err;
    }

    // Fetch が自動でリダイレクトを追った場合（ブラウザ次第）: 最終 URL に移動する
    if (res.redirected && res.url) {
        window.location.href = res.url;
        return { navigated: true };
    }

    // Inertia の「フルページリダイレクト必要」レスポンス: 409 + X-Inertia-Location
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

    // 防御的に 3xx の Location ヘッダも処理
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
