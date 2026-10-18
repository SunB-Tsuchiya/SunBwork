// 同じ日の予定を、時間が重なる間だけ別の横列へ配置する。
// 戻り値の event は元オブジェクトなので、クリック・ドラッグ時の識別子は変わらない。
export function layoutTimedEvents(events, intervalFor) {
    const ordered = events.map((event, index) => {
        const { start, end } = intervalFor(event);
        return { event, index, start, end: Math.max(start + 1, end) };
    }).sort((a, b) => a.start - b.start || b.end - a.end || a.index - b.index);

    const placed = [];
    let group = [];
    let groupEnd = -Infinity;
    let laneEnds = [];

    function finishGroup() {
        for (const item of group) {
            placed.push({ event: item.event, lane: item.lane, lanes: laneEnds.length });
        }
        group = [];
        groupEnd = -Infinity;
        laneEnds = [];
    }

    for (const item of ordered) {
        if (group.length && item.start >= groupEnd) finishGroup();

        let lane = laneEnds.findIndex(end => end <= item.start);
        if (lane === -1) lane = laneEnds.length;
        laneEnds[lane] = item.end;
        group.push({ ...item, lane });
        groupEnd = Math.max(groupEnd, item.end);
    }
    if (group.length) finishGroup();

    return placed;
}
