<?php

namespace App\Services\SalesAnalysis;

use App\Models\Sales\SalesClientGroup;
use App\Models\Sales\SalesClientGroupMember;
use App\Models\Sales\SalesOrder;
use Illuminate\Database\Eloquent\Builder;

/**
 * 得意先統合設定画面用の集計・CRUD補助（2026-09-04 PLAN1.md「Phase 7-0 詳細設計」）。
 * 正規化候補の提示までを行い、確定（グループ作成・メンバー追加）は常にユーザーの手動操作のみで行う。
 * 会社別データ分離（2026-09-05）: 呼び出し側は先に必ず`forCompany()`を呼ぶ
 * （SalesQueryServiceと同じ設計。詳細はそちらのコメント参照）。
 */
class ClientGroupService
{
    private ?int $companyId = null;

    public function forCompany(int $companyId): self
    {
        $this->companyId = $companyId;

        return $this;
    }

    private function requireCompanyId(): int
    {
        if ($this->companyId === null) {
            throw new \LogicException('ClientGroupService: forCompany()が呼ばれる前にメソッドが実行されました。');
        }

        return $this->companyId;
    }

    /** 部署を問わず、自社の有効データに含まれる得意先名の受注を対象にする基底クエリ */
    private function allActiveOrdersQuery(): Builder
    {
        $companyId = $this->requireCompanyId();

        return SalesOrder::query()
            ->join('sales_active_months', function ($join) use ($companyId) {
                $join->on('sales_orders.sales_import_id', '=', 'sales_active_months.sales_import_id')
                    ->on('sales_orders.sales_year', '=', 'sales_active_months.sales_year')
                    ->on('sales_orders.sales_month', '=', 'sales_active_months.sales_month')
                    ->where('sales_active_months.company_id', $companyId)
                    ->whereIn('sales_active_months.department_key', SalesDepartments::enabledKeysFor($companyId));
            });
    }

    /** 既存グループに未所属の得意先名（有効データに実在するもののみ）。件数・直近取引額付き */
    public function unassignedClients(): array
    {
        $groupedNames = SalesClientGroupMember::where('company_id', $this->requireCompanyId())->pluck('client_name');

        $rows = $this->allActiveOrdersQuery()
            ->whereNotNull('sales_orders.client_name')
            ->get(['sales_orders.client_name', 'sales_orders.order_amount', 'sales_orders.plate_date']);

        return $rows->groupBy('client_name')
            ->reject(fn ($group, $name) => $groupedNames->contains($name))
            ->map(function ($group, $name) {
                $latest = $group->sortByDesc('plate_date')->first();

                return [
                    'client_name' => $name,
                    'order_count' => $group->count(),
                    'latest_amount' => (float) $latest->order_amount,
                    'latest_order_date' => $latest->plate_date?->format('Y-m-d'),
                ];
            })
            ->sortBy('client_name')
            ->values()
            ->all();
    }

    /** 未所属の得意先名のうち、正規化結果が一致する（=表記ゆれの可能性がある）ものを候補として提示する */
    public function candidates(): array
    {
        $unassignedNames = collect($this->unassignedClients())->pluck('client_name');

        return $unassignedNames
            ->groupBy(fn ($name) => ClientNameNormalizer::normalize($name))
            ->filter(fn ($group) => $group->count() > 1)
            ->map(fn ($group, $normalized) => [
                'normalized_name' => $normalized,
                'client_names' => $group->values()->all(),
            ])
            ->values()
            ->all();
    }

    public function groups(): array
    {
        return SalesClientGroup::where('company_id', $this->requireCompanyId())->with('members')->orderBy('name')->get()
            ->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'members' => $g->members->map(fn ($m) => [
                    'id' => $m->id,
                    'client_name' => $m->client_name,
                    'normalized_name' => $m->normalized_name,
                ])->all(),
            ])
            ->all();
    }

    /**
     * 指定した得意先名群を1グループとして統合した場合の売上影響をプレビューする（DBへは書き込まない）。
     */
    public function preview(array $clientNames): array
    {
        $rows = $this->allActiveOrdersQuery()
            ->whereIn('sales_orders.client_name', $clientNames)
            ->get(['sales_orders.client_name', 'sales_orders.order_amount', 'sales_active_months.department_key']);

        $perName = $rows->groupBy('client_name')
            ->map(fn ($group, $name) => [
                'client_name' => $name,
                'amount' => (float) $group->sum('order_amount'),
                'order_count' => $group->count(),
            ])
            ->values()
            ->all();

        return [
            'client_names' => $clientNames,
            'total_amount' => (float) $rows->sum('order_amount'),
            'order_count' => $rows->count(),
            'departments' => $rows->pluck('department_key')->unique()->values()->all(),
            'per_name' => $perName,
        ];
    }
}
