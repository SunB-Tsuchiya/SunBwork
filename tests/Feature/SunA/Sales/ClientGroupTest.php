<?php

namespace Tests\Feature\SunA\Sales;

use App\Models\SunA\Sales\AuditLog;
use App\Models\SunA\Sales\ClientGroup;
use App\Models\SunA\Sales\ClientGroupEvent;
use App\Models\SunA\Sales\ClientGroupMember;
use App\Models\SunA\Sales\Fact;
use App\Models\SunA\Sales\Import;
use App\Models\SunA\Sales\ReportPermission;
use App\Models\User;
use App\Services\SalesAnalysis\ClientNameNormalizer;
use App\Services\SunA\Sales\ClientGroupCandidates;
use App\Services\SunA\Sales\DepartmentInitializer;
use App\Services\SunA\Sales\SunaCompanyContext;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\Concerns\SetsUpSunaSales;
use Tests\TestCase;

/**
 * 得意先の名寄せ設定。設定すると、分析の集計で1社として扱われる（売上の行は変えない）。SUNASALES_PLAN1.md §17
 *
 * データ（受注件数は各行1）:
 *   東京印刷 … 「株式会社東京印刷」2024年1月=100・2025年1月=150（部1）／「東京印刷（株）」2024年1月=50・2025年1月=30（部2）
 *   大阪商事 2024年1月=70
 *   桜学園   … 「(学)桜学園」2025年2月=40／「桜学園」2025年2月=10
 *   「株式会社甲 営業部」と「甲」（法人格以外の違い）、「株式会社ア」（除くと1文字）
 */
class ClientGroupTest extends TestCase
{
    use RefreshesSalesDatabase;
    use SetsUpSunaSales;

    private const T1 = '株式会社東京印刷';

    private const T2 = '東京印刷（株）';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSunaSales();
        $this->seedData($this->saneiCompanyId);
    }

    protected function tearDown(): void
    {
        $this->tearDownSunaSales();
        parent::tearDown();
    }

    private function key(string $name): string
    {
        return ClientNameNormalizer::normalize($name);
    }

    private function seedData(int $companyId): void
    {
        SunaCompanyContext::run($companyId, function () {
            (new DepartmentInitializer())->ensureDefaults();
            $import = Import::create(['format' => 'client_sales_v1', 'original_filename' => 'ダミー.xlsx', 'file_sha256' => hash('sha256', uniqid()), 'status' => 'completed', 'imported_by' => 1, 'imported_at' => now()]);
            $rows = [
                [self::T1, 2024, 1, 100, 'dept1'], [self::T1, 2025, 1, 150, 'dept1'],
                [self::T2, 2024, 1, 50, 'dept2'], [self::T2, 2025, 1, 30, 'dept2'],
                ['大阪商事', 2024, 1, 70, 'dept1'],
                ['(学)桜学園', 2025, 2, 40, 'dept1'], ['桜学園', 2025, 2, 10, 'dept1'],
                ['株式会社甲 営業部', 2025, 1, 5, 'dept1'], ['甲', 2025, 1, 5, 'dept1'],
                ['株式会社ア', 2025, 1, 5, 'dept1'], ['ア', 2025, 1, 5, 'dept1'],
            ];
            foreach ($rows as [$name, $year, $month, $amount, $dept]) {
                Fact::create(['client_key' => ClientNameNormalizer::normalize($name), 'client_name' => $name, 'sales_year' => $year, 'sales_month' => $month, 'department_key' => $dept, 'amount' => $amount, 'order_count' => 1, 'import_id' => $import->id]);
            }
        });
    }

    private function user(bool $canImport = true): User
    {
        $user = $this->saneiUser('user');
        ReportPermission::create(['user_id' => $user->id, 'enabled' => true, 'can_import' => $canImport]);

        return $user;
    }

    private function api(string $name, array $query = [], ?User $user = null)
    {
        return $this->actingAs($user ?? $this->user())->getJson(route("suna.sales.report.api.{$name}", $query))->assertOk()->json();
    }

    private function createGroup(User $user, string $name = '東京印刷', ?array $names = null)
    {
        $keys = array_map(fn ($n) => $this->key($n), $names ?? [self::T1, self::T2]);

        return $this->actingAs($user)->postJson(route('suna.sales.report.api.client_groups.store'), ['name' => $name, 'client_keys' => $keys]);
    }

    private function inCompany(callable $fn, ?int $companyId = null)
    {
        return SunaCompanyContext::run($companyId ?? $this->saneiCompanyId, $fn);
    }

    public function test_strip_removes_only_corporate_forms_at_the_start_or_end()
    {
        $this->assertSame('東京印刷', ClientGroupCandidates::strip('株式会社東京印刷'));
        $this->assertSame('東京印刷', ClientGroupCandidates::strip('東京印刷（株）'));
        $this->assertSame('東京印刷', ClientGroupCandidates::strip('東京印刷㈱'));
        $this->assertSame('東京印刷', ClientGroupCandidates::strip('  東京　印刷 (株) '));        // 全角半角・空白の違いも吸収
        $this->assertSame('桜学園', ClientGroupCandidates::strip('学校法人桜学園'));
        $this->assertSame('桜学園', ClientGroupCandidates::strip('（学）桜学園'));
        $this->assertSame('桜財団', ClientGroupCandidates::strip('一般財団法人桜財団'));
        $this->assertSame('東京株式印刷', ClientGroupCandidates::strip('東京株式印刷'));        // 途中にある語は除かない
        $this->assertSame('甲営業部', ClientGroupCandidates::strip('株式会社甲 営業部'));          // 部署名などは残す
        $this->assertNull(ClientGroupCandidates::strip('株式会社ア'));                          // 除くと1文字は候補にしない
    }

    public function test_candidates_cluster_names_that_differ_only_by_corporate_form()
    {
        $c = $this->api('client_groups.data')['candidates'];
        $clusters = collect($c)->map(fn ($x) => collect($x['clients'])->pluck('name')->sort()->values()->all())->all();

        $this->assertContains(['東京印刷（株）', '株式会社東京印刷'], array_map(fn ($a) => collect($a)->sort()->values()->all(), $clusters));
        $this->assertCount(2, $c);                                                               // 東京印刷、桜学園 のみ
        $this->assertSame(9, $this->api('client_groups.data')['candidates_checked']);            // 候補を探した得意先の数（全9社）
        $this->assertSame(['株式会社東京印刷', '東京印刷（株）'], collect($c[0]['clients'])->pluck('name')->all()); // 売上の多い順
        $names = collect($c)->flatMap(fn ($x) => collect($x['clients'])->pluck('name'))->all();
        $this->assertNotContains('甲', $names);        // 「営業部」の違いは候補にしない
        $this->assertNotContains('ア', $names);        // 除くと1文字
        $this->assertNotContains('大阪商事', $names);  // 1社だけ

        // グループにした得意先は、もう候補に出ない
        $this->createGroup($this->user())->assertStatus(201);
        $this->assertCount(1, $this->api('client_groups.data')['candidates']);
    }

    public function test_grouping_merges_the_numbers_in_every_analysis_and_does_not_touch_facts()
    {
        $user = $this->user();
        $factsBefore = $this->inCompany(fn () => Fact::query()->orderBy('id')->get()->toArray());

        // 統合前: 東京印刷は2社
        $this->assertSame(['株式会社東京印刷', '東京印刷（株）'], collect($this->api('annual.clients', ['year' => 2025, 'mode' => 'all'], $user)['rows'])->pluck('label')->filter(fn ($l) => str_contains($l, '東京印刷'))->values()->all());
        $this->assertSame(8, $this->api('annual.summary', ['year' => 2025], $user)['kpi']['client_count']['value']);    // 東京印刷×2・桜学園×2・甲×2・ア×2

        $id = $this->createGroup($user)->assertStatus(201)->json('id');

        // 年次分析: ランキングは1社（金額は合算）、取引社数も1社ぶん減る
        $rows = collect($this->api('annual.clients', ['year' => 2025, 'mode' => 'all'], $user)['rows'])->keyBy('client_key');
        $this->assertEquals(180, $rows["g:{$id}"]['amount']);          // 150 + 30
        $this->assertSame('東京印刷', $rows["g:{$id}"]['label']);      // グループ名で表示
        $this->assertEquals(150, $rows["g:{$id}"]['prior_amount']);    // 前年の同じ月（1月）= 100 + 50
        $this->assertEquals(30, $rows["g:{$id}"]['diff']);
        $this->assertCount(1, $rows->filter(fn ($r) => str_contains($r['label'], '東京印刷')));
        $summary = $this->api('annual.summary', ['year' => 2025], $user);
        $this->assertSame(7, $summary['kpi']['client_count']['value']);                // 8社 → 東京印刷が1社になって7社
        $this->assertEquals(150 + 30 + 40 + 10 + 5 + 5 + 5 + 5, $summary['kpi']['amount']['value']); // 売上の合計は変わらない
        $this->assertSame(8, $summary['kpi']['order_count']['value']);
        $dept = collect($summary['by_department'])->keyBy('key');
        $this->assertSame(7, $dept['dept1']['client_count']);                          // 部門別の社数（東京印刷グループ・桜学園×2・甲×2・ア×2）
        $this->assertSame(1, $dept['dept2']['client_count']);                          // 第二部は、東京印刷（株）の行を持つグループ1社

        // 同月比較: 1月の取引社数（2024年: 東京印刷1社＋大阪商事＝2社）
        $jan = collect($this->api('same_month.summary', ['month' => 1], $user)['years'])->keyBy('year');
        $this->assertSame(2, $jan[2024]['client_count']);
        $this->assertEquals(220, $jan[2024]['amount']);

        // 売上の行そのものは変わらない
        $this->assertEquals($factsBefore, $this->inCompany(fn () => Fact::query()->orderBy('id')->get()->toArray()));
    }

    public function test_client_search_and_detail_treat_a_group_as_one_client()
    {
        $user = $this->user();
        $id = $this->createGroup($user)->assertStatus(201)->json('id');

        // 検索: グループが1件。メンバー名でも見つかり、合計は全メンバーぶん
        $found = $this->api('client.search', ['keyword' => '東京印刷（株）'], $user)['clients'];
        $this->assertCount(1, $found);
        $this->assertSame("g:{$id}", $found[0]['client_key']);
        $this->assertSame('東京印刷', $found[0]['name']);
        $this->assertEquals(330, $found[0]['total_amount']);           // 100+150+50+30
        $this->assertCount(1, $this->api('client.search', ['keyword' => '東京印刷'], $user)['clients']);

        // 詳細: メンバーの行を合算。元のキーで開いても、グループとして表示
        $d = $this->api('client.detail', ['client_key' => "g:{$id}"], $user);
        $this->assertSame('東京印刷', $d['name']);
        $y2024 = collect($d['years'])->keyBy('year')[2024];
        $this->assertEquals(150, $y2024['amount']);
        $this->assertEquals(150, $y2024['months'][1]['amount']);
        $this->assertEqualsCanonicalizing(['株式会社東京印刷', '東京印刷（株）'], array_column($d['members'], 'name'));
        $this->assertSame("g:{$id}", $this->api('client.detail', ['client_key' => $this->key(self::T2)], $user)['client_key']);
        $this->assertEqualsCanonicalizing(['営業第一部', '営業第二部'], $y2024['departments']);

        // 名寄せしていない得意先は、これまでどおり（members は null）
        $solo = $this->api('client.detail', ['client_key' => $this->key('大阪商事')], $user);
        $this->assertNull($solo['members']);
    }

    public function test_dissolve_and_removing_a_member_restore_the_separate_numbers()
    {
        $user = $this->user();
        $id = $this->createGroup($user, '東京印刷', [self::T1, self::T2, '大阪商事'])->assertStatus(201)->json('id');
        $this->assertCount(3, $this->inCompany(fn () => ClientGroupMember::query()->get()));

        // 3社から1社を外す → 2社のまま残る
        $this->actingAs($user)->postJson(route('suna.sales.report.api.client_groups.members.remove', ['group' => $id]), ['client_key' => $this->key('大阪商事')])->assertOk()->assertJson(['dissolved' => false]);
        // さらに外して1社になる → 自動で解散
        $this->actingAs($user)->postJson(route('suna.sales.report.api.client_groups.members.remove', ['group' => $id]), ['client_key' => $this->key(self::T2)])->assertOk()->assertJson(['dissolved' => true]);
        $this->assertSame(0, $this->inCompany(fn () => ClientGroup::query()->count()));
        $this->assertSame(0, $this->inCompany(fn () => ClientGroupMember::query()->count()));

        // 元の2社に戻っている
        $labels = collect($this->api('annual.clients', ['year' => 2025, 'mode' => 'all'], $user)['rows'])->pluck('label')->all();
        $this->assertContains('株式会社東京印刷', $labels);
        $this->assertContains('東京印刷（株）', $labels);

        // 手動の解散
        $id2 = $this->createGroup($user)->assertStatus(201)->json('id');
        $this->actingAs($user)->deleteJson(route('suna.sales.report.api.client_groups.destroy', ['group' => $id2]))->assertOk();
        $this->assertSame(0, $this->inCompany(fn () => ClientGroup::query()->count()));
    }

    public function test_rename_and_add_members()
    {
        $user = $this->user();
        $id = $this->createGroup($user)->assertStatus(201)->json('id');
        $this->actingAs($user)->patchJson(route('suna.sales.report.api.client_groups.update', ['group' => $id]), ['name' => '東京印刷グループ'])->assertOk();
        $this->actingAs($user)->postJson(route('suna.sales.report.api.client_groups.members.add', ['group' => $id]), ['client_keys' => [$this->key('大阪商事')]])->assertOk();

        $g = collect($this->api('client_groups.data', [], $user)['groups'])->firstWhere('id', $id);
        $this->assertSame('東京印刷グループ', $g['name']);
        $this->assertCount(3, $g['members']);
        $this->assertSame('東京印刷グループ', $this->api('client.detail', ['client_key' => "g:{$id}"], $user)['name']);
    }

    public function test_invalid_operations_are_rejected()
    {
        $user = $this->user();
        $this->createGroup($user, '1社だけ', [self::T1])->assertStatus(422);                       // 2社以上
        $this->createGroup($user, '', [self::T1, self::T2])->assertStatus(422);                    // 名前が空
        $this->createGroup($user, '存在しない', [self::T1, '存在しない会社'])->assertStatus(422);     // 売上に無い得意先
        $this->assertSame(0, $this->inCompany(fn () => ClientGroup::query()->count()));

        $id = $this->createGroup($user)->assertStatus(201)->json('id');
        $this->createGroup($user, '重複', [self::T1, '大阪商事'])->assertStatus(422);                // すでに別のグループにいる
        $this->actingAs($user)->postJson(route('suna.sales.report.api.client_groups.members.add', ['group' => $id]), ['client_keys' => [$this->key(self::T1)]])->assertStatus(422);
        $this->actingAs($user)->postJson(route('suna.sales.report.api.client_groups.members.remove', ['group' => $id]), ['client_key' => $this->key('大阪商事')])->assertStatus(422); // 含まれていない
        $this->actingAs($user)->deleteJson(route('suna.sales.report.api.client_groups.destroy', ['group' => 99999]))->assertStatus(422);
        $this->assertSame(1, $this->inCompany(fn () => ClientGroup::query()->count()));
    }

    public function test_viewers_can_look_but_only_importers_can_change()
    {
        $importer = $this->user(true);
        $viewer = $this->user(false);
        $id = $this->createGroup($importer)->assertStatus(201)->json('id');

        $this->actingAs($viewer)->get(route('suna.sales.report.client_groups'))->assertOk()->assertInertia(fn ($p) => $p->component('SunA/Sales/ClientGroups', false));
        $this->assertCount(1, $this->api('client_groups.data', [], $viewer)['groups']);
        $this->api('client_groups.events', [], $viewer);

        $keys = [$this->key('大阪商事'), $this->key('桜学園')];
        $this->createGroup($viewer, '勝手に', ['大阪商事', '桜学園'])->assertForbidden();
        $this->actingAs($viewer)->patchJson(route('suna.sales.report.api.client_groups.update', ['group' => $id]), ['name' => 'x'])->assertForbidden();
        $this->actingAs($viewer)->postJson(route('suna.sales.report.api.client_groups.members.add', ['group' => $id]), ['client_keys' => $keys])->assertForbidden();
        $this->actingAs($viewer)->deleteJson(route('suna.sales.report.api.client_groups.destroy', ['group' => $id]))->assertForbidden();

        $nobody = $this->saneiUser('user');
        $this->actingAs($nobody)->get(route('suna.sales.report.client_groups'))->assertForbidden();
        $this->actingAs($nobody)->getJson(route('suna.sales.report.api.client_groups.data'))->assertForbidden();
        $this->assertSame(1, $this->inCompany(fn () => ClientGroup::query()->count()));
    }

    public function test_other_company_groups_never_mix()
    {
        // 別会社に、同じ得意先名のデータと、それを統合したグループがある
        $this->seedData($this->otherSaneiCompanyId);
        $other = $this->saneiUser('user', ['company_id' => $this->otherSaneiCompanyId]);
        ReportPermission::create(['user_id' => $other->id, 'enabled' => true, 'can_import' => true]);
        $this->withSession(['superadmin_context' => ['company_id' => $this->otherSaneiCompanyId]]);
        $this->createGroup($other)->assertStatus(201);
        $this->withSession(['superadmin_context' => ['company_id' => $this->saneiCompanyId]]);

        // 自社の集計には影響しない（自社はまだ統合していないので、東京印刷は2社のまま）
        $mine = $this->user();
        $labels = collect($this->api('annual.clients', ['year' => 2025, 'mode' => 'all'], $mine)['rows'])->pluck('label')->all();
        $this->assertContains('株式会社東京印刷', $labels);
        $this->assertSame(0, $this->inCompany(fn () => ClientGroup::query()->count()));
        $this->assertSame(1, $this->inCompany(fn () => ClientGroup::query()->count(), $this->otherSaneiCompanyId));
        $this->assertCount(2, $this->api('client_groups.data', [], $mine)['candidates']);

        // 自社でも同じ得意先を統合できる（会社が別なので衝突しない）
        $this->createGroup($mine)->assertStatus(201);
        $this->assertSame(1, $this->inCompany(fn () => ClientGroup::query()->count()));
    }

    public function test_events_keep_names_but_audit_log_has_counts_only()
    {
        $user = $this->user();
        $id = $this->createGroup($user)->assertStatus(201)->json('id');
        $this->actingAs($user)->deleteJson(route('suna.sales.report.api.client_groups.destroy', ['group' => $id]))->assertOk();

        $events = $this->api('client_groups.events', [], $user)['events'];
        $this->assertSame(['dissolve', 'create', 'create'], array_column($events, 'action'));
        $this->assertContains('株式会社東京印刷', array_column($events, 'client_name'));
        $this->assertSame($user->name, $events[0]['user_name']);

        $audit = $this->inCompany(fn () => AuditLog::query()->whereIn('action', ['client_group_create', 'client_group_dissolve'])->get());
        $this->assertCount(2, $audit);
        $this->assertSame(['members' => 2], $audit->firstWhere('action', 'client_group_create')->context);
        $this->assertStringNotContainsString('東京印刷', json_encode($audit->toArray(), JSON_UNESCAPED_UNICODE));
        $this->assertSame(3, $this->inCompany(fn () => ClientGroupEvent::query()->count()));
    }
}
