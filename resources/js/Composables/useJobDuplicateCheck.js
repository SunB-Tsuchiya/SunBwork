import { ref } from 'vue';

/**
 * 案件登録・編集時の重複チェック（受注番号・案件名）を扱う Composable。
 *
 * 受注番号の重複は禁止ではなく警告として扱う。
 * グループ会社（印刷 → 組版 → 印刷）で同じ伝票番号を共有したり、
 * 同じ受注番号で組版・可変・発送など別作業を登録したりする運用があるため。
 */
export function useJobDuplicateCheck() {
    const showDuplicateModal = ref(false);
    const titleDuplicates = ref([]);
    const jobcodeDuplicates = ref([]);
    const isCheckingDuplicate = ref(false);

    function closeDuplicateModal() {
        showDuplicateModal.value = false;
    }

    /**
     * 重複チェックを実行する。
     * 重複が見つかったらモーダルを開いて true（＝送信を止める）を返す。
     * 通信に失敗した場合は登録を妨げないよう false を返す。
     *
     * @param {object} params
     * @param {string|null} params.jobcode   受注番号
     * @param {string|null} params.title     案件タイトル
     * @param {number|string|null} params.clientId  クライアント ID（案件名チェックに必要）
     * @param {number|null} params.excludeId 編集時に自分自身を除外する案件 ID
     * @returns {Promise<boolean>} モーダルを表示した場合 true
     */
    async function checkDuplicates({ jobcode = null, title = null, clientId = null, excludeId = null } = {}) {
        // 受注番号、または「クライアント + 案件名」が揃っていなければチェック対象なし
        if (!jobcode && !(clientId && title)) return false;

        isCheckingDuplicate.value = true;
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const res = await fetch(route('coordinator.project_jobs.check_duplicate'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    jobcode: jobcode || null,
                    title: title || null,
                    client_id: clientId || null,
                    exclude_id: excludeId || null,
                }),
            });

            if (!res.ok) return false;

            const data = await res.json();
            const titles = data.duplicates ?? [];
            const jobcodes = data.jobcode_duplicates ?? [];
            if (titles.length === 0 && jobcodes.length === 0) return false;

            titleDuplicates.value = titles;
            jobcodeDuplicates.value = jobcodes;
            showDuplicateModal.value = true;
            return true;
        } catch {
            // チェック失敗時はそのまま保存を続行させる
            return false;
        } finally {
            isCheckingDuplicate.value = false;
        }
    }

    return {
        showDuplicateModal,
        titleDuplicates,
        jobcodeDuplicates,
        isCheckingDuplicate,
        checkDuplicates,
        closeDuplicateModal,
    };
}

export default useJobDuplicateCheck;
