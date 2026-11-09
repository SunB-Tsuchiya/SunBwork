import axios from 'axios';
import { onScopeDispose, ref } from 'vue';
import { route } from 'ziggy-js';

// Replace the complete seed only after the newest request succeeds. Keep current input on errors.
export function useReceivedJobPrefill() {
    const busy = ref(false);
    const error = ref('');
    let sequence = 0;
    function cancel() { sequence += 1; busy.value = false; error.value = ''; }
    async function select(id, apply) {
        const current = ++sequence;
        busy.value = true; error.value = '';
        try {
            const { data } = await axios.get(route('user.myjobbox.received_prefill', { assignment: id }));
            if (current !== sequence) return false;
            if (!data?.project?.id || String(data.assignment?.supersedes_assignment_id) !== String(id) || !data.assignment?._request_revision) {
                throw new Error('Invalid received job');
            }
            apply(data);
            return true;
        } catch (failure) {
            if (current === sequence) error.value = failure.response?.data?.message || '依頼の詳細を取得できませんでした。現在の入力は保持しています。依頼一覧を開き直してください。';
            return false;
        } finally {
            if (current === sequence) busy.value = false;
        }
    }
    onScopeDispose(cancel);
    return { busy, error, cancel, select };
}

export function mergeReceivedCandidates(items, item) {
    return item ? [item, ...items.filter(existing => String(existing.id) !== String(item.id))] : items;
}
