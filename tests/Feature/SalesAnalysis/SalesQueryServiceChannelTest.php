<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Company;
use App\Models\Sales\SalesActiveMonth;
use App\Models\Sales\SalesImport;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderDetail;
use App\Services\SalesAnalysis\SalesImportService;
use App\Services\SalesAnalysis\SalesQueryService;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

/**
 * Phase 20: SalesQueryService の受注経路（standard/direct）内訳・登録状態伝播を検証する。
 * Import HTTPフローは SalesOrderChannelImportTest でカバー済みのため、ここではDBへ直接
 * 架空データを投入して集計ロジックだけを検証する。
 */
class SalesQueryServiceChannelTest extends TestCase
{
    use RefreshesSalesDatabase;

    private static int $importSeq = 0;

    private function markCompanyAsSunbrain(): void
    {
        Company::find($this->salesTestCompanyId())->update(['code' => 'SUNBRAIN']);
    }

    /**
     * @param  array<int, array{order_number:string, client_name:string, amount:float}>  $orders
     */
    private function seedMonth(string $dept, int $year, int $month, string $channel, array $orders): SalesImport
    {
        self::$importSeq++;

        $import = SalesImport::create([
            'company_id' => $this->salesTestCompanyId(),
            'department_key' => $dept,
            'order_channel' => $channel,
            'source_type' => 'monthly',
            'source_year' => $year,
            'source_month' => $month,
            'version' => 1,
            'original_filename' => 'seed.xlsx',
            'file_sha256' => hash('sha256', "seed-{$dept}-{$channel}-{$year}-{$month}-" . self::$importSeq),
            'status' => 'completed',
            'imported_by' => 1,
            'imported_at' => now(),
            'order_count' => count($orders),
            'detail_count' => count($orders),
            'total_amount' => array_sum(array_column($orders, 'amount')),
        ]);

        foreach ($orders as $o) {
            $order = SalesOrder::create([
                'sales_import_id' => $import->id,
                'order_number' => $o['order_number'],
                'client_name' => $o['client_name'],
                'product_name' => $o['product_name'] ?? '商品',
                'plate_date' => sprintf('%04d-%02d-15', $year, $month),
                'sales_year' => $year,
                'sales_month' => $month,
                'order_amount' => $o['amount'],
            ]);

            SalesOrderDetail::create([
                'sales_order_id' => $order->id,
                'source_row_number' => 1,
                'client_name' => $o['client_name'],
                'product_name' => $o['product_name'] ?? '商品',
                'category' => $o['category'] ?? '組版',
                'item_name' => $o['item_name'] ?? '新規',
                'format_size' => 'A4',
                'color_count' => 1,
                'quantity' => 1,
                'unit_price' => $o['amount'],
                'line_amount' => $o['amount'],
                'order_amount_component' => $o['amount'],
                'plate_date' => sprintf('%04d-%02d-15', $year, $month),
            ]);
        }

        SalesActiveMonth::updateOrCreate(
            ['company_id' => $this->salesTestCompanyId(), 'department_key' => $dept, 'order_channel' => $channel, 'sales_year' => $year, 'sales_month' => $month],
            ['sales_import_id' => $import->id, 'activated_by' => 1, 'activated_at' => now()]
        );

        return $import;
    }

    private function service(): SalesQueryService
    {
        return (new SalesQueryService(new SalesImportService()))->forCompany($this->salesTestCompanyId());
    }

    public function test_registration_state_progresses_from_no_data_to_partial_to_complete()
    {
        $this->markCompanyAsSunbrain();

        $this->assertNull($this->service()->monthlyTotal('planning', 2026, 8));

        $this->seedMonth('planning', 2026, 8, 'standard', [
            ['order_number' => 'S1', 'client_name' => 'A社', 'amount' => 1000],
        ]);
        $partial = $this->service()->monthlyTotal('planning', 2026, 8);
        $this->assertSame('partial', $partial['registration']);
        $this->assertSame(1000.0, $partial['standard_amount']);
        $this->assertSame(0.0, $partial['direct_amount']);
        $this->assertSame(1000.0, $partial['total_amount']);

        $this->seedMonth('planning', 2026, 8, 'direct', [
            ['order_number' => 'D1', 'client_name' => 'B社', 'amount' => 500],
        ]);
        $complete = $this->service()->monthlyTotal('planning', 2026, 8);
        $this->assertSame('complete', $complete['registration']);
        $this->assertSame(1000.0, $complete['standard_amount']);
        $this->assertSame(500.0, $complete['direct_amount']);
        $this->assertSame(1500.0, $complete['total_amount']);
        $this->assertEqualsWithDelta(33.3, $complete['direct_share'], 0.1);
    }

    public function test_non_sunbrain_company_is_always_complete_when_any_data_exists()
    {
        // 会社コードをSUNBRAIN以外のまま（既定）。既存データはすべてstandardなので
        // 「1件でもあればcomplete」という従来どおりの判定になる。
        $this->seedMonth('planning', 2026, 8, 'standard', [
            ['order_number' => 'S1', 'client_name' => 'A社', 'amount' => 1000],
        ]);

        $total = $this->service()->monthlyTotal('planning', 2026, 8);
        $this->assertSame('complete', $total['registration']);
        $this->assertSame(1000.0, $total['standard_amount']);
        $this->assertSame(0.0, $total['direct_amount']);
    }

    public function test_registration_status_by_department_reports_partial_and_complete_months()
    {
        $this->markCompanyAsSunbrain();

        $this->seedMonth('planning', 2026, 1, 'standard', [['order_number' => 'A1', 'client_name' => 'X', 'amount' => 100]]);
        $this->seedMonth('planning', 2026, 2, 'standard', [['order_number' => 'A2', 'client_name' => 'X', 'amount' => 100]]);
        $this->seedMonth('planning', 2026, 2, 'direct', [['order_number' => 'A3', 'client_name' => 'X', 'amount' => 50]]);

        $years = $this->service()->registrationStatusByDepartment('planning');
        $year2026 = collect($years)->firstWhere('year', 2026);
        $this->assertTrue($year2026['supports_order_channels']);
        $this->assertTrue($year2026['has_any_partial']);

        $monthsByNumber = collect($year2026['months'])->keyBy('month');
        $this->assertSame('partial', $monthsByNumber[1]['registration']);
        $this->assertSame('complete', $monthsByNumber[2]['registration']);
        $this->assertSame(50.0, $monthsByNumber[2]['direct_amount']);
        $this->assertSame('no_data', $monthsByNumber[3]['registration']);
    }

    public function test_client_ranking_reports_channel_breakdown_per_client()
    {
        $this->markCompanyAsSunbrain();

        $this->seedMonth('planning', 2026, 8, 'standard', [
            ['order_number' => 'S1', 'client_name' => 'A社', 'amount' => 1000],
        ]);
        $this->seedMonth('planning', 2026, 8, 'direct', [
            ['order_number' => 'D1', 'client_name' => 'A社', 'amount' => 300],
            ['order_number' => 'D2', 'client_name' => 'B社', 'amount' => 200],
        ]);

        $ranking = $this->service()->clientRanking('planning', 2026, 8);

        $this->assertSame('complete', $ranking['registration']);
        $this->assertSame(1000.0, $ranking['standard_amount']);
        $this->assertSame(500.0, $ranking['direct_amount']);

        $byName = collect($ranking['ranking'])->keyBy('name');
        $this->assertSame(1000.0, $byName['A社']['standard_amount']);
        $this->assertSame(300.0, $byName['A社']['direct_amount']);
        $this->assertSame(1300.0, $byName['A社']['amount']);
        $this->assertSame(0.0, $byName['B社']['standard_amount']);
        $this->assertSame(200.0, $byName['B社']['direct_amount']);
    }

    public function test_annual_summary_sums_channel_breakdown_across_months()
    {
        $this->markCompanyAsSunbrain();

        $this->seedMonth('planning', 2026, 1, 'standard', [['order_number' => 'A1', 'client_name' => 'X', 'amount' => 1000]]);
        $this->seedMonth('planning', 2026, 1, 'direct', [['order_number' => 'A2', 'client_name' => 'X', 'amount' => 200]]);
        $this->seedMonth('planning', 2026, 2, 'standard', [['order_number' => 'A3', 'client_name' => 'X', 'amount' => 500]]);

        $summary = $this->service()->annualSummary('planning', 2026);

        $this->assertTrue($summary['supports_order_channels']);
        $this->assertSame(1500.0, $summary['kpi']['standard_amount']);
        $this->assertSame(200.0, $summary['kpi']['direct_amount']);
        $this->assertSame(1700.0, $summary['kpi']['period_amount']);

        $monthlyByNumber = collect($summary['monthly'])->keyBy('month');
        $this->assertSame('complete', $monthlyByNumber[1]['registration']);
        $this->assertSame('partial', $monthlyByNumber[2]['registration']);
    }

    /**
     * Codexレビュー指摘（2026-09-07）: department_key='all'のとき、部署ごとの経路完全性ではなく
     * 「月全体でどこかにstandard・どこかにdirectがあればcomplete」と誤判定していた不具合の回帰。
     * 企画は両経路そろっているが制作はstandardのみの場合、全体はpartialでなければならない。
     */
    public function test_annual_summary_all_departments_marks_partial_when_any_reporting_department_is_channel_incomplete()
    {
        $this->markCompanyAsSunbrain();

        $this->seedMonth('planning', 2026, 3, 'standard', [['order_number' => 'P1', 'client_name' => 'X', 'amount' => 100]]);
        $this->seedMonth('planning', 2026, 3, 'direct', [['order_number' => 'P2', 'client_name' => 'X', 'amount' => 50]]);
        $this->seedMonth('production', 2026, 3, 'standard', [['order_number' => 'S1', 'client_name' => 'Y', 'amount' => 200]]);
        // ondemandはこの月は未登録（部署カバレッジの問題であり、経路完全性の判定には含めない）

        $summary = $this->service()->annualSummary('all', 2026);
        $march = collect($summary['monthly'])->firstWhere('month', 3);

        $this->assertSame('partial', $march['registration']);
        $this->assertFalse($march['coverage']['is_complete']);
    }

    /** 全部署が登録済みかつ全部署が両経路そろっている場合のみcompleteになることの確認 */
    public function test_annual_summary_all_departments_is_complete_when_every_reporting_department_has_both_channels()
    {
        $this->markCompanyAsSunbrain();

        $this->seedMonth('planning', 2026, 4, 'standard', [['order_number' => 'P1', 'client_name' => 'X', 'amount' => 100]]);
        $this->seedMonth('planning', 2026, 4, 'direct', [['order_number' => 'P2', 'client_name' => 'X', 'amount' => 50]]);
        // production/ondemandはこの月は未登録

        $summary = $this->service()->annualSummary('all', 2026);
        $april = collect($summary['monthly'])->firstWhere('month', 4);

        $this->assertSame('complete', $april['registration']);
        $this->assertFalse($april['coverage']['is_complete']);
    }

    /**
     * rangeFigures()（同月比較・左右比較のyear型期間で使用）も同じクラスの不具合を持っていたため
     * 回帰確認する（Codexが直接指摘したのはmonthlyFiguresForYear側だが、同じ集計パターンを
     * 持つrangeFigures()にも同様の修正を適用した）。
     */
    public function test_same_month_comparison_all_departments_registration_reflects_per_department_channels()
    {
        $this->markCompanyAsSunbrain();

        $this->seedMonth('planning', 2026, 5, 'standard', [['order_number' => 'P1', 'client_name' => 'X', 'amount' => 100]]);
        $this->seedMonth('planning', 2026, 5, 'direct', [['order_number' => 'P2', 'client_name' => 'X', 'amount' => 50]]);
        $this->seedMonth('production', 2026, 5, 'standard', [['order_number' => 'S1', 'client_name' => 'Y', 'amount' => 200]]);

        $result = $this->service()->sameMonthComparison('all', 5);
        $row2026 = collect($result['yearly'])->firstWhere('year', 2026);

        $this->assertSame('partial', $row2026['registration']);
    }
}
