// Derive app base path from Vite's BASE_URL (e.g. '/members/build/' -> '/members', '/build/' -> '')
const _APP_BASE = (typeof import.meta !== 'undefined' && import.meta.env && import.meta.env.BASE_URL
    ? import.meta.env.BASE_URL
    : '/build/').replace(/\/build\/?$/, '');

const STREAM_BASE = (function () {
    if (typeof window === 'undefined' || !window.location) return _APP_BASE + '/attachments/stream';
    const p = String(window.location.pathname || '').toLowerCase();
    if (p.startsWith(_APP_BASE + '/chat')) return _APP_BASE + '/chat/attachments';
    if (p.startsWith(_APP_BASE + '/bot')) return _APP_BASE + '/bot/attachments';
    // diaries (diary pages) and default pages use the web stream endpoint by default
    // Use the web-based `/attachments/stream` (served under `web` middleware)
    return _APP_BASE + '/attachments/stream';
})();

export function ensureAttachmentUrl(candidate) {
    // candidate can be: full URL string, '/storage/...' path, 'attachments/...' relative path,
    // or an object { url, path, thumb_path, attachment_id }
    if (!candidate) return null;

    // If object, prefer already-formed fields
    if (typeof candidate === 'object') {
        const meta = candidate;
        // prefer streamable endpoints when we have attachment_id
        if (meta.attachment_id) return `${STREAM_BASE}?id=${encodeURIComponent(meta.attachment_id)}`;
        // prefer thumb_url/public_url if provided
        if (meta.thumb_url) return meta.thumb_url;
        if (meta.public_url) return meta.public_url;
        if (meta.url) return normalizePathToStream(meta.url);
        if (meta.path) return `${STREAM_BASE}?path=${encodeURIComponent(meta.path)}`;
        return null;
    }

    // If string
    if (typeof candidate === 'string') {
        // Full http(s) -> return as-is
        if (candidate.startsWith('http://') || candidate.startsWith('https://')) return candidate;
        // Already a stream endpoint (accept both web and legacy api-prefixed forms)
        if (candidate.startsWith('/api/attachments') || candidate.startsWith('/attachments') ||
            candidate.startsWith('/chat/attachments') || candidate.startsWith(_APP_BASE + '/chat/attachments') ||
            candidate.startsWith(_APP_BASE + '/bot/attachments') || candidate.startsWith(_APP_BASE + '/attachments'))
            return candidate;
        // /storage/ local path -> convert to stream endpoint
        if (candidate.startsWith('/storage/')) {
            const path = candidate.replace(/^\/storage\//, '');
            return `${STREAM_BASE}?path=${encodeURIComponent(path)}`;
        }
        // relative attachments/... -> stream by path
        if (candidate.startsWith('attachments/')) {
            return `${STREAM_BASE}?path=${encodeURIComponent(candidate)}`;
        }
        // any other leading slash path -> return as-is
        if (candidate.startsWith('/')) return candidate;
        // fallback: treat as path under attachments
        return `${STREAM_BASE}?path=${encodeURIComponent(candidate)}`;
    }

    return null;
}

function normalizePathToStream(url) {
    try {
        const parsed = new URL(url, window.location.origin);
        if (parsed.pathname && parsed.pathname.includes('/storage/')) {
            const after = parsed.pathname.substring(parsed.pathname.indexOf('/storage/') + '/storage/'.length);
            return `${STREAM_BASE}?path=${encodeURIComponent(after)}`;
        }
        return url;
    } catch (e) {
        return url;
    }
}

export function ensureThumbUrl(meta) {
    if (!meta) return null;
    // meta may be string or object
    if (typeof meta === 'string') return ensureAttachmentUrl(meta);
    if (meta.thumb_url) return ensureAttachmentUrl(meta.thumb_url);
    if (meta.thumb_path) return `${STREAM_BASE}?path=${encodeURIComponent(meta.thumb_path)}`;
    // try fallbacks
    if (meta.attachment_id) return `${STREAM_BASE}?id=${encodeURIComponent(meta.attachment_id)}&thumb=1`;
    if (meta.path) return `${STREAM_BASE}?path=${encodeURIComponent(meta.path)}`;
    if (meta.url) return ensureAttachmentUrl(meta.url);
    return null;
}
