import { inject } from 'vue';
import { route } from 'ziggy-js';

/**
 * 年末調整フォームのルート名を、本人入力（user.general_affairs.nencho.*）と
 * 総務の代理入力（clerk.general_affairs.nencho.proxy.*）で切り替える。
 * Form.vue が provide('nenchoRoutePrefix', …) で接頭辞を渡す。
 */
export function useNenchoRoute() {
    const prefix = inject('nenchoRoutePrefix', 'user.general_affairs.nencho');
    return (name, params = {}) => route(`${prefix}.${name}`, params);
}

/** 編集ロックのトークン（Form.vue が provide する computed）。保存のたびにサーバーへ送り、ロックを持つ画面か確認する */
export function useNenchoLockToken() {
    return inject('nenchoLockToken', null);
}
