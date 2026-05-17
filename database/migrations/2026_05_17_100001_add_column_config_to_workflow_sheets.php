<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_sheets', function (Blueprint $table) {
            $table->json('column_config')->nullable()->after('stage_config');
        });

        // stage_config → column_config 自動変換
        // type: coordinator→worker, proof_worker→proof_v2, それ以外はそのまま
        $typeMap = [
            'coordinator'  => 'worker',
            'proof_worker' => 'proof_v2',
        ];

        $sheets = DB::table('workflow_sheets')->whereNotNull('stage_config')->get();
        foreach ($sheets as $sheet) {
            $stageConfig = json_decode($sheet->stage_config, true);
            $stages = $stageConfig['stages'] ?? [];

            $columnConfig = array_map(function ($stage) use ($typeMap) {
                $type = $stage['type'] ?? 'worker';
                return [
                    'key'   => $stage['key']   ?? ('col_' . uniqid()),
                    'label' => $stage['label'] ?? '',
                    'type'  => $typeMap[$type] ?? $type,
                ];
            }, $stages);

            DB::table('workflow_sheets')
                ->where('id', $sheet->id)
                ->update(['column_config' => json_encode($columnConfig)]);
        }
    }

    public function down(): void
    {
        Schema::table('workflow_sheets', function (Blueprint $table) {
            $table->dropColumn('column_config');
        });
    }
};
