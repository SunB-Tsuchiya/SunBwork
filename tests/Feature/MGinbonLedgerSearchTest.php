<?php

namespace Tests\Feature;

use App\Services\MGinbon\MGinbonLedgerSearch;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MGinbonLedgerSearchTest extends TestCase
{
    public function test_it_decodes_non_empty_conditions_and_limits_invalid_payloads(): void
    {
        $search = app(MGinbonLedgerSearch::class);
        $requests = $search->decode(json_encode([
            ['conditions' => [
                ['field' => 'school_name', 'value' => '青山'],
                ['field' => 'media', 'value' => ''],
            ]],
            ['conditions' => [
                ['field' => 'milestone', 'code' => 'manuscript_received_on', 'subject' => 'japanese', 'value' => '02/03'],
            ]],
        ], JSON_UNESCAPED_UNICODE));

        $this->assertCount(2, $requests);
        $this->assertCount(1, $requests[0]['conditions']);
        $this->assertSame('青山', $requests[0]['conditions'][0]['value']);
        $this->assertSame('japanese', $requests[1]['conditions'][0]['subject']);
        $this->assertSame([], $search->decode('{invalid'));
    }

    public function test_metadata_requests_are_or_and_conditions_inside_each_request_are_and(): void
    {
        $search = app(MGinbonLedgerSearch::class);
        $requests = $search->decode(json_encode([
            ['conditions' => [
                ['field' => 'school_name', 'value' => '青山'],
                ['field' => 'media', 'value' => '問題'],
            ]],
            ['conditions' => [
                ['field' => 'mikuni_code', 'value' => '101'],
            ]],
        ], JSON_UNESCAPED_UNICODE));

        $query = DB::connection('mginbon')->table('mginbon_items as items')
            ->join('mginbon_production_units as units', 'units.id', '=', 'items.mginbon_production_unit_id')
            ->join('mginbon_media_types as media', 'media.id', '=', 'items.mginbon_media_type_id');

        $sql = $search->apply($query, $requests)->toSql();

        $this->assertStringContainsString('display_name', $sql);
        $this->assertStringContainsString('media', $sql);
        $this->assertStringContainsString('mikuni_code', $sql);
        $this->assertSame(['%青山%', '%問題%', '%101%'], $query->getBindings());
    }
    public function test_it_applies_include_requests_before_omitting_matching_requests(): void
    {
        $search = app(MGinbonLedgerSearch::class);
        $requests = $search->decode(json_encode([
            ['conditions' => [['field' => 'school_name', 'value' => '学院']]],
            ['omit' => true, 'conditions' => [['field' => 'media', 'value' => '解答用紙']]],
        ], JSON_UNESCAPED_UNICODE));

        $query = DB::connection('mginbon')->table('mginbon_items as items')
            ->join('mginbon_production_units as units', 'units.id', '=', 'items.mginbon_production_unit_id')
            ->join('mginbon_media_types as media', 'media.id', '=', 'items.mginbon_media_type_id');

        $sql = $search->apply($query, $requests)->toSql();

        $this->assertFalse($requests[0]['omit']);
        $this->assertTrue($requests[1]['omit']);
        $this->assertStringContainsString('not (', $sql);
        $this->assertSame(['%学院%', '%解答用紙%'], $query->getBindings());
    }

    public function test_it_translates_filemaker_exact_range_and_wildcard_operators(): void
    {
        $search = app(MGinbonLedgerSearch::class);
        $requests = $search->decode(json_encode([
            ['conditions' => [
                ['field' => 'mikuni_code', 'value' => '>=100'],
                ['field' => 'n_code', 'value' => '100...199'],
                ['field' => 'school_name', 'value' => '青山*'],
                ['field' => 'media', 'value' => '==問題'],
            ]],
        ], JSON_UNESCAPED_UNICODE));

        $query = DB::connection('mginbon')->table('mginbon_items as items')
            ->join('mginbon_production_units as units', 'units.id', '=', 'items.mginbon_production_unit_id')
            ->join('mginbon_media_types as media', 'media.id', '=', 'items.mginbon_media_type_id');

        $sql = $search->apply($query, $requests)->toSql();

        $this->assertStringContainsString('>= ?', $sql);
        $this->assertStringContainsString('<= ?', $sql);
        $this->assertStringContainsString('REGEXP', $sql);
        $this->assertSame(['100', '100', '199', '青山.*', '問題'], $query->getBindings());
    }

    public function test_it_decodes_version_two_and_resolves_relative_dates(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
        $search = app(MGinbonLedgerSearch::class);
        $requests = $search->decode(json_encode(['version' => 2, 'requests' => [[
            'label' => '今後7日', 'conditions' => [[
                'field' => 'milestone', 'code' => 'manuscript_due_on', 'subject' => '', 'value' => 'relative:next_days:7',
            ]],
        ]]], JSON_UNESCAPED_UNICODE));
        $query = DB::connection('mginbon')->table('mginbon_items as items')
            ->join('mginbon_production_units as units', 'units.id', '=', 'items.mginbon_production_unit_id')
            ->join('mginbon_media_types as media', 'media.id', '=', 'items.mginbon_media_type_id');

        $sql = $search->apply($query, $requests)->toSql();

        $this->assertStringContainsString('mginbon_milestones', $sql);
        $this->assertContains('2026-10-01', $query->getBindings());
        $this->assertContains('2026-10-08', $query->getBindings());
    }

    public function test_missing_milestone_and_anomaly_use_safe_exists_queries(): void
    {
        $search = app(MGinbonLedgerSearch::class);
        $requests = $search->decode(json_encode(['version' => 2, 'requests' => [[
            'conditions' => [
                ['field' => 'milestone', 'code' => 'completed_on', 'subject' => '', 'value' => '='],
                ['field' => 'anomaly', 'code' => 'stale_30_days', 'subject' => '', 'value' => '1'],
            ],
        ]]], JSON_UNESCAPED_UNICODE));
        $query = DB::connection('mginbon')->table('mginbon_items as items')
            ->join('mginbon_production_units as units', 'units.id', '=', 'items.mginbon_production_unit_id')
            ->join('mginbon_media_types as media', 'media.id', '=', 'items.mginbon_media_type_id');

        $sql = $search->apply($query, $requests)->toSql();

        $this->assertStringContainsString('not exists', $sql);
        $this->assertStringContainsString('items`.`updated_at', $sql);
    }


    public function test_search_criteria_is_canonical_and_discards_unknown_fields(): void
    {
        $criteria = app(\App\Services\MGinbon\MGinbonSearchCriteria::class);
        $normalized = $criteria->normalize(['version' => 2, 'requests' => [['conditions' => [
            ['field' => 'school_name', 'value' => '青山'],
            ['field' => 'raw_sql', 'value' => '1 = 1'],
        ]]]]);

        $this->assertCount(1, $normalized['requests']);
        $this->assertCount(1, $normalized['requests'][0]['conditions']);
        $this->assertSame(64, strlen($criteria->hash($normalized)));
        $this->assertStringContainsString('学校名:青山', $criteria->summary($normalized));
    }

    public function test_extended_anomaly_rules_compile_to_safe_queries(): void
    {
        $search = app(MGinbonLedgerSearch::class);
        $codes = [
            'proof_completed_before_started', 'multiple_active_actor', 'duplicate_item_subject',
            'previous_done_next_missing', 'overdue_pending', 'missing_stage_task',
        ];

        foreach ($codes as $code) {
            $requests = $search->decode(json_encode(['version' => 2, 'requests' => [['conditions' => [[
                'field' => 'anomaly', 'code' => $code, 'value' => '1',
            ]]]]]));
            $query = DB::connection('mginbon')->table('mginbon_items as items')
                ->join('mginbon_production_units as units', 'units.id', '=', 'items.mginbon_production_unit_id')
                ->join('mginbon_media_types as media', 'media.id', '=', 'items.mginbon_media_type_id');

            $sql = $search->apply($query, $requests)->toSql();
            $this->assertStringNotContainsString('1 = 0', $sql, $code);
            $this->assertStringContainsString('mginbon_', $sql, $code);
        }
    }
}
