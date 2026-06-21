<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('ghost_expires_at');
        });

        // 雇用形態順（正社員=1, 契約=2, 派遣=3, 業務委託=4）で初期値を設定
        $typeRank = ['regular' => 1, 'contract' => 2, 'dispatch' => 3, 'outsource' => 4];

        $users = DB::table('users')->orderBy('company_id')->orderBy('name')->get(['id', 'employment_type', 'company_id']);

        $byCompany = [];
        foreach ($users as $u) {
            $byCompany[$u->company_id ?? 0][] = $u;
        }

        foreach ($byCompany as $companyUsers) {
            usort($companyUsers, function ($a, $b) use ($typeRank) {
                $ra = $typeRank[$a->employment_type ?? 'regular'] ?? 5;
                $rb = $typeRank[$b->employment_type ?? 'regular'] ?? 5;
                if ($ra !== $rb) return $ra - $rb;
                return strcmp($a->name ?? '', $b->name ?? '');
            });
            foreach ($companyUsers as $i => $u) {
                DB::table('users')->where('id', $u->id)->update(['sort_order' => $i + 1]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
