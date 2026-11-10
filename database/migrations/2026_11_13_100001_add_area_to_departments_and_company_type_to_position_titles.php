<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * サンエー印刷の権限体系の変更（SUNAROLE_PLAN1.md）。
 *  - departments.area : 部署が受け持つエリア（soumu 総務 / keiri 経理 / sales 営業 / NULL なし）。サンエー印刷（一般会社）の部署だけ使う
 *  - position_titles.company_type : 役職がどの会社タイプ専用か（NULL = 全社共通 = 従来どおり）。サンエー印刷用に「本部長」「副本部長」を登録する
 * 既存の部署は、一般会社のものだけ部署名から自動で埋める（サン・ブレーンの部署・既存の役職は触らない）。
 * 部署名が複数のエリアに当たる／どれにも当たらない部署は NULL のまま（反映前に一覧を確認して設定する）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->string('area', 20)->nullable()->after('module');
        });
        Schema::table('position_titles', function (Blueprint $table) {
            $table->string('company_type', 20)->nullable()->after('applicable_role');
        });

        // 一般会社（サンエー印刷）の部署のエリアを、部署名から自動で割り当てる
        $generalCompanyIds = DB::table('companies')->where('company_type', 'general')->pluck('id');
        $words = ['soumu' => '総務', 'keiri' => '経理', 'sales' => '営業'];
        foreach (DB::table('departments')->whereIn('company_id', $generalCompanyIds)->get(['id', 'name']) as $d) {
            $hits = array_keys(array_filter($words, fn ($w) => str_contains((string) $d->name, $w)));
            if (count($hits) === 1) {
                DB::table('departments')->where('id', $d->id)->update(['area' => $hits[0]]);
            }
        }

        // サンエー印刷専用の役職。並びは 役員(3) の次（= 部長(4) の上）。同じ値は id の順で並ぶ
        $now = now();
        foreach (['本部長', '副本部長'] as $name) {
            DB::table('position_titles')->updateOrInsert(
                ['name' => $name, 'company_type' => 'general'],
                ['applicable_role' => 'leader', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        DB::table('position_titles')->where('company_type', 'general')->delete();
        Schema::table('position_titles', function (Blueprint $table) {
            $table->dropColumn('company_type');
        });
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('area');
        });
    }
};
