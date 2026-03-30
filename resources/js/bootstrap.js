import axios from 'axios';

axios.defaults.baseURL = (window.location?.origin || '') + (import.meta.env.VITE_APP_BASE_PATH || '');
axios.defaults.withCredentials = true; // これも必須

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;

// SPAでAPI認証が必要な場合、初回にCSRFクッキーを取得してから Echo を初期化
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Echo 初期化を関数化して、CSRF クッキー取得完了後に呼ぶ
const initEcho = () => {
    window.Pusher = Pusher;

    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST?.replace(/"/g, ''),
        wsPort: import.meta.env.VITE_REVERB_PORT,
        wssPort: import.meta.env.VITE_REVERB_PORT,
        forceTLS: false, // httpの場合はfalse
        encrypted: false, // httpの場合はfalse
        enabledTransports: ['ws'], // wsのみ
        disableStats: true,
        cluster: '', // Reverbはクラスタ不要
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
        },
    });

    // Ensure Pusher uses axios for channel authorization so that cookies (session)
    // and the configured axios headers (X-CSRF-TOKEN) are sent. Some environments
    // (vite proxy, different origins) may cause the default pusher auth XHR to omit
    // credentials which results in 403 from /broadcasting/auth.
    try {
        if (window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
            const pusher = window.Echo.connector.pusher;
            pusher.config.auth = pusher.config.auth || {};
            pusher.config.auth.headers = pusher.config.auth.headers || {};

            // prefer axios POST which respects axios.defaults.withCredentials
            pusher.config.authorizer = function (channel, options) {
                return {
                    authorize: function (socketId, callback) {
                        const payload = {
                            socket_id: socketId,
                            channel_name: channel.name,
                        };
                        // axios is configured with withCredentials=true above
                        window.axios
                            .post(pusher.config.authEndpoint || '/broadcasting/auth', payload, {
                                headers: pusher.config.auth.headers || {},
                            })
                            .then(function (response) {
                                callback(false, response.data);
                            })
                            .catch(function (error) {
                                // Mirror pusher authorizer callback signature for errors
                                callback(true, error);
                            });
                    },
                };
            };
        }
    } catch (e) {
        console.warn('Failed to attach axios-based pusher authorizer', e);
    }
};

// CSRF cookie を取得してから Echo を初期化する（race を防ぐ）
// REVERB キーが設定されており、かつ wss://localhost への接続でない場合のみ Echo を初期化する
// NOTE: axios.defaults.baseURL には VITE_APP_BASE_PATH 分のパスが含まれている。
// そのため URL に再度 basePath を付けると /members/members/... と二重になる。
const csrfUrl  = '/sanctum/csrf-cookie';
const echoHost = import.meta.env.VITE_REVERB_HOST?.replace(/"/g, '') || '';
const echoEnabled = import.meta.env.VITE_REVERB_APP_KEY && echoHost && echoHost !== 'localhost';

window.axios
    .get(csrfUrl)
    .then(() => {
        if (echoEnabled) initEcho();
    })
    .catch((err) => {
        console.warn('Failed to fetch CSRF cookie before Echo init', err);
        if (echoEnabled) initEcho();
    });
