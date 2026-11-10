<?php

namespace App\Http\Controllers\SunA\Sales;

use App\Http\Controllers\Controller;
use App\Services\SunA\Sales\ClientGroupException;
use App\Services\SunA\Sales\ClientGroupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * 売上レポートの名寄せ設定（サンエー印刷。SUNASALES_PLAN1.md §17）。
 * 画面とデータ取得は suna_sales_report:view（閲覧できる人）、変更は :import（取込できる人）で保護する。
 */
class ClientGroupController extends Controller
{
    public function __construct(private ClientGroupService $service)
    {
    }

    public function index()
    {
        return Inertia::render('SunA/Sales/ClientGroups');
    }

    public function data()
    {
        return response()->json($this->service->overview());
    }

    public function clients(Request $request)
    {
        $data = $request->validate(['keyword' => ['nullable', 'string', 'max:100'], 'limit' => ['nullable', 'integer', 'min:1', 'max:50']]);

        return response()->json(['clients' => $this->service->searchUngrouped((string) ($data['keyword'] ?? ''), (int) ($data['limit'] ?? 20))]);
    }

    public function events(Request $request)
    {
        return response()->json($this->service->events((int) $request->query('page', 1)));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'client_keys' => ['required', 'array', 'min:2', 'max:100'], 'client_keys.*' => ['required', 'string', 'max:255']]);

        return $this->run(fn () => ['id' => $this->service->create($data['name'], $data['client_keys'], (int) Auth::id())->id], 201);
    }

    public function update(Request $request, int $group)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        return $this->run(fn () => ['id' => $this->service->rename($group, $data['name'], (int) Auth::id())->id]);
    }

    public function addMembers(Request $request, int $group)
    {
        $data = $request->validate(['client_keys' => ['required', 'array', 'min:1', 'max:100'], 'client_keys.*' => ['required', 'string', 'max:255']]);

        return $this->run(fn () => ['id' => $this->service->addMembers($group, $data['client_keys'], (int) Auth::id())->id]);
    }

    public function removeMember(Request $request, int $group)
    {
        $data = $request->validate(['client_key' => ['required', 'string', 'max:255']]);

        return $this->run(fn () => $this->service->removeMember($group, $data['client_key'], (int) Auth::id()));
    }

    public function destroy(int $group)
    {
        return $this->run(function () use ($group) {
            $this->service->dissolve($group, (int) Auth::id());

            return ['dissolved' => true];
        });
    }

    /** @param  callable(): array<string,mixed>  $action */
    private function run(callable $action, int $status = 200)
    {
        try {
            return response()->json($action(), $status);
        } catch (ClientGroupException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
