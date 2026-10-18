<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // サンエー印刷 総務: 年末調整の受付（年度ごと）
        Schema::create('suna_ga_nencho_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_type_id')->nullable()->constrained('suna_ga_application_types')->nullOnDelete();
            $table->unsignedSmallInteger('fiscal_year');            // 控除申告年分（西暦）
            $table->string('title', 100);
            $table->date('opens_on');
            $table->date('due_on');
            $table->text('message')->nullable();                    // 従業員への案内文
            $table->string('status', 20)->default('open');          // open / closed
            // 会社側情報（国の様式に必要。従業員には入力させない）
            $table->string('tax_office_name', 10)->nullable();      // 所轄税務署
            $table->string('municipality_name', 35)->nullable();    // 所轄市区町村
            $table->string('payer_name', 30)->nullable();           // 給与の支払者の名称
            $table->string('payer_corporate_number', 13)->nullable();
            $table->string('payer_postal_code', 7)->nullable();
            $table->string('payer_prefecture', 4)->nullable();
            $table->string('payer_city', 35)->nullable();
            $table->string('payer_street', 100)->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'fiscal_year'], 'suna_ga_nencho_campaign_year_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suna_ga_nencho_campaigns');
    }
};
