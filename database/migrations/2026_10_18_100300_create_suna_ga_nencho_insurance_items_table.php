<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 保険料控除の繰り返し行（生命保険=一般/介護医療/個人年金、地震保険、社会保険料）
        Schema::create('suna_ga_nencho_insurance_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('suna_ga_nencho_submissions')->cascadeOnDelete();
            $table->string('kind', 20);                                 // life_general / life_care / life_pension / earthquake / social
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('company_name', 30)->nullable();             // 保険会社名
            $table->string('insurance_type', 30)->nullable();           // 保険種類 / 保険等の種類（目的） / 社会保険の種類
            $table->unsignedSmallInteger('period')->nullable();         // 保険期間（0=終身）
            $table->string('contractor_name', 30)->nullable();          // 契約者名
            $table->string('beneficiary_name', 30)->nullable();         // 受取人（生命保険）
            $table->string('insured_name', 30)->nullable();             // 居住者等の氏名（地震）/ 保険料を負担する人（社会保険）
            $table->string('payee_name', 30)->nullable();               // 保険料支払先の名称（社会保険）
            $table->unsignedTinyInteger('new_old')->nullable();         // 0旧 1新（生命保険・個人年金）
            $table->date('pension_start_on')->nullable();               // 支払開始日（個人年金）
            $table->unsignedTinyInteger('quake_class')->nullable();     // 0旧長期 1地震（地震保険）
            $table->unsignedBigInteger('amount')->nullable();           // 本年中に支払った保険料等の金額
            $table->timestamps();
            $table->index(['submission_id', 'kind', 'sort_order'], 'suna_ga_nencho_ins_items_list_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suna_ga_nencho_insurance_items');
    }
};
