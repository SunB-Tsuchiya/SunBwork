<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_departments', function (Blueprint $table) {
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->primary(['client_id', 'department_id']);
        });

        // 既存クライアントをすべて 情報出版(id=1) に紐付け
        $clientIds = DB::table('clients')->pluck('id');
        $rows = $clientIds->map(fn($id) => ['client_id' => $id, 'department_id' => 1])->all();
        if (!empty($rows)) {
            DB::table('client_departments')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_departments');
    }
};
