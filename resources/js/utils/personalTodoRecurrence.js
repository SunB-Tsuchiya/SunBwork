export const personalTodoRepeatOptions = {
    daily: '毎日', weekdays: '平日のみ（月〜金・祝日を含む）', weekly: '毎週', monthly: '毎月', yearly: '毎年',
};
export function personalTodoCompletionNotice(item) {
    const result = item?.repeat_result;
    if (result?.status === 'next') return `完了しました。次回の期限：${result.start_date}`;
    if (result?.status === 'ended') return '完了しました。終了日を迎えたため、繰り返しは終了です。';
    if (result?.status === 'undone') return result.retracted ? '完了を取り消しました。未編集の次回分も取り下げました。' : '完了を取り消しました。';
    return '';
}
export function personalTodoDeleteQuestion(item) {
    if (item?.repeat_has_next) return 'この回の履歴を削除しますか？ 作成済みの次回分は残ります。';
    if (item?.repeat_rule) return 'このTODOを削除しますか？ 削除しても次回分は作成されません。';
    return 'このTODO・締め切りを削除しますか？';
}
