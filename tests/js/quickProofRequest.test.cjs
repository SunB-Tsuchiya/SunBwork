const fs = require('fs');
const path = require('path');
const os = require('os');
const assert = require('node:assert/strict');
const { build } = require('esbuild');
const { parse, compileScript } = require('@vue/compiler-sfc');
const { createRenderer, h, nextTick } = require('vue');
const root = path.resolve(__dirname, '../..');
const out = fs.mkdtempSync(path.join(os.tmpdir(), 'sb-quick-proof-'));
const calls = [];
global.__proofRouter = { post: (...args) => calls.push(args) };
const renderer = createRenderer({ patchProp() {}, insert() {}, remove() {}, createElement: () => ({}), createText: () => ({}), createComment: () => ({}), setText() {}, setElementText() {}, parentNode() { return {}; }, nextSibling() { return null; } });
(async () => {
    const file = path.join(root, 'resources/js/Components/ProofRequestModal.vue');
    await build({ entryPoints: [file], outfile: path.join(out, 'modal.cjs'), bundle: true, platform: 'node', format: 'cjs', plugins: [{ name: 'actual-vue', setup(b) {
        b.onResolve({ filter: /^vue$/ }, () => ({ path: require.resolve('vue'), external: true }));
        b.onResolve({ filter: /^(@inertiajs\/vue3|ziggy-js)$/ }, a => ({ path: a.path, namespace: 'mock' }));
        b.onLoad({ filter: /.*/, namespace: 'mock' }, a => ({ loader: 'js', contents: a.path === 'ziggy-js' ? 'export const route=(name,params)=>name+JSON.stringify(params||{});' : 'export const router=globalThis.__proofRouter;' }));
        b.onLoad({ filter: /\.vue$/ }, a => {
            const source = fs.readFileSync(a.path, 'utf8').replace('</script>', '\nglobalThis.__proofState={form,submitting,errors,submit};\n</script>');
            const { descriptor } = parse(source);
            return { loader: 'js', contents: compileScript(descriptor, { id: 'proof-test' }).content, resolveDir: path.dirname(a.path) };
        });
    } }] });
    const component = require(path.join(out, 'modal.cjs')).default;
    component.render = () => null;
    async function mount(extra) {
        let close = 0;
        let sent = 0;
        const props = require('vue').reactive({ show: false, initialTitle: '試験_組版', ...extra });
        const app = renderer.createApp({ render: () => h(component, { ...props, onClose: () => close++, onSent: () => sent++ }) });
        app.mount({});
        props.show = true;
        await nextTick();
        return { app, state: global.__proofState, counts: () => ({ close, sent }) };
    }
    const quick = await mount({ quickEntryId: 7, projectJobId: 88 });
    const s = quick.state;
    assert.equal(s.form.value.title, '試験_校正');
    s.form.value.deadline_date = '2026-12-01';
    s.form.value.note = '保持する備考';
    s.submit(); s.submit();
    assert.equal(calls.length, 1, 'double click is ignored while pending');
    let [url, body, options] = calls[0];
    assert.equal(url, 'user.myjobbox.entries.proof_request{"entry":7}');
    assert.deepEqual(Object.keys(body).sort(), ['deadline', 'note', 'title']);
    assert.equal(body.deadline, '2026-12-01T08:30:00.000Z');
    options.onError({ title: '校正窓口がありません' }); options.onFinish();
    assert.deepEqual(quick.counts(), { close: 0, sent: 0 });
    assert.equal(s.form.value.note, '保持する備考');
    assert.equal(s.errors.value.title, '校正窓口がありません');
    s.submit();
    assert.equal(calls.length, 2);
    calls[1][2].onSuccess(); calls[1][2].onFinish();
    assert.deepEqual(quick.counts(), { close: 1, sent: 1 });
    quick.app.unmount();
    const legacy = await mount({ projectJobId: 88, projectJobAssignmentId: 19, proofCellId: 3 });
    legacy.state.form.value.deadline_date = '2026-12-01';
    legacy.state.submit();
    [url, body, options] = calls[2];
    assert.equal(url, 'proof_requests.store{}');
    assert.equal(body.project_job_assignment_id, 19);
    assert.equal(body.project_job_id, 88);
    assert.equal(body.proof_cell_id, 3);
    options.onSuccess(); options.onFinish();
    assert.deepEqual(legacy.counts(), { close: 1, sent: 1 });
    legacy.app.unmount();
    // Compile all changed actual templates as well as their behavior scripts.
    for (const relative of ['Components/ProofRequestModal.vue', 'Components/ProofQuickWorkSource.vue', 'Pages/MyJobBox/WorkDetail.vue', 'Pages/ProofCoordinator/Inbox/Index.vue', 'Pages/ProofCoordinator/Inbox/Assign.vue', 'Pages/User/ProofStatus.vue']) {
        const { descriptor, errors } = parse(fs.readFileSync(path.join(root, 'resources/js', relative), 'utf8'));
        assert.equal(errors.length, 0);
        compileScript(descriptor, { id: relative, inlineTemplate: true });
    }
    console.log('Actual proof Vue: quick/legacy payloads, JST deadline, pending duplicate, validation input retention, success close and changed templates passed');
})().catch(error => { console.error(error); process.exitCode = 1; });
