import { readFile } from 'node:fs/promises';
import assert from 'node:assert/strict';
import test from 'node:test';
const source = await readFile(new URL('../../resources/js/utils/broadProjectTree.js', import.meta.url), 'utf8');
const { treeRows, largeGroups, siblings, shiftOrder } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);
const nodes = [
    {id:1,name:'模試',path:'模試',selectable:true},
    {id:2,parent_id:1,name:'公開',path:'模試 ／ 公開',selectable:true},
    {id:3,parent_id:2,name:'2027',path:'模試 ／ 公開 ／ 2027年度',selectable:true,client:{name:'取引先Ａ'}},
    {id:4,name:'休止',path:'休止',selectable:false},
    {id:5,parent_id:4,name:'子',path:'休止 ／ 子',selectable:false},
];
test('collapsed, expanded and ancestor-aware search keep the tree together', () => {
    assert.deepEqual(treeRows(nodes).map(n=>n.id),[1]);
    assert.deepEqual(treeRows(nodes,'',false,new Set([1,2])).map(n=>n.id),[1,2,3]);
    assert.deepEqual(treeRows(nodes,'取引先A ２０２７').map(n=>n.id),[1,2,3]);
    assert.deepEqual(treeRows(nodes,'模試 公開').map(n=>n.id),[1,2,3]);
    assert.deepEqual(treeRows(nodes,'missing'),[]);
    assert.deepEqual(treeRows(nodes,'子').map(n=>n.id),[]);
    assert.deepEqual(treeRows(nodes,'子',true).map(n=>n.id),[4,5]);
});
test('large group choices and sibling ordering never mix classification levels', () => {
    assert.deepEqual(largeGroups(nodes).map(n=>n.id), [1,4]);
    assert.deepEqual(siblings(nodes,1).map(n=>n.id), [2]);
    assert.deepEqual(siblings(nodes).map(n=>n.id), [1,4]);
    assert.deepEqual(shiftOrder([1,4],0,1),[4,1]);
    assert.deepEqual(shiftOrder([1,4],0,-1),[1,4]);
    assert.deepEqual(shiftOrder([1,4],1,1),[1,4]);
});
