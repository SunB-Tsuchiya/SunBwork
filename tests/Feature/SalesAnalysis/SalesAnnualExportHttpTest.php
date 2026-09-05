<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Sales\SalesActiveMonth;
use App\Models\Sales\SalesAuditLog;
use App\Models\Sales\SalesImport;
use App\Models\Sales\SalesOrder;
use App\Models\User;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

class SalesAnnualExportHttpTest extends TestCase
{
    use RefreshesSalesDatabase;

    private function seedMonth(string $dept, int $year, int $month, string $clientName, float $amount): void
    {
        $import = SalesImport::create([
            'department_key' => $dept,
            'source_type' => 'monthly',
            'source_year' => $year,
            'source_month' => $month,
            'version' => 1,
            'original_filename' => "{$dept}-{$year}-{$month}.xlsx",
            'file_sha256' => hash('sha256', "export-http-{$dept}-{$year}-{$month}-" . uniqid()),
            'status' => 'completed',
            'imported_by' => 1,
            'imported_at' => now(),
            'order_count' => 1,
            'detail_count' => 1,
            'total_amount' => $amount,
        ]);

        SalesOrder::create([
            'sales_import_id' => $import->id,
            'order_number' => "EXH-{$dept}-{$year}-{$month}",
            'client_name' => $clientName,
            'product_name' => '商品A',
            'plate_date' => sprintf('%04d-%02d-15', $year, $month),
            'sales_year' => $year,
            'sales_month' => $month,
            'order_amount' => $amount,
        ]);

        SalesActiveMonth::updateOrCreate(
            ['department_key' => $dept, 'sales_year' => $year, 'sales_month' => $month],
            ['sales_import_id' => $import->id, 'activated_by' => 1, 'activated_at' => now()]
        );
    }

    public function test_export_returns_xlsx_with_correct_headers()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2026, 1, 'A社', 1000.0);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.annual_analysis.export', [
            'department_key' => 'planning',
            'year' => 2026,
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    public function test_export_records_audit_log_without_client_names()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->seedMonth('planning', 2026, 1, 'A社', 1000.0);

        $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.annual_analysis.export', [
            'department_key' => 'planning',
            'year' => 2026,
        ]))->assertOk();

        $log = SalesAuditLog::where('action', 'export')->first();
        $this->assertNotNull($log);
        $this->assertSame('annual_analysis', $log->target_type);
        $this->assertStringNotContainsString('A社', json_encode($log->context) ?: '');
    }

    public function test_export_requires_sales_analysis_access()
    {
        $leader = User::factory()->create(['user_role' => 'leader']);

        $this->actingAs($leader)
            ->get(route('superadmin.sales_analysis.annual_analysis.export', ['department_key' => 'planning', 'year' => 2026]))
            ->assertForbidden();
    }

    public function test_export_rejects_unknown_department()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $this->actingAs($superadmin)
            ->get(route('superadmin.sales_analysis.annual_analysis.export', ['department_key' => 'unknown', 'year' => 2026]), ['Accept' => 'application/json'])
            ->assertStatus(422);
    }
}
