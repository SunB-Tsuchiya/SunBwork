const fs = require('fs');
const os = require('os');
const path = require('path');
const assert = require('node:assert/strict');
const { build } = require('esbuild');
const { parse, compileScript } = require('@vue/compiler-sfc');
const { createRenderer, createSSRApp, h, reactive } = require('vue');
const { renderToString } = require('vue/server-renderer');
const root = path.resolve(__dirname, '../..');
const output = fs.mkdtempSync(path.join(os.tmpdir(), 'sb-broad-clients-'));
const calls = [];
global.document = {addEventListener() {}, removeEventListener() {}};
global.route = (name, params) => name + JSON.stringify(params || {});
global.__broadForm = (initial) => Object.assign(reactive(initial), {
    errors: {}, processing: false, put: (url, options) => calls.push(['put', url, options]), post: (url) => calls.push(['post', url]), reset() {}, clearErrors() {},
});
global.__broadAxios = { get: async () => ({data: [{id: 2, name: 'Beta'}]}) };
async function component(file, behavior = false) {
    const dest = path.join(output, path.basename(file) + behavior + '.cjs');
    await build({entryPoints: [path.join(root, file)], outfile: dest, bundle: true, platform: 'node', format: 'cjs', plugins: [{name: 'vue-test', setup(b) {
        b.onResolve({filter: /^vue$/}, a => ({path: require.resolve(a.path), external: true}));
        b.onResolve({filter: /^@\//}, a => ({path: path.join(root, 'resources/js', a.path.slice(2) + (path.extname(a.path) ? '' : '.js'))}));
        b.onResolve({filter: /^(axios|@inertiajs\/vue3)$/}, a => ({path: a.path, namespace: 'mock'}));
        b.onLoad({filter: /.*/, namespace: 'mock'}, a => ({loader: 'js', contents: a.path === 'axios' ? 'export default global.__broadAxios;' : 'export const useForm=global.__broadForm;'}));
        b.onLoad({filter: /\.vue$/}, a => {
            let source = fs.readFileSync(a.path, 'utf8');
            if (behavior && a.path.endsWith('BroadProjectForm.vue')) source = source.replace('</script>', '\nglobal.__broadState={form,selectedClients,clientSearch,chooseClient,removeClient,searchClients,save};\n</script>');
            if (behavior) source = source.replace(/<template>[\s\S]*<\/template>/, '<template><div /></template>');
            const {descriptor} = parse(source, {filename: a.path});
            if (behavior) descriptor.template.content = '<div />';
            return {loader: 'js', contents: compileScript(descriptor, {id: 'broad-test', inlineTemplate: true}).content};
        });
    }}]});
    return require(dest).default;
}
const renderer = createRenderer({createElement: () => ({addEventListener() {}, removeEventListener() {}, tagName: 'INPUT'}), createText: () => ({}), createComment: () => ({}), setText() {}, setElementText() {}, patchProp() {}, insert() {}, remove() {}, parentNode() {}, nextSibling() {}});
(async () => {
    const form = await component('resources/js/Pages/SuperAdmin/BroadProjects/BroadProjectForm.vue', true);
    const app = renderer.createApp({render: () => h(form, {project: {id: 7, name: 'Calendar', clients: [{id: 1, name: 'Alpha'}]}, routePrefix: 'user.work.broad_projects'})});
    app.mount({}); const state = global.__broadState;
    assert.deepEqual([...state.form.client_ids], [1]);
    state.clientSearch.value = 'Beta'; await state.searchClients();
    assert.deepEqual([...state.form.client_ids], [1]);
    state.chooseClient({id: 2, name: 'Beta'}); state.chooseClient({id: 2, name: 'Beta'});
    assert.deepEqual([...state.form.client_ids], [1, 2]);
    state.save(); assert.equal(calls[0][1], 'user.work.broad_projects.update{"broadProject":7}');
    assert.ok(!('client_id' in state.form)); assert.ok(!('parent_id' in state.form));
    state.removeClient(1); assert.deepEqual([...state.form.client_ids], [2]);
    state.removeClient(2); assert.deepEqual([...state.form.client_ids], []); app.unmount();
    const actualForm = await component('resources/js/Pages/SuperAdmin/BroadProjects/BroadProjectForm.vue');
    const formHtml = await renderToString(createSSRApp(actualForm, {project: {id:7,name:'Calendar',clients:[{id:1,name:'Alpha'}, {id:2,name:'Beta'}]}}));
    assert.ok(formHtml.includes('Alpha')); assert.ok(formHtml.includes('Beta')); assert.ok(formHtml.includes('複数選択可'));
    const display = await component('resources/js/Components/ProjectJobs/BroadProjectClients.vue');
    const html = await renderToString(createSSRApp(display, {clients: [{id:1,name:'Alpha'}, {id:2,name:'<Beta>'}, {id:3,name:'Gamma'}]}));
    assert.ok(html.includes('ほか1社')); assert.ok(html.includes('<details')); assert.ok(html.includes('Gamma')); assert.ok(html.includes('&lt;Beta&gt;'));
    const picker = await component('resources/js/Components/ProjectJobs/BroadProjectPicker.vue');
    const pickerHtml = await renderToString(createSSRApp(picker, {modelValue:7, options:[{id:7,label:'Calendar',active:true,clients:[{id:1,name:'Alpha'}]}]}));
    assert.ok(pickerHtml.includes('Alpha')); assert.ok(pickerHtml.includes('クライアント名'));
    console.log('Broad clients Vue: existing selection, search retention, deduplication, explicit clear, named route, metadata payload, disclosure and escaped client names passed');
})().catch(e => {console.error(e); process.exitCode = 1;});
