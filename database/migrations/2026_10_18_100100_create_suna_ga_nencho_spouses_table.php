<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suna_ga_nencho_spouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->unique()->constrained('suna_ga_nencho_submissions')->cascadeOnDelete();
            $table->unsignedTinyInteger('spouse_status')->default(0);   // 0なし 1あり 2なし（本年中の異動あり）
            $table->unsignedTinyInteger('kind')->nullable();            // 配偶者の種別 0〜7（NTAAPP001）
            $table->string('name', 30)->nullable();
            $table->string('name_kana', 60)->nullable();
            $table->date('birthday')->nullable();
            $table->text('my_number')->nullable();                      // encrypted
            $table->boolean('my_number_provided')->default(false);
            $table->boolean('address_same')->default(true);             // 申告者と同じ
            $table->string('postal_code', 7)->nullable();
            $table->string('prefecture', 4)->nullable();
            $table->string('city', 35)->nullable();
            $table->string('street', 100)->nullable();
            $table->unsignedBigInteger('income_estimate')->nullable();  // 本年中の所得の見積額
            $table->unsignedBigInteger('retirement_income')->nullable();
            $table->boolean('nonresident')->default(false);
            $table->boolean('elderly')->default(false);                 // 老人控除対象配偶者
            $table->unsignedTinyInteger('disability_class')->default(0); // 0非該当 1一般 2特別 3同居特別
            $table->string('disability_handbook_type', 20)->nullable();
            $table->date('disability_issued_on')->nullable();
            $table->string('disability_detail', 50)->nullable();
            $table->json('change')->nullable();                         // 異動 {month, day, reason, reason_other}
            $table->unsignedBigInteger('remittance_amount')->nullable(); // 生計を一にする事実（非居住者）
            $table->unsignedTinyInteger('remittance_unit')->nullable();
            $table->string('remittance_unit_other', 15)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suna_ga_nencho_spouses');
    }
};
