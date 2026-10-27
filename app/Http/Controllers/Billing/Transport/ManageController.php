<?php

namespace App\Http\Controllers\Billing\Transport;

use App\Http\Controllers\Controller;
use App\Models\TransportExpense;
use App\Models\TransportExpenseEvent;
use App\Models\TransportDeadlineExtension;
use App\Models\TransportExpenseItem;
use App\Models\User;
use App\Services\Billing\Transport\TransportCompany;
use App\Services\Billing\Transport\TransportDeadline;
use App\Services\Billing\Transport\TransportExpenseExporter;
use App\Services\Billing\Transport\TransportExpenseWorkflow;
use App\Services\Billing\Transport\TransportFareChecker;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 交通費の管理側（総務・経理＝Clerk、Admin、SuperAdmin）。
 * 会社ごとに絞り込み（SuperAdmin は切り替え中の会社。未選択なら全社）、受理・差戻し・請求済みを行う。
 */
class ManageController extends Controller
{
    /** 管理側に見せる状態（下書きは見せない） */
    private const VISIBLE_STATUSES = [
        TransportExpense::STATUS_SUBMITTED,
        TransportExpense::STATUS_APPROVED,
        TransportExpense::STATUS_RETURNED,
        TransportExpense::STATUS_BILLED,
    ];

    public function __construct(private TransportExpenseWorkflow $workflow, private TransportFareChecker $checker)
    {
    }

    public function index(Request $request): Response
    {
        $user = $this->manager($request);

        $months = collect(range(0, 11))->map(fn ($i) => now()->subMonths($i)->format('Y-m'))->values();
        $month  = $request->input('month');
        if (!is_string($month) || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = $months->first();
        }
        $status = $request->input('status');
        $status = in_array($status, self::VISIBLE_STATUSES, true) ? $status : null;

        $base = $this->scoped($user, TransportExpense::where('billing_month', $month)
            ->whereIn('status', self::VISIBLE_STATUSES));

        // 状態ごとの件数・金額（絞り込みの影響を受けない）
        $statusSummary = (clone $base)->get(['status', 'total_amount'])->groupBy('status')
            ->map(fn ($g) => ['count' => $g->count(), 'amount' => (int) $g->sum('total_amount')]);

        $expenses = (clone $base)->when($status, fn ($q) => $q->where('status', $status))
            ->with(['user:id,name', 'chargeDepartment:id,name', 'company:id,name', 'items'])
            ->withCount('items')
            ->get();

        // 運賃マスタとの照合・重複・休日・金額の異常を、申請ごとに数える（一覧では、前回比較は省く）
        $issues = $expenses->mapWithKeys(fn ($e) => [$e->id => $this->checker->check($e, false)['summary']]);

        // 請求先部門ごとにまとめる（共通は最後）
        $groups = $expenses->groupBy(fn ($e) => $e->chargeDepartment?->name ?? '共通')
            ->map(fn ($g, $name) => [
                'department_name' => $name,
                'total_amount'    => (int) $g->sum('total_amount'),
                'rows'            => $g->sortBy(fn ($e) => $e->user->name)->map(fn ($e) => [
                    'issues'       => ['diff' => $issues[$e->id]['diff'], 'warn' => $issues[$e->id]['warn_rows'], 'unverified' => $issues[$e->id]['unverified'] + $issues[$e->id]['none'], 'unchecked' => $issues[$e->id]['unchecked']],
                    'id'           => $e->id,
                    'user_name'    => $e->user->name,
                    'company_name' => $e->company?->name,
                    'status'       => $e->status,
                    'total_amount' => $e->total_amount,
                    'item_count'   => $e->items_count,
                    'submitted_at' => $e->submitted_at?->timezone('Asia/Tokyo')->format('m/d H:i'),
                ])->values(),
            ])
            ->sortBy(fn ($g) => $g['department_name'] === '共通' ? 1 : 0)->values();

        return Inertia::render('Billing/Transport/Manage/Index', [
            'routeBase'     => $this->routeBase($request),
            'month'         => $month,
            'months'        => $months,
            'status'        => $status,
            'statusLabels'  => TransportExpense::STATUS_LABELS,
            'statuses'      => self::VISIBLE_STATUSES,
            'statusSummary' => $statusSummary,
            'groups'        => $groups,
            'grandTotal'    => (int) $expenses->sum('total_amount'),
            // 期限（当月末日）の解除: 現在の解除の一覧と、許可する人・月の選択肢（期限を過ぎた月だけ）
            'deadlineEnforced' => TransportDeadline::enforced(),
            'deadlineRequests' => $this->deadlines($user, [TransportDeadlineExtension::STATUS_REQUESTED]),
            'deadlines'     => $this->deadlines($user, [TransportDeadlineExtension::STATUS_GRANTED, TransportDeadlineExtension::STATUS_REJECTED]),
            'applicants'    => $this->applicants($user),
            'pastMonths'    => $months->filter(fn ($m) => TransportDeadline::requestable($m))->values(),   // 期限後の申請を許可できる月（先月分まで）
        ]);
    }

    public function show(Request $request, TransportExpense $expense): Response
    {
        $user = $this->manager($request, $expense);
        abort_if($expense->status === TransportExpense::STATUS_DRAFT, 404);   // 下書きは管理側に見せない

        $expense->load(['user:id,name,department_id', 'user.department:id,name', 'company:id,name', 'chargeDepartment:id,name', 'reviewer:id,name', 'items', 'events.user:id,name']);
        $check = $this->checker->check($expense, true);   // 運賃マスタとの照合・重複・休日・金額の異常・前回との比較
        $companions = app(\App\Services\Billing\Transport\TransportCompanionMatcher::class)->forExpense($expense);   // 同行者の突き合わせ

        return Inertia::render('Billing/Transport/Manage/Show', [
            'routeBase'    => $this->routeBase($request),
            'statusLabels' => TransportExpense::STATUS_LABELS,
            'actionLabels' => TransportExpenseEvent::ACTION_LABELS,
            'purposes'     => TransportExpenseItem::PURPOSE_LABELS,
            'expense'      => [
                'id'              => $expense->id,
                'status'          => $expense->status,
                'billing_month'   => $expense->billing_month,
                'billing_date'    => $expense->billing_date->toDateString(),
                'total_amount'    => $expense->total_amount,
                'user_name'       => $expense->user->name,
                'applicant_dept'  => $expense->user->department?->name,
                'company_name'    => $expense->company?->name,
                'charge_dept'     => $expense->chargeDepartment?->name,
                'submitted_at'    => $expense->submitted_at?->timezone('Asia/Tokyo')->format('Y/m/d H:i'),
                'reviewer_name'   => $expense->reviewer?->name,
                'reviewed_at'     => $expense->reviewed_at?->timezone('Asia/Tokyo')->format('Y/m/d H:i'),
                'review_comment'  => $expense->review_comment,
                'billed_at'       => $expense->billed_at?->timezone('Asia/Tokyo')->format('Y/m/d H:i'),
                'check_summary'   => $check['summary'],
                // 申請者のコメント: 最新の申請（再申請）に添えられたもの
                'applicant_comment' => ($sub = $expense->events->where('action', 'submit')->sortByDesc('id')->first()) && $sub->comment
                    ? ['comment' => $sub->comment, 'at' => $sub->created_at->timezone('Asia/Tokyo')->format('Y/m/d H:i')] : null,
                'items'           => TransportExpenseExporter::sortedItems($expense)->map(fn ($it, $i) => [
                    'id'              => $it->id,
                    'check'           => $check['items'][$i],
                    'via_stations'    => $it->via_stations ?? [],
                    'companion_review' => $companions[$it->id] ?? ['companions' => [], 'named_by' => []],
                    'occurrence_date' => $it->occurrence_date?->toDateString(),
                    'destination'     => $it->destination,
                    'purpose_label'   => $it->purpose_label,
                    'station_from'    => $it->station_from,
                    'station_to'      => $it->station_to,
                    'fare_type'       => $it->fare_type,
                    'amount'          => $it->amount,
                    'base_amount'     => $it->auto_double ? $it->base_amount : null,   // 往復の片道金額（×2 のときだけ）
                ])->values(),
                'events'          => $expense->events->map(fn ($ev) => [
                    'id'         => $ev->id,
                    'action'     => $ev->action,
                    'user_name'  => $ev->user?->name,
                    'comment'    => $ev->comment,
                    'created_at' => $ev->created_at->timezone('Asia/Tokyo')->format('Y/m/d H:i'),
                ])->values(),
                // 今の状態で実行できる操作（ボタンの出し分け）
                'can'             => collect(['approve', 'return', 'bill', 'unapprove', 'unbill'])
                    ->mapWithKeys(fn ($a) => [$a => TransportExpenseWorkflow::can($expense, $a)])->all(),
            ],
        ]);
    }

    /** 行ごとの「確認済み」のチェックを付ける／外す */
    public function checkItem(Request $request, TransportExpense $expense, TransportExpenseItem $item)
    {
        $user = $this->manager($request, $expense);
        abort_unless($item->transport_expense_id === $expense->id, 404);
        abort_unless(in_array($expense->status, [TransportExpense::STATUS_SUBMITTED, TransportExpense::STATUS_APPROVED], true), 422, 'この状態では、確認のチェックは変更できません。');
        $checked = $request->boolean('checked');
        $item->update(['checked_at' => $checked ? now() : null, 'checked_by' => $checked ? $user->id : null]);

        return back();
    }

    /**
     * 検算の結果、台帳の運賃と一致した行を、まとめて「確認済み」にする。
     * 重複・休日・金額の異常などの注意がある行は、勝手に確認済みにせず、残す。
     */
    public function checkMatched(Request $request, TransportExpense $expense)
    {
        $user = $this->manager($request, $expense);
        abort_unless(in_array($expense->status, [TransportExpense::STATUS_SUBMITTED, TransportExpense::STATUS_APPROVED], true), 422, 'この状態では、確認のチェックは変更できません。');

        $result = $this->checker->check($expense, false);
        $matched = collect($result['items'])->filter(fn ($r) => !$r['checked'] && $r['fare']['state'] === 'match');
        $ok = $matched->filter(fn ($r) => !collect($r['flags'])->contains(fn ($f) => $f['level'] === 'warn'));
        if ($ok->isNotEmpty()) {
            $expense->items()->whereIn('id', $ok->pluck('id'))->update(['checked_at' => now(), 'checked_by' => $user->id]);
        }
        $skipped = $matched->count() - $ok->count();

        return back()->with('success', $ok->count() . '行を、台帳と一致したので確認済みにしました。'
            . ($skipped ? "（注意のある {$skipped} 行は、確認が必要なので残してあります）" : ''));
    }

    /** 全ての行を「確認済み」にする（未確認の行だけ） */
    public function checkAll(Request $request, TransportExpense $expense)
    {
        $user = $this->manager($request, $expense);
        abort_unless(in_array($expense->status, [TransportExpense::STATUS_SUBMITTED, TransportExpense::STATUS_APPROVED], true), 422, 'この状態では、確認のチェックは変更できません。');
        $expense->items()->whereNull('checked_at')->update(['checked_at' => now(), 'checked_by' => $user->id]);

        return back();
    }

    public function approve(Request $request, TransportExpense $expense)
    {
        return $this->act($request, $expense, 'approve', '受理しました。');
    }

    public function returnBack(Request $request, TransportExpense $expense)
    {
        return $this->act($request, $expense, 'return', '差戻ししました。申請者に通知しました。');
    }

    public function bill(Request $request, TransportExpense $expense)
    {
        return $this->act($request, $expense, 'bill', '請求済みにしました。');
    }

    public function unapprove(Request $request, TransportExpense $expense)
    {
        return $this->act($request, $expense, 'unapprove', '受理を取り消しました（申請済みに戻りました）。');
    }

    public function unbill(Request $request, TransportExpense $expense)
    {
        return $this->act($request, $expense, 'unbill', '請求済みを取り消しました（受理に戻りました）。');
    }

    /** 月次集計 CSV（UTF-8・BOM つき。Excel でそのまま開ける）。部門別・個人別。 */
    public function summary(Request $request)
    {
        $user = $this->manager($request);
        $month = $request->input('month');
        abort_unless(is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month), 422, '対象月を指定してください。');

        $expenses = $this->scoped($user, TransportExpense::where('billing_month', $month)
            ->whereIn('status', self::VISIBLE_STATUSES))
            ->with(['user:id,name', 'chargeDepartment:id,name', 'company:id,name'])->withCount('items')->get()
            ->sortBy(fn ($e) => [($e->chargeDepartment?->name ?? '共通') === '共通' ? 1 : 0, $e->chargeDepartment?->name ?? '', $e->user->name])
            ->values();

        $filename = '交通費集計_' . str_replace('-', '', $month) . '.csv';
        return response()->streamDownload(function () use ($expenses, $month) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['対象月', '会社', '請求先部門', '氏名', '状態', '件数', '合計金額']);
            $byDept = [];
            foreach ($expenses as $e) {
                $dept = $e->chargeDepartment?->name ?? '共通';
                fputcsv($out, [$month, $e->company?->name, $dept, $e->user->name,
                    TransportExpense::STATUS_LABELS[$e->status] ?? $e->status, $e->items_count, $e->total_amount]);
                $byDept[$dept] = ($byDept[$dept] ?? 0) + $e->total_amount;
            }
            fputcsv($out, []);
            fputcsv($out, ['部門別合計']);
            foreach ($byDept as $dept => $sum) {
                fputcsv($out, ['', '', $dept, '', '', '', $sum]);
            }
            fputcsv($out, ['', '', '全体', '', '', '', array_sum($byDept)]);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ---------------------------------------------------------------

    private function act(Request $request, TransportExpense $expense, string $action, string $successMessage)
    {
        $user = $this->manager($request, $expense);
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:1000']]);

        $this->workflow->apply($expense, $user, $action, $data['comment'] ?? null);

        return back()->with('success', $successMessage);
    }

    /** 期限の解除の一覧（この会社・直近 12 か月） */
    private function deadlines($user, array $statuses): array
    {
        $companyId = TransportCompany::manageScopeId($user);
        return TransportDeadlineExtension::with(['user:id,name', 'grantor:id,name'])
            ->whereIn('status', $statuses)
            ->where('billing_month', '>=', now()->subMonths(12)->format('Y-m'))
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->orderByDesc('billing_month')->orderByDesc('id')->get()
            ->map(fn ($e) => [
                'id' => $e->id, 'user_name' => $e->user?->name, 'billing_month' => $e->billing_month, 'reason' => $e->reason, 'status' => $e->status,
                'decision_comment' => $e->decision_comment, 'granted_by' => $e->grantor?->name,
                'requested_at' => $e->requested_at?->timezone('Asia/Tokyo')->format('Y/m/d H:i'),
                'created_at' => ($e->decided_at ?? $e->created_at)->timezone('Asia/Tokyo')->format('Y/m/d H:i'),
            ])->all();
    }

    /** 期限の解除の対象にできる人（この会社の人） */
    private function applicants($user): array
    {
        $companyId = TransportCompany::manageScopeId($user);
        return User::query()
            ->when($companyId, fn ($q) => $q->where(fn ($w) => $w->where('company_id', $companyId)->orWhere('home_company_id', $companyId)))
            ->where(fn ($q) => $q->whereNull('is_ghost')->orWhere('is_ghost', false))
            ->orderBy('name')->limit(500)->get(['id', 'name'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->all();
    }

    /** 管理権限の確認（申請を指定したときは、同じ会社かも確認） */
    private function manager(Request $request, ?TransportExpense $expense = null)
    {
        $user = $request->user();
        abort_unless(TransportCompany::isManager($user), 403);
        if ($expense) {
            $expense->loadMissing('user');
            abort_unless(TransportCompany::canManage($user, $expense), 403);
        }
        return $user;
    }

    /** 会社の絞り込み（SuperAdmin は切り替え中の会社。未選択なら全社） */
    private function scoped($user, $query)
    {
        $companyId = TransportCompany::manageScopeId($user);
        return $companyId ? $query->where('company_id', $companyId) : $query;
    }

    /** ルート名の接頭辞（総務・経理＝Clerk の画面。SuperAdmin も、同じ画面を使う） */
    private function routeBase(Request $request): string
    {
        return 'clerk.billing.transport.';
    }
}
