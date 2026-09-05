<?php

namespace Tests\Concerns;

use App\Models\Company;
use App\Models\Sales\SalesDepartmentDefinition;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 通常DBと sales DB の2接続を同時に使うテスト用トレイト。
 * migrate:fresh はデフォルト接続のテーブルしか drop しないため、
 * sales接続に前回実行分のテーブルが残っていると CREATE TABLE が
 * 「already exists」で失敗する。事前に sales接続のテーブルだけを
 * 明示的に drop してから通常の migrate:fresh に委譲することで回避する。
 *
 * 会社別データ分離（2026-09-05）以降、売上分析の全クエリはcompany_idスコープが必須になったため、
 * setUp()でテスト用の会社を1つ作成し、SuperAdminアクター用にsuperadmin_contextセッションへ
 * 自動設定する（各テストファイルで個別にwithSession()する手間を無くす）。実際のアクターが
 * SuperAdminでなくAdmin/Clerkの場合は、そのユーザー自身にsalesTestCompanyId()をcompany_idとして
 * 設定すること（SuperAdmin以外はsession値を見ない）。
 */
trait RefreshesSalesDatabase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    protected $connectionsToTransact = ['mysql', 'sales'];

    private ?int $salesTestCompanyId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesTestCompanyId = Company::create([
            'name' => 'テスト用会社_' . uniqid(),
            'code' => 'TEST_' . uniqid(),
            'company_type' => 'sunbrain',
            'active' => true,
        ])->id;

        // SuperAdminアクターは画面右上の会社切替に相当するセッション値が無いとcompanyId=nullになり
        // 全ての売上分析クエリが422で弾かれる。テストの大半はSuperAdminで検証するため、
        // ここで自動的にテスト会社へ切り替えておく。
        $this->withSession(['superadmin_context' => ['company_id' => $this->salesTestCompanyId]]);

        // 既存テスト群は全て'planning'/'production'/'ondemand'の3部署を前提にしているため、
        // 会社別データ分離（sales_department_definitions）導入後もそのまま動くよう自動投入する。
        foreach ([
            ['key' => 'planning', 'label' => '企画', 'sort_order' => 1],
            ['key' => 'production', 'label' => '制作', 'sort_order' => 2],
            ['key' => 'ondemand', 'label' => 'オンデマンド', 'sort_order' => 3],
        ] as $dept) {
            SalesDepartmentDefinition::create(array_merge(['company_id' => $this->salesTestCompanyId], $dept));
        }
    }

    /** 会社別データ分離テスト用に作成したテスト会社のID */
    protected function salesTestCompanyId(): int
    {
        return $this->salesTestCompanyId;
    }

    protected function refreshTestDatabase()
    {
        if (! RefreshDatabaseState::$migrated) {
            Schema::connection('sales')->dropAllTables();
            DB::connection('sales')->reconnect();

            $this->artisan('migrate:fresh', $this->migrateFreshUsing());

            $this->app[Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }
}
