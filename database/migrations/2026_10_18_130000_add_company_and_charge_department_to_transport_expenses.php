<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 交通費を会社ごとに扱う。
     * company_id: 申請の会社（部門の選択肢・出力様式を決める）
     * charge_department_id: 請求先の部門（会社に登録された部署。NULL = 共通）
     */
    public function up(): void
    {
        Schema::table('transport_expenses', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->foreignId('charge_department_id')->nullable()->after('department_id')->constrained('departments')->nullOnDelete();
        });

        // 既存行の補完: 会社は申請者の会社。部門は旧コードの名称と同名の部署（共通=0 は NULL のまま）
        $names = [10 => '情報出版', 20 => '制作', 30 => '製版', 50 => 'オンデマンド'];
        foreach (DB::table('transport_expenses')->get(['id', 'user_id', 'department_code']) as $row) {
            $user = DB::table('users')->where('id', $row->user_id)->first(['company_id', 'home_company_id']);
            $companyId = $user->company_id ?? $user->home_company_id ?? null;
            $deptId = null;
            if ($companyId && isset($names[(int) $row->department_code])) {
                $deptId = DB::table('departments')
                    ->where('company_id', $companyId)->where('name', $names[(int) $row->department_code])->value('id');
            }
            DB::table('transport_expenses')->where('id', $row->id)->update([
                'company_id' => $companyId, 'charge_department_id' => $deptId,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('transport_expenses', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['charge_department_id']);
            $table->dropColumn(['company_id', 'charge_department_id']);
        });
    }
};
