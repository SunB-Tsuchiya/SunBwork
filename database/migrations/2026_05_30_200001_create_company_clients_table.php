<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_clients', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('client_id');
            $table->primary(['company_id', 'client_id']);
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });

        // 既存クライアントを全てサン・ブレーン(id=2)に登録
        // company_id=2 の41件 + NULL の3件（NTS, NTS(2), その他）
        $clientIds = DB::table('clients')->pluck('id');
        $rows = $clientIds->map(fn($id) => [
            'company_id' => 2,
            'client_id'  => $id,
        ])->all();

        if (!empty($rows)) {
            DB::table('company_clients')->insertOrIgnore($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_clients');
    }
};
