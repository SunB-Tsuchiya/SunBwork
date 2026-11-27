const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { compile } = require('@vue/compiler-ssr');
const { createSSRApp } = require('vue');
const { renderToString } = require('@vue/server-renderer');
const source = fs.readFileSync(path.join(__dirname, '../../resources/js/Pages/Billing/Transport/Manage/Show.vue'), 'utf8');
// Render the actual fare-result cell, including its conditional branches.
const start = source.indexOf('<!-- 運賃マスタとの照合 -->');
const end = source.indexOf('<!-- 入力の内容からの注意', start);
const template = '<div>' + source.slice(start, end) + '</div>';
const { code } = compile(template, { mode: 'function' });
const ssrRender = new Function('require', code)(require);
(async () => {
  for (const state of ['match', 'diff', 'none', 'unverified', 'skip']) {
    for (const via of [false, true]) {
      const it = { amount: 178, check: { fare: { state, registered: 178, expected: 178, diff: 0, via_used: via, legs: [{ from: '赤坂', to: '代々木上原', fare: state === 'none' ? null : 178 }] } } };
      const html = await renderToString(createSSRApp({ ssrRender, setup: () => ({ it, yen: n => `${n}円`, registerFare() {} }) }));
      assert.equal(html.includes('○ 台帳と一致'), state === 'match', `${state}/${via}`);
      assert.equal(html.includes('△ 台帳'), state === 'diff', `${state}/${via}`);
      assert.equal(html.includes('台帳に登録がありません'), ['none', 'unverified'].includes(state), `${state}/${via}`);
      assert.equal(html.includes('この金額で登録'), ['none', 'unverified'].includes(state), `${state}/${via}`);
      assert.equal(html.includes('区間ごとの合計'), via, `${state}/${via}`);
    }
  }
  console.log('PASS: actual Vue fare cell, 5 states × direct/via; exclusive status and preserved leg details.');
})().catch(e => { console.error(e); process.exitCode = 1; });
