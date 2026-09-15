import test from 'node:test';
import assert from 'node:assert/strict';
import { addDays, dayClass, jstToday, monthStart, stripRange } from '../../resources/js/Components/Clerk/clerkCalendarDates.js';
import { duplicateEventForm, shiftedDuplicateEnd } from '../../resources/js/Components/Clerk/clerkEventDuplicate.js';

test('月初を含む日曜から月境界にかかわらず35日を表示できる', () => {
    for (const [year, month, start, last] of [
        [2026, 9, '2026-08-30', '2026-10-03'],
        [2026, 2, '2026-02-01', '2026-03-07'],
        [2026, 5, '2026-04-26', '2026-05-30'],
        [2026, 12, '2026-11-29', '2027-01-02'],
        [2027, 1, '2026-12-27', '2027-01-30'],
    ]) {
        assert.equal(monthStart(year, month), start);
        assert.equal(addDays(start, 34), last);
    }
});
test('年間範囲に年初年末の5週表示とスクロール余白を含む', () => {
    const range = stripRange(2026);
    assert.ok(range.start < monthStart(2026, 1));
    assert.ok(range.end > addDays(monthStart(2026, 12), 35));
    assert.equal(addDays('2028-02-28', 1), '2028-02-29');
    assert.equal(addDays('2028-02-29', 1), '2028-03-01');
});
test('UTCで前日でもJSTの現在日付を返す', () => {
    assert.equal(jstToday(new Date('2026-08-31T15:00:00Z')), '2026-09-01');
    assert.equal(jstToday(new Date('2026-12-31T15:01:00Z')), '2027-01-01');
});
test('土曜祝日は赤優先、祝日削除後は曜日の配色に戻る', () => {
    assert.equal(dayClass('2026-09-05', {}), 'clerk-saturday');
    assert.equal(dayClass('2026-09-05', { '2026-09-05': '設定した休日' }), 'clerk-holiday');
    assert.equal(dayClass('2026-09-06', {}), 'clerk-holiday');
    assert.equal(dayClass('2026-09-22', { '2026-09-22': '国民の休日' }), 'clerk-holiday');
    assert.equal(dayClass('2026-09-22', {}), '');
});
test('複製は内容と期間を引き継ぎ、完了と自動設定の紐づけを外す', () => {
    const copy = duplicateEventForm({ title: '申請', description: '内容', start_date: '2026-12-30', end_date: '2027-01-02', all_day: true, color_key: 'purple', completed: true, schedule_rule_id: 9 });
    assert.equal(copy.title, '申請');
    assert.equal(copy.color_key, 'purple');
    assert.equal(copy.completed, false);
    assert.equal(copy.schedule_rule_id, null);
    assert.equal(shiftedDuplicateEnd('2026-12-30', '2027-01-05', copy.end_date), '2027-01-08');
});
