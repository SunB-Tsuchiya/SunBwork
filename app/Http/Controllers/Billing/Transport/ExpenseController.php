<?php

namespace App\Http\Controllers\Billing\Transport;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\Transport\SaveExpenseRequest;
use App\Models\Department;
use App\Models\TransportExpense;
use App\Services\Billing\Transport\TransportCompany;
use App\Services\Billing\Transport\TransportDeadline;
use App\Services\Billing\Transport\TransportExpenseExporter;
use App\Services\Billing\Transport\TransportExpenseWorkflow;
use App\Models\TransportExpenseItem;
use App\Models\TransportPreset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 交通費の申請者側（1ユーザー × 対象月 = 1申請）。
 * 状態: 下書き → 申請済み →（受理／差戻し）→ 請求済み。申請者が編集できるのは下書き・差戻しのみ。
 */
class ExpenseController extends Controller
{
    public function __construct(private TransportExpenseWorkflow $workflow)
    {
    }

    public function index(Request $request): Response
    {
        $user = $request->user()->load('department');

        // 対象月: 今月と過去 11 か月。指定が無ければ今月
        $months = collect(range(0, 11))->map(fn ($i) => now()->subMonths($i)->format('Y-m'))->values();
        $month  = $request->input('month');
        if (!is_string($month) || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = $months->first();
        }
        [$periodStart, $periodEnd] = TransportExpense::monthRange($month);

        $expense = TransportExpense::where('user_id', $user->id)
            ->where('billing_month', $month)
            ->with(['items.companions.user:id,name', 'reviewer:id,name', 'events.user:id,name'])
            ->first();

        // 部門の選択肢: 会社に登録された有効な部署（＋共通）。初期値は本人の所属（選択肢に無ければ共通）
        $companyId = TransportCompany::idFor($user);
        $departments = TransportCompany::departments($companyId);
        $defaultDeptId = $departments->contains('id', $user->department_id) ? $user->department_id : null;

        // 前月の明細（コピー用。発生日は引き継がない）
        $prevMonth = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $month . '-01')->subMonth()->format('Y-m');
        $previousItems = TransportExpense::where('user_id', $user->id)
            ->where('billing_month', $prevMonth)
            ->first()?->items
            ->map(fn ($it) => [
                'destination' => $it->destination, 'purpose' => $it->purpose, 'purpose_text' => $it->purpose_text,
                'station_from' => $it->station_from, 'station_to' => $it->station_to, 'via_stations' => $it->via_stations ?? [],
                'fare_type' => $it->fare_type, 'amount' => $it->amount,
            ])->values() ?? [];

        // 申請履歴（月ごとの状態と合計）
        $history = TransportExpense::where('user_id', $user->id)
            ->orderByDesc('billing_month')
            ->get(['id', 'billing_month', 'status', 'total_amount'])
            ->map(fn ($e) => [
                'id' => $e->id, 'billing_month' => $e->billing_month,
                'status' => $e->status, 'total_amount' => $e->total_amount,
            ]);

        return Inertia::render('Billing/Transport/Apply/Index', [
            'routeBase'          => $this->routeBase($request),
            'authDepartmentName' => $user->department?->name ?? '',
            'departments'        => $departments,
            'purposes'           => TransportExpenseItem::PURPOSE_LABELS,
            'statusLabels'       => TransportExpense::STATUS_LABELS,
            'actionLabels'       => \App\Models\TransportExpenseEvent::ACTION_LABELS,
            'month'              => $month,
            'months'             => $months,
            'periodStart'        => $periodStart,
            'periodEnd'          => $periodEnd,
            'rowsPerPage'        => TransportExpense::ROWS_PER_PAGE,
            // 期限（当月末日）。canEdit = この月を編集（保存・申請）できるか。差戻しは期限後でも可、総務・経理が解除した月も可
            'canEdit'            => TransportDeadline::canApplicantEdit($user->id, $month, $expense),
            // この月の、期限後の申請の依頼（依頼中・見送り）。許可された場合は deadline.extended が true になる
            'deadlineRequest'    => ($req = \App\Models\TransportDeadlineExtension::where('user_id', $user->id)->where('billing_month', $month)->first())
                ? ['id' => $req->id, 'status' => $req->status, 'reason' => $req->reason, 'decision_comment' => $req->decision_comment,
                    'requested_at' => $req->requested_at?->timezone('Asia/Tokyo')->format('Y/m/d H:i')]
                : null,
            'deadline'           => [
                'passed'   => TransportDeadline::passed($month),
                'extended' => TransportDeadline::extended($user->id, $month),
                'requestable' => TransportDeadline::requestable($month),   // 期限後の申請を依頼できる月か（先月分まで）
                'end'      => TransportDeadline::endOf($month)->toDateString(),
            ],
            'defaultDeptId'      => $defaultDeptId,
            'expense'            => $expense ? [
                'id'              => $expense->id,
                'status'          => $expense->status,
                'charge_department_id' => $expense->charge_department_id,
                'billing_date'    => $expense->billing_date->toDateString(),
                'total_amount'    => $expense->total_amount,
                'submitted_at'    => $expense->submitted_at?->timezone('Asia/Tokyo')->format('Y/m/d H:i'),
                'reviewed_at'     => $expense->reviewed_at?->timezone('Asia/Tokyo')->format('Y/m/d H:i'),
                'reviewer_name'   => $expense->reviewer?->name,
                'review_comment'  => $expense->review_comment,
                'editable'        => $expense->isEditableByApplicant(),
                'deletable'       => $expense->isEditableByApplicant(),   // 下書き・差戻しは、期限後でも削除できる（残った下書きを片付けられる）
                'events'          => $expense->events->map(fn ($ev) => [
                    'id' => $ev->id, 'action' => $ev->action, 'user_name' => $ev->user?->name,
                    'comment' => $ev->comment, 'created_at' => $ev->created_at->timezone('Asia/Tokyo')->format('Y/m/d H:i'),
                ])->values(),
                'items'           => TransportExpenseExporter::sortedItems($expense)->map(fn ($it) => [
                    'occurrence_date' => $it->occurrence_date?->toDateString(),
                    'destination'     => $it->destination,
                    'purpose'         => $it->purpose,
                    'purpose_text'    => $it->purpose_text,
                    'station_from'    => $it->station_from,
                    'station_to'      => $it->station_to,
                    'via_stations'    => $it->via_stations ?? [],
                    'companions'      => $it->companions->map(fn ($c) => ['id' => $c->user_id, 'name' => $c->user?->name ?? '（退職・削除）'])->values(),
                    'fare_type'       => $it->fare_type,
                    'amount'          => $it->amount,
                    'base_amount'     => $it->base_amount ?? $it->amount,   // 旧データ（片道の記録なし）は合計のまま
                    'auto_double'     => (bool) $it->auto_double,
                ])->values(),
            ] : null,
            'previousItems'      => $previousItems,
            'colleagues'         => $this->colleagues($user, $companyId),
            'suggestions'        => $this->suggestions($user->id),
            'history'            => $history,
        ]);
    }

    /**
     * 同行者に選べる人: 同じ会社の社員（本人・ゴーストは除く）。申請者管理で OFF の人も選べる（名前を載せるだけ）。
     */
    private function colleagues($user, ?int $companyId): array
    {
        if (!$companyId) {
            return [];
        }
        return \App\Models\User::with(['department:id,name', 'positionTitle:id,name'])
            ->where('company_id', $companyId)->where('is_ghost', false)->whereNull('ghost_owner_id')->where('id', '!=', $user->id)
            ->orderBy('name')->get()
            ->map(fn ($u) => [
                'id' => $u->id, 'name' => $u->name, 'department' => $u->department?->name ?? '未所属',
                'position_title' => $u->positionTitle?->name, 'employment_type' => $u->employment_type ?? 'regular',
            ])->values()->all();
    }

    /**
     * 入力補助の候補。登録した行先（registered=true）と、過去 12 か月の自分の入力履歴を、行先＋用件＋区間でまとめる。
     * 登録済みを先に、同じ中では使った回数の多い順。履歴は最新の金額を使う。
     */
    private function suggestions(int $userId): array
    {
        $key = fn ($d, $p, $f, $t) => implode("\u{1}", [$d, $p, $f ?? '', $t ?? '']);

        $history = [];
        TransportExpenseItem::query()
            ->join('transport_expenses', 'transport_expenses.id', '=', 'transport_expense_items.transport_expense_id')
            ->where('transport_expenses.user_id', $userId)
            ->where('transport_expenses.billing_month', '>=', now()->subMonths(12)->format('Y-m'))
            ->whereNotNull('transport_expense_items.destination')->where('transport_expense_items.destination', '!=', '')
            ->orderByDesc('transport_expenses.billing_month')->orderByDesc('transport_expense_items.id')
            ->limit(2000)
            ->get(['transport_expense_items.destination', 'transport_expense_items.purpose', 'transport_expense_items.purpose_text',
                'transport_expense_items.station_from', 'transport_expense_items.station_to', 'transport_expense_items.fare_type',
                'transport_expense_items.amount', 'transport_expense_items.base_amount', 'transport_expense_items.auto_double'])
            ->each(function ($it) use (&$history, $key) {
                $k = $key($it->destination, $it->purpose, $it->station_from, $it->station_to);
                if (!isset($history[$k])) {   // 先に来るもの（最新）の金額を使う
                    $history[$k] = [
                        'destination' => $it->destination, 'purpose' => $it->purpose, 'purpose_text' => $it->purpose_text,
                        'station_from' => $it->station_from ?? '', 'station_to' => $it->station_to ?? '', 'fare_type' => $it->fare_type,
                        'base_amount' => $it->base_amount ?? $it->amount, 'auto_double' => (bool) $it->auto_double,
                        'amount' => $it->amount, 'count' => 0,
                    ];
                }
                $history[$k]['count']++;
            });

        $out = [];
        foreach (TransportPreset::where('user_id', $userId)->orderBy('destination')->get() as $p) {
            $k = $key($p->destination, $p->purpose, $p->station_from, $p->station_to);
            $out[$k] = [
                'id' => $p->id, 'registered' => true,
                'destination' => $p->destination, 'purpose' => $p->purpose, 'purpose_text' => $p->purpose_text,
                'station_from' => $p->station_from ?? '', 'station_to' => $p->station_to ?? '', 'via_stations' => $p->via_stations ?? [], 'fare_type' => $p->fare_type,
                'base_amount' => $p->base_amount, 'auto_double' => $p->auto_double, 'amount' => $p->amount,
                'count' => $history[$k]['count'] ?? 0,
            ];
        }
        foreach ($history as $k => $h) {
            if (!isset($out[$k])) {
                $out[$k] = ['id' => null, 'registered' => false] + $h;
            }
        }

        return collect($out)->sort(fn ($a, $b) => [$b['registered'], $b['count']] <=> [$a['registered'], $a['count']])
            ->take(500)->values()->all();
    }

    /** 保存（その月の申請が無ければ作成、あれば更新）。下書き・差戻しのときだけ */
    public function store(SaveExpenseRequest $request)
    {
        $expense = $this->persist($request->validated(), $request->user());

        return redirect()->route($this->routeBase($request) . 'index', ['month' => $expense->billing_month])
            ->with('success', '交通費を保存しました（まだ申請はされていません）。');
    }

    /**
     * 自動保存（入力が止まったら、画面から自動で呼ぶ）。下書きとして保存するだけで、申請はしない。
     * 画面（入力中の行）を書き換えないよう、リダイレクトではなく JSON を返す。
     */
    public function autosave(SaveExpenseRequest $request)
    {
        $expense = $this->persist($request->validated(), $request->user());

        return response()->json([
            'ok'       => true,
            'id'       => $expense->id,
            'status'   => $expense->status,
            'total'    => $expense->total_amount,
            'saved_at' => now()->timezone('Asia/Tokyo')->format('H:i'),
        ]);
    }

    /** 保存の本体（store と autosave で共通） */
    private function persist(array $data, $user): TransportExpense
    {
        DB::transaction(function () use ($data, $user, &$expense) {
            $expense = TransportExpense::where('user_id', $user->id)
                ->where('billing_month', $data['billing_month'])
                ->lockForUpdate()
                ->first();

            if ($expense && !$expense->isEditableByApplicant()) {
                abort(422, 'この月の申請は申請済み／受理済みのため、編集できません。');
            }
            // 期限（当月末日）。差戻しされたものは、期限後でも直せる。下書き・新規は、期限内か、総務・経理が解除したときだけ
            if (!TransportDeadline::canApplicantEdit($user->id, $data['billing_month'], $expense)) {
                abort(422, TransportDeadline::lockedMessage($data['billing_month']));
            }

            // 往復の「片道×2」は、サーバーでも検算して合計（amount）をそろえる。×2 は用件が往復のときだけ有効
            $items = collect($data['items'])->values()->map(function ($it) {
                $doubled = !empty($it['auto_double']) && in_array($it['purpose'], TransportExpenseItem::DOUBLE_PURPOSES, true);
                if ($doubled) {
                    $base = $it['base_amount'] ?? intdiv((int) $it['amount'], 2);
                    return array_merge($it, ['auto_double' => true, 'base_amount' => $base, 'amount' => $base * 2]);
                }
                return array_merge($it, ['auto_double' => false, 'base_amount' => $it['amount']]);
            });
            $deptId = $data['charge_department_id'] ?? null;
            $deptName = $deptId ? Department::whereKey($deptId)->value('name') : null;
            $attrs = [
                'company_id'           => TransportCompany::idFor($user),
                'department_id'        => $user->department_id,
                'charge_department_id' => $deptId ?: null,
                // 旧・数字コード（サン・ブレーン様式の表示用）。共通 = 0、対応する部署が無ければ 0
                'department_code'      => $deptId ? (TransportExpense::codeForDepartmentName($deptName) ?? 0) : 0,
                'total_amount'         => $items->sum('amount'),
            ];
            if ($expense) {
                $expense->update($attrs);
            } else {
                $expense = TransportExpense::create($attrs + [
                    'user_id'       => $user->id,
                    'billing_month' => $data['billing_month'],
                    'billing_date'  => now()->toDateString(),
                    'status'        => TransportExpense::STATUS_DRAFT,
                ]);
            }

            $expense->items()->delete();
            foreach ($items as $i => $item) {
                $companionIds = $item['companion_ids'] ?? [];
                unset($item['companion_ids']);
                $item['via_stations'] = ($item['via_stations'] ?? []) ?: null;
                $created = $expense->items()->create(array_merge($item, ['sort_order' => $i]));
                foreach ($companionIds as $uid) {
                    $created->companions()->create(['user_id' => $uid]);
                }
            }
        });

        return $expense;
    }

    /** 申請する（印刷用 PDF を総務に提出できる状態にする） */
    public function submit(Request $request, TransportExpense $expense)
    {
        $this->authorizeOwner($request, $expense);
        if (!$expense->isEditableByApplicant()) {
            abort(422, '申請できる状態ではありません。');
        }
        if (!TransportDeadline::canApplicantEdit($request->user()->id, $expense->billing_month, $expense)) {
            abort(422, TransportDeadline::lockedMessage($expense->billing_month));
        }
        $expense->load('items');

        // 申請には明細の必須項目（発生日・行先・区間・金額）が揃っていること
        $errors = [];
        foreach (TransportExpenseExporter::sortedItems($expense) as $i => $it) {
            $n = $i + 1;
            if (!$it->occurrence_date) $errors[] = "{$n}行目の発生日が未入力です。";
            if (!$it->destination)     $errors[] = "{$n}行目の行先が未入力です。";
            if (!$it->station_from || !$it->station_to) $errors[] = "{$n}行目の区間が未入力です。";
            if ($it->amount <= 0)      $errors[] = "{$n}行目の金額が未入力です。";
        }
        if ($expense->items->isEmpty()) $errors[] = '明細がありません。';
        if ($errors) {
            return back()->withErrors(['submit' => implode(' ', $errors)]);
        }

        $this->workflow->apply($expense, $request->user(), 'submit');

        return redirect()->route($this->routeBase($request) . 'index', ['month' => $expense->billing_month])
            ->with('success', '交通費を申請しました。印刷した用紙を提出してください。');
    }

    /** 申請の取り下げ（申請済みのときだけ。下書きに戻る） */
    public function withdraw(Request $request, TransportExpense $expense)
    {
        $this->authorizeOwner($request, $expense);
        if ($expense->status !== TransportExpense::STATUS_SUBMITTED) {
            abort(422, '取り下げできる状態ではありません。');
        }
        // 期限後に取り下げると、下書きに戻って再申請できなくなるため、期限内（または解除）のときだけ
        if (!TransportDeadline::isOpen($request->user()->id, $expense->billing_month)) {
            abort(422, '期限（月末）を過ぎているため、取り下げできません（取り下げると、再申請できなくなります）。必要な場合は、総務・経理に相談してください。');
        }
        $this->workflow->apply($expense, $request->user(), 'withdraw');

        return redirect()->route($this->routeBase($request) . 'index', ['month' => $expense->billing_month])
            ->with('success', '申請を取り下げました。');
    }

    public function destroy(Request $request, TransportExpense $expense)
    {
        $this->authorizeOwner($request, $expense);
        if (!$expense->isEditableByApplicant()) {
            abort(422, '申請済み／受理済みの申請は削除できません。');
        }
        $month = $expense->billing_month;
        $expense->delete();

        return redirect()->route($this->routeBase($request) . 'index', ['month' => $month])
            ->with('success', '交通費の下書きを削除しました。');
    }

    // ---------------------------------------------------------------
    // 出力（本人、または総務・管理者のみ）
    // ---------------------------------------------------------------

    public function exportExcel(Request $request, TransportExpense $expense, TransportExpenseExporter $exporter)
    {
        $this->authorizeView($request, $expense);
        $expense->load(['user.department', 'items']);

        return response($exporter->excelContent($expense), 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . rawurlencode(TransportExpenseExporter::filename($expense, 'xlsx')) . '"',
        ]);
    }

    /** PDF（mode=inline でブラウザ内閲覧・印刷、省略でダウンロード） */
    public function exportPdf(Request $request, TransportExpense $expense, TransportExpenseExporter $exporter)
    {
        $this->authorizeView($request, $expense);
        $expense->load(['user.department', 'items']);

        $pdf      = $exporter->pdf($expense);
        $filename = TransportExpenseExporter::filename($expense, 'pdf');

        return $request->query('mode') === 'inline' ? $pdf->stream($filename) : $pdf->download($filename);
    }

    // ---------------------------------------------------------------

    /** ルート名の接頭辞（ユーザー用の入口 user.billing.transport.） */
    private function routeBase(Request $request): string
    {
        return 'user.billing.transport.';
    }

    private function authorizeOwner(Request $request, TransportExpense $expense): void
    {
        abort_unless($expense->user_id === $request->user()->id, 403);
    }

    private function authorizeView(Request $request, TransportExpense $expense): void
    {
        $u = $request->user();
        $expense->loadMissing('user');
        // 本人、または同じ会社の総務・経理・管理者（SuperAdmin は全社）
        abort_unless($expense->user_id === $u->id || TransportCompany::canManage($u, $expense), 403);
    }
}
