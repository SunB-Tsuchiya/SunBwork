<?php

namespace Tests\Concerns;

use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use App\Services\SunA\Sales\SunaCompanyContext;

/**
 * サンエー印刷 売上レポート（SunA/Sales）のテスト用ヘルパー。
 * RefreshesSalesDatabase と併用する（通常DBと sales DB の両方を初期化・巻き戻す）。
 * サンエー印刷に相当する会社（company_type='general'）と、別会社（サン・ブレーン相当）を作る。
 */
trait SetsUpSunaSales
{
    protected int $saneiCompanyId;

    protected int $otherSaneiCompanyId;

    protected function setUpSunaSales(): void
    {
        $this->saneiCompanyId = Company::create([
            'name' => 'テスト用サンエー_' . uniqid(), 'code' => 'SANEI_' . uniqid(), 'company_type' => 'general', 'active' => true,
        ])->id;
        // もう1社の一般会社（会社分離のテスト用。同じ company_type でも、データは混ざらないこと）
        $this->otherSaneiCompanyId = Company::create([
            'name' => 'テスト用別会社_' . uniqid(), 'code' => 'OTHER_' . uniqid(), 'company_type' => 'general', 'active' => true,
        ])->id;
        SunaCompanyContext::clear();
        // SuperAdmin の会社切替は、サンエー印刷に向ける
        $this->withSession(['superadmin_context' => ['company_id' => $this->saneiCompanyId]]);
    }

    /** @var array<string,int> "会社ID:エリア" → 部署ID */
    private array $areaDepartmentIds = [];

    /** その会社の、指定したエリア（soumu/keiri/sales）の部署（SUNAROLE_PLAN1.md）。なければ作る */
    protected function areaDepartmentId(string $area, ?int $companyId = null): int
    {
        $companyId ??= $this->saneiCompanyId;
        $names = ['soumu' => '総務部', 'keiri' => '経理部', 'sales' => '営業部'];

        return $this->areaDepartmentIds["{$companyId}:{$area}"] ??= Department::create([
            'company_id' => $companyId, 'name' => $names[$area], 'code' => "{$area}_" . uniqid(), 'area' => $area, 'active' => true,
        ])->id;
    }

    protected function salesDepartmentId(?int $companyId = null): int
    {
        return $this->areaDepartmentId('sales', $companyId);
    }

    /** 指定したエリアの部署に所属させる */
    protected function inArea(User $user, string $area): User
    {
        $user->update(['department_id' => $this->areaDepartmentId($area, $user->company_id)]);

        return $user;
    }

    /** 営業エリアの部署に所属させる（売上レポートを開ける前提） */
    protected function inSalesArea(User $user): User
    {
        return $this->inArea($user, 'sales');
    }

    protected function tearDownSunaSales(): void
    {
        SunaCompanyContext::clear();
    }

    protected function saneiUser(string $role = 'user', array $attributes = []): User
    {
        return User::factory()->create(array_merge(['user_role' => $role, 'company_id' => $this->saneiCompanyId], $attributes));
    }
}
