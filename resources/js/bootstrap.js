import axios from 'axios';

axios.defaults.baseURL = 'http://localhost';
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
};

// CSRF cookie を取得してから Echo を初期化する（race を防ぐ）
window.axios
    .get('/sanctum/csrf-cookie')
    .then(() => {
        initEcho();
    })
    .catch((err) => {
        // 取得に失敗しても念のため初期化を試みるが、コンソールに警告を出す
        console.warn('Failed to fetch /sanctum/csrf-cookie before Echo init', err);
        initEcho();
    });
