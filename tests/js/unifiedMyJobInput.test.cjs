const fs = require('fs');
const path = require('path');
const os = require('os');
const assert = require('node:assert/strict');
const { build } = require('esbuild');
const { parse, compileScript } = require('@vue/compiler-sfc');
const { createRenderer, createSSRApp, h, reactive, nextTick } = require('vue');
const { renderToString } = require('vue/server-renderer');
const root = path.resolve(__dirname, '../..');
const output = fs.mkdtempSync(path.join(os.tmpdir(), 'sb-unified-input-'));
const modal = 'resources/js/Components/Calendar/QuickWorkEntryModal.vue';
const page = 'resources/js/Pages/MyJobBox/UnifiedPreview.vue';
let requests = [];
let failSave = false;
globalThis.__unifiedAxios = {
    get: async (...args) => { requests.push(['get', ...args]); return { data: { default_category: 'other', broad_projects: globalThis.__pickerFixture || [] } }; },
    post: async (...args) => { requests.push(['post', ...args]); if (failSave) throw { response: { data: { errors: { title: ['保存の検証エラー'] } } } }; return { data: { id: 1 } }; },
    delete: async (...args) => { requests.push(['delete', ...args]); return { data: {} }; },
    put: async (...args) => { requests.push(['put', ...args]); return { data: { id: 1 } }; },
};
globalThis.__unifiedRouter = { get: (...args) => { globalThis.__unifiedNavigation = args; }, reload: (...args) => { globalThis.__detailReload = args; }, visit: (...args) => { globalThis.__detailVisit = args; } };
const route = (name, params) => name + (params ? JSON.stringify(params) : '');
async function component(file, behavior = false) {
    const outfile = path.join(output, path.basename(file) + (behavior ? '.behavior' : '.ssr') + '.cjs');
    await build({ entryPoints: [path.join(root, file)], outfile, bundle: true, platform: 'node', format: 'cjs', external: ['vue', 'vue/server-renderer'], plugins: [{ name: 'vue-test', setup(b) {
        b.onResolve({ filter: /^vue(\/server-renderer)?$/ }, a => ({ path: require.resolve(a.path), external: true }));
        b.onResolve({ filter: /^(axios|ziggy-js|@inertiajs\/vue3)$/ }, a => ({ path: a.path, namespace: 'mock' }));
        b.onLoad({ filter: /.*/, namespace: 'mock' }, a => ({ loader: 'js', contents: a.path === 'axios' ? 'export default globalThis.__unifiedAxios;' : a.path === 'ziggy-js' ? `export const route=${route.toString()};` : `const {h}=require('vue'); export const router=globalThis.__unifiedRouter; export const Link={props:['href'],setup(p,{slots}){return ()=>h('a',{href:p.href},slots.default?.())}};` }));
        b.onResolve({ filter: /^@\// }, a => a.path.endsWith('.vue') ? { path: a.path, namespace: 'shell' } : { path: path.join(root, 'resources/js', a.path.slice(2) + '.js') });
        b.onLoad({ filter: /.*/, namespace: 'shell' }, () => ({ loader: 'js', contents: `const {h}=require('vue'); export default {setup(p,{slots}){return ()=>h('div',{},[slots.header?.(),slots.tabs?.(),slots.default?.()])}};` }));
        b.onLoad({ filter: /\.vue$/ }, a => {
            let source = fs.readFileSync(a.path, 'utf8');
            if (behavior) source = source.replace('</script>', (file === modal
                ? '\nglobalThis.__unifiedModal={form,save,remove,heading,error,saving,broadTreeRows,toggleBroadProject,projectSearch,openProjectPicker,chooseBroadProject};'
                : file === page ? '\nglobalThis.__unifiedPage={openCreate,onSaved,createDate,showCreate};' : '\nglobalThis.__detailState={saved,deleted,editing,backHref};') + '\n</script>');
            if (!behavior && file === modal && globalThis.__pickerFixture) source = source.replace('</script>', '\nbroadProjects.value=globalThis.__pickerFixture; projectPickerOpen.value=true;\n</script>');
            const { descriptor } = parse(source);
            return { loader: 'js', contents: compileScript(descriptor, { id: 'unified-input', inlineTemplate: !behavior, templateOptions: { ssr: !behavior } }).content, resolveDir: path.dirname(a.path) };
        });
    } }] });
    const result = require(outfile).default;
    if (behavior) result.render = () => null;
    return result;
}
const renderer = createRenderer({
    createElement: () => ({}), createText: () => ({}), createComment: () => ({}),
    insert() {}, remove() {}, setText() {}, setElementText() {}, patchProp() {}, parentNode() {}, nextSibling() {},
});
async function settle() { await nextTick(); await Promise.resolve(); await nextTick(); }
(async () => {
    globalThis.__pickerFixture = [{id:1,name:'大分類',path:'大分類',is_active:true}, ...Array.from({length:40},(_,i)=>({id:i+2,parent_id:1,name:'中分類'+(i+2),path:'大分類 ／ 中分類'+(i+2),is_active:true,is_favorite:i===39})), {id:100,name:'別の大分類',path:'別の大分類',is_active:true}];
    const formComponent = await component(modal, true);
    const props = reactive({ show: false, date: '2026-12-01', entryPoint: 'myjob' });
    const saved = []; const deleted = [];
    const app = renderer.createApp({ render: () => h(formComponent, { ...props, onSaved: date => saved.push(date), onDeleted: date => deleted.push(date) }) });
    app.mount({}); props.show = true; await settle();
    let state = globalThis.__unifiedModal;
    assert.equal(state.heading.value, 'マイジョブ作成');
    state.openProjectPicker(); await settle();
    assert.deepEqual(state.broadTreeRows.value.map(p=>p.id),[1,100]);
    state.toggleBroadProject(1); assert.equal(state.broadTreeRows.value.length,42);
    state.toggleBroadProject(1); state.projectSearch.value='中分類41';
    assert.deepEqual(state.broadTreeRows.value.map(p=>p.id),[1,41]);
    state.chooseBroadProject(state.broadTreeRows.value[1]); assert.equal(state.form.broad_project_id,41);
    state.form.broad_project_id='';

    assert.equal(state.form.work_date, '2026-12-01');
    assert.equal(state.form.planned_start_time, ''); assert.equal(state.form.planned_end_time, '');
    Object.assign(state.form, { title: '試用作業', planned_start_time: '09:00', planned_end_time: '09:30', actual_quantity: '' });
    failSave = true; await state.save();
    assert.equal(state.error.value, '保存の検証エラー'); assert.equal(saved.length, 0); assert.equal(state.form.title, '試用作業');
    failSave = false; await state.save();
    let last = requests.at(-1); assert.equal(last[0], 'post'); assert.equal(last[1], 'user.myjobbox.unified_store');
    assert.equal(last[2].actual_quantity, null); assert.equal(last[2].actual_unit, null); assert.equal(last[2].project_job_id, null);
    assert.deepEqual(saved, ['2026-12-01']); assert.equal(state.saving.value, false);
    props.show = false; await settle(); props.entryPoint = 'calendar'; props.show = true; await settle();
    assert.equal(state.heading.value, '簡単な作業予定を追加'); assert.equal(state.form.title, '');
    Object.assign(state.form, { planned_start_time: '10:00', planned_end_time: '10:15' }); await state.save();
    assert.equal(requests.at(-1)[1], 'schedule.department_work.store');
    props.show = false; await settle(); props.entry = { id: 7, work_date: '2026-12-02', planned_start_time: '11:00', planned_end_time: '11:15' }; props.show = true; await settle();
    assert.equal(state.heading.value, '作業予定を編集'); await state.save();
    assert.equal(requests.at(-1)[0], 'put'); assert.equal(requests.at(-1)[1], 'schedule.department_work.update{"entry":7}');
    props.entryPoint = 'myjob'; await settle(); await state.save();
    assert.equal(requests.at(-1)[1], 'user.myjobbox.entries.update{"entry":7}');
    const oldConfirm = global.confirm; global.confirm = () => true;
    const beforeDelete = saved.length; await state.remove();
    assert.equal(requests.at(-1)[1], 'user.myjobbox.entries.destroy{"entry":7}');
    assert.equal(deleted.length, 1); assert.equal(saved.length, beforeDelete);
    props.entryPoint = 'calendar'; await settle(); await state.remove();
    assert.equal(requests.at(-1)[1], 'schedule.department_work.destroy{"entry":7}');
    assert.equal(saved.length, beforeDelete + 1); global.confirm = oldConfirm;
    app.unmount();
    const preview = await component(page, true);
    const pageProps = { rows: [], months: ['2026-11'], filters: { q: 'old search', period: '2026-11', hide_completed: true }, pagination: { total: 0, page: 1, last: 1 } };
    const box = renderer.createApp({ render: () => h(preview, pageProps) }); box.mount({});
    const RealDate = Date; global.Date = class extends RealDate { constructor(...args) { super(...(args.length ? args : ['2026-11-01T15:30:00Z'])); } };
    globalThis.__unifiedPage.openCreate(); global.Date = RealDate;
    assert.equal(globalThis.__unifiedPage.createDate.value, '2026-11-02'); assert.equal(globalThis.__unifiedPage.showCreate.value, true);
    globalThis.__unifiedPage.onSaved('2026-12-01', 1);
    assert.equal(globalThis.__unifiedPage.showCreate.value, false);
    assert.deepEqual(globalThis.__unifiedNavigation, ['user.myjobbox.unified_preview', { period: '2026-12', created_entry: 1 }, { preserveState: false }]);
    box.unmount();
    const actualForm = await component(modal);
    for (const entryPoint of ['calendar', 'myjob']) {
        const ctx = {};
        await renderToString(createSSRApp(actualForm, { show: true, entryPoint }), ctx);
        const html = ctx.teleports.body;
        assert.ok(html.includes(entryPoint === 'myjob' ? 'マイジョブ作成' : '簡単な作業予定を追加'));
        assert.equal(html.includes('カレンダーで選んだ時刻'), entryPoint === 'calendar');
        for (const unit of ['ページ','ファイル','件','枚','点','冊']) assert.ok(html.includes('>'+unit+'</button>'));
        assert.ok(html.includes('aria-expanded="false"')); assert.ok(html.includes('大まかな案件（42件）'));
        assert.ok(html.match(/type="time"[^>]*required/)); assert.ok(html.includes('閉じる'));
    }
    const actualPage = await component(page);
    const html = await renderToString(createSSRApp(actualPage, pageProps));
    for (const text of ['マイジョブ作成', '詳細入力・依頼からの登録・添付', '従来のマイジョブBOXへ']) assert.ok(html.includes(text));
    const detailBehavior = await component('resources/js/Pages/MyJobBox/WorkDetail.vue', true);
    const detailProps = {work:{id:1,work_date:'2026-11-03'},time:{},ancestors:[],back:{from:'calendar'}};
    const detailApp = renderer.createApp({render:()=>h(detailBehavior,detailProps)}); detailApp.mount({});
    globalThis.__detailState.editing.value = true; globalThis.__detailState.saved();
    assert.equal(globalThis.__detailState.editing.value, false);
    assert.deepEqual(globalThis.__detailReload, [{only:['work','time','ancestors'],preserveScroll:true}]);
    globalThis.__detailState.deleted(); assert.equal(globalThis.__detailVisit[0], 'calendar.index{"date":"2026-11-03","view":"day"}');
    detailApp.unmount();
    const workDetail = await component('resources/js/Pages/MyJobBox/WorkDetail.vue');
    const detailHtml = await renderToString(createSSRApp(workDetail, {
        work: {id:1,title:'<script>work</script>',work_date:'2026-11-03',planned_start_time:'09:00',planned_end_time:'15:00',work_category:'other',actual_quantity:'90.00',actual_unit:'ページ',completed:false},
        time:{planned:360,lunch:60,afterLunch:300,recorded:150}, ancestors:[], back:{from:'box',period:'all',q:'search'},
    }));
    for (const text of ['マイジョブ詳細','編集','予定枠','昼休憩との重なり','記録時間','150分','360分','90 ページ','案件情報','作業詳細','スケジュール','予定を編集','未完了','&lt;script&gt;work&lt;/script&gt;']) assert.ok(detailHtml.includes(text), text);
    assert.ok(!detailHtml.includes('<script>work</script>'));
    assert.ok(!detailHtml.includes('90.00'));
    console.log('Actual Vue input: BOX/calendar contexts, reset, required time, failure retention, POST/PUT destinations, JST date, saved-month navigation, preserved legacy paths passed');
})().catch(error => { console.error(error); process.exitCode = 1; });
