<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Services\GeneralWorkItemDefaultsService;
use Illuminate\Database\Seeder;

class GeneralWorkItemsSeeder extends Seeder
{
    /**
     * 全会社に一般的な作業項目デフォルトを投入する。
     * 既にその会社の work_item_types が存在する場合はスキップ（冪等）。
     */
    public function run(): void
    {
        $service = app(GeneralWorkItemDefaultsService::class);

        Company::all()->each(function (Company $company) use ($service) {
            $service->seedForCompany($company->id);
            $this->command?->info("  seeded: {$company->name} (id={$company->id})");
        });
    }
}
