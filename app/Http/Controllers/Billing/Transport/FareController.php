<?php

namespace App\Http\Controllers\Billing\Transport;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\Transport\SaveExpenseRequest;
use App\Models\TransportExpense;
use App\Models\TransportExpenseItem;
use App\Models\TransportFare;
use App\Services\Billing\Transport\TransportCompany;
use App\Services\Billing\Transport\TransportFareChecker;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** 運賃マスタ（総務・経理が管理する、経路と運賃の台帳。会社ごと）と、検算ボタン */
class FareController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->manager($request);
        $companyId = $this->companyId($user);

        $base = TransportFare::where('company_id', $companyId);
        $counts = (clone $base)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        // 画面は「登録済み（検査済み）」と「未登録の候補（未検査）」の 2 つ。指定が無ければ登録済み
        $status = in_array($request->input('status'), [TransportFare::STATUS_VERIFIED, TransportFare::STATUS_UNVERIFIED], true)
            ? $request->input('status') : TransportFare::STATUS_VERIFIED;
        $q = trim((string) $request->input('q', ''));

        $fares = (clone $base)
            ->when($status, fn ($qq) => $qq->where('status', $status))
            ->when($q !== '', fn ($qq) => $qq->where(fn ($w) => $w->where('station_a', 'like', "%{$q}%")->orWhere('station_b', 'like', "%{$q}%")
                ->orWhere('note', 'like', "%{$q}%")))
            ->with('checker:id,name')
            ->orderByDesc('use_count')->orderBy('station_a')
            ->limit(1000)->get()
            ->map(fn (TransportFare $f) => [
                'id' => $f->id, 'station_a' => $f->station_a, 'station_b' => $f->station_b,
                'fare_ic' => $f->fare_ic, 'fare_ticket' => $f->fare_ticket, 'status' => $f->status,
                'reported_fare' => $f->reported_fare, 'reported_fare_type' => $f->reported_fare_type,
                'note' => $f->note, 'use_count' => $f->use_count,
                'last_used_at' => $f->last_used_at?->timezone('Asia/Tokyo')->format('Y/m/d'),
                'checked_by' => $f->checker?->name, 'checked_at' => $f->checked_at?->timezone('Asia/Tokyo')->format('Y/m/d'),
                'recheck' => $f->needsRecheck(), 'source' => $f->source,
            ]);

        return Inertia::render('Billing/Transport/Fares/Index', [
            'routeBase'    => $this->routeBase($request),
            'statusLabels' => TransportFare::STATUS_LABELS,
            'counts'       => ['all' => $counts->sum(), 'verified' => (int) ($counts['verified'] ?? 0), 'unverified' => (int) ($counts['unverified'] ?? 0), 'wanted' => (int) ($counts['wanted'] ?? 0)],
            'status'       => $status,
            'q'            => $q,
            'fares'        => $fares,
        ]);
    }

    public function store(Request $request)
    {
        $user = $this->manager($request);
        $data = $this->validated($request);
        $companyId = $this->companyId($user);
        $key = TransportFare::routeKey($data['station_a'], $data['station_b']);

        // 同じ経路（方向を問わない）が既にあれば、その内容を更新する
        $fare = TransportFare::firstOrNew(['company_id' => $companyId, 'route_key' => $key]);
        $this->fillAndSave($fare, $data, $user, $companyId, $key);

        return back()->with('success', '「' . $data['station_a'] . ' － ' . $data['station_b'] . '」を登録しました。');
    }

    public function update(Request $request, TransportFare $fare)
    {
        $user = $this->manager($request);
        abort_unless((int) $fare->company_id === (int) $this->companyId($user), 403);
        $data = $this->validated($request);

        $key = TransportFare::routeKey($data['station_a'], $data['station_b']);
        if ($key !== $fare->route_key && TransportFare::where('company_id', $fare->company_id)->where('route_key', $key)->exists()) {
            return back()->withErrors(['station_a' => '同じ経路（出発と到着を入れ替えたものを含む）が、すでに登録されています。']);
        }
        $this->fillAndSave($fare, $data, $user, (int) $fare->company_id, $key);

        return back()->with('success', '運賃マスタを更新しました。');
    }

    public function destroy(Request $request, TransportFare $fare)
    {
        $user = $this->manager($request);
        abort_unless((int) $fare->company_id === (int) $this->companyId($user), 403);
        $fare->delete();

        return back()->with('success', '運賃マスタから削除しました。');
    }

    /**
     * 詳細画面の行から、運賃マスタへ登録する。
     *  - register: 台帳に無ければ「未検査」で登録（申請の金額は参考値として残す）
     *  - verify:   申請の金額を、この経路の運賃として「検査済み」にする（IC / 切符は、その行の種別）
     */
    public function fromItem(Request $request, TransportExpense $expense, TransportExpenseItem $item)
    {
        $user = $this->manager($request);
        $expense->loadMissing('user');
        abort_unless(TransportCompany::canManage($user, $expense) && $item->transport_expense_id === $expense->id, 403);
        $action = $request->validate(['action' => ['required', Rule::in(['register', 'verify'])]])['action'];

        $a = trim((string) $item->station_from);
        $b = trim((string) $item->station_to);
        if ($a === '' || $b === '' || TransportFare::normalizeStation($a) === TransportFare::normalizeStation($b)) {
            return back()->withErrors(['fare' => '区間が正しく入力されていないため、登録できません。']);
        }
        $companyId = (int) ($expense->company_id ?? $this->companyId($user));
        $key = TransportFare::routeKey($a, $b);
        $fare = TransportFare::firstOrNew(['company_id' => $companyId, 'route_key' => $key]);
        if (!$fare->exists) {
            $fare->fill(['station_a' => $a, 'station_b' => $b, 'status' => TransportFare::STATUS_UNVERIFIED, 'source' => 'manual']);
        }
        $one = TransportFareChecker::oneWay($item);

        if ($action === 'verify') {
            if ($one === null) {
                return back()->withErrors(['fare' => '片道の金額が決められないため（往復の金額が奇数など）、この金額では検査済みにできません。運賃マスタで直接入力してください。']);
            }
            $col = $item->fare_type === 'ticket' ? 'fare_ticket' : 'fare_ic';
            $fare->{$col} = $one;
            $fare->status = TransportFare::STATUS_VERIFIED;
            $fare->checked_by = $user->id;
            $fare->checked_at = now();
            $fare->save();
            return back()->with('success', "「{$a} － {$b}」を、片道 " . number_format($one) . '円（' . ($item->fare_type === 'ticket' ? '切符' : 'IC') . '）で検査済みにしました。');
        }

        if ($one !== null && $fare->status !== TransportFare::STATUS_VERIFIED) {
            $fare->reported_fare = $one;
            $fare->reported_fare_type = $item->fare_type;
        }
        $fare->save();

        return back()->with('success', "「{$a} － {$b}」を、運賃マスタに登録しました（" . TransportFare::STATUS_LABELS[$fare->status] . '）。');
    }

    // ---------------------------------------------------------------

    private function fillAndSave(TransportFare $fare, array $data, $user, int $companyId, string $key): void
    {
        $becameVerified = $data['status'] === TransportFare::STATUS_VERIFIED
            && ($fare->status !== TransportFare::STATUS_VERIFIED || $fare->fare_ic !== $data['fare_ic'] || $fare->fare_ticket !== $data['fare_ticket']);

        $fare->fill([
            'company_id' => $companyId, 'route_key' => $key, 'station_a' => $data['station_a'], 'station_b' => $data['station_b'],
            'fare_ic' => $data['fare_ic'], 'fare_ticket' => $data['fare_ticket'], 'status' => $data['status'],
        ]);
        if (array_key_exists('note', $data)) {   // メモは、画面から送られたときだけ更新する（簡単な登録では送らない）
            $fare->note = $data['note'];
        }
        if (!$fare->exists) {
            $fare->source = 'manual';
        }
        if ($becameVerified) {                          // 検査済みにした／検査済みの運賃を変えたときは、検査した人と日付を更新
            $fare->checked_by = $user->id;
            $fare->checked_at = now();
        } elseif ($data['status'] !== TransportFare::STATUS_VERIFIED) {
            $fare->checked_by = null;
            $fare->checked_at = null;
        }
        $fare->save();
    }

    private function validated(Request $request): array
    {
        $request->merge([
            'station_a'   => SaveExpenseRequest::zenkaku($request->input('station_a')),
            'station_b'   => SaveExpenseRequest::zenkaku($request->input('station_b')),
            'note'        => SaveExpenseRequest::zenkaku($request->input('note')),
            'fare_ic'     => $request->filled('fare_ic') ? SaveExpenseRequest::yen($request->input('fare_ic')) : null,
            'fare_ticket' => $request->filled('fare_ticket') ? SaveExpenseRequest::yen($request->input('fare_ticket')) : null,
        ]);

        $data = $request->validate([
            'station_a'   => ['required', 'string', 'max:100'],
            'station_b'   => ['required', 'string', 'max:100', 'different:station_a'],
            'fare_ic'     => ['nullable', 'integer', 'min:0', 'max:99999'],
            'fare_ticket' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'status'      => ['nullable', Rule::in(array_keys(TransportFare::STATUS_LABELS))],
            'note'        => ['nullable', 'string', 'max:255'],
        ], [
            'station_a.required' => '出発駅を入力してください。',
            'station_b.required' => '到着駅を入力してください。',
            'station_b.different' => '出発と到着に、同じ駅は指定できません。',
            'fare_ic.integer' => 'IC の運賃は数字で入力してください。',
            'fare_ticket.integer' => '切符の運賃は数字で入力してください。',
        ]);

        // 状態を指定しなければ「登録」: 運賃（IC か切符）が必要で、登録済み（検査済み）になる。状態を明示したときだけ、運賃なしの「未検査」なども許す
        $hasFare = ($data['fare_ic'] ?? null) !== null || ($data['fare_ticket'] ?? null) !== null;
        if (empty($data['status'])) {
            if (!$hasFare) {
                abort(back()->withErrors(['fare_ic' => '運賃（IC か切符のどちらか）を入力してください。']));
            }
            $data['status'] = TransportFare::STATUS_VERIFIED;
        } elseif ($data['status'] === TransportFare::STATUS_VERIFIED && !$hasFare) {
            abort(back()->withErrors(['fare_ic' => '運賃（IC か切符のどちらか）を入力してください。']));
        }
        $data['fare_ic'] = $data['fare_ic'] ?? null;
        $data['fare_ticket'] = $data['fare_ticket'] ?? null;

        return $data;
    }

    private function manager(Request $request)
    {
        $user = $request->user();
        abort_unless(TransportCompany::isManager($user), 403);
        return $user;
    }

    /** 台帳の会社（SuperAdmin は、切り替え中の会社。未選択なら、自分の所属会社） */
    private function companyId($user): int
    {
        $id = TransportCompany::manageScopeId($user) ?? TransportCompany::idFor($user);
        abort_unless($id, 403, '会社が決まりません。会社を選択してください。');
        return (int) $id;
    }

    /** ルート名の接頭辞（総務・経理＝Clerk の画面。SuperAdmin も、同じ画面を使う） */
    private function routeBase(Request $request): string
    {
        return 'clerk.billing.transport.';
    }
}
