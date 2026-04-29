<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subcontractor_departments', function (Blueprint $table) {
            $table->foreignId('subcontractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->primary(['subcontractor_id', 'department_id']);
        });

        // 既存外注先をすべて 情報出版(id=1) に紐付け
        $subIds = DB::table('subcontractors')->pluck('id');
        $rows = $subIds->map(fn($id) => ['subcontractor_id' => $id, 'department_id' => 1])->all();
        if (!empty($rows)) {
            DB::table('subcontractor_departments')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subcontractor_departments');
    }
};
