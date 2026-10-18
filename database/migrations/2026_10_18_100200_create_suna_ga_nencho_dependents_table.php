<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suna_ga_nencho_dependents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('suna_ga_nencho_submissions')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('kind')->nullable();            // 扶養親族の種別 1〜10（NTAAPP001）
            $table->string('name', 30)->nullable();
            $table->string('name_kana', 60)->nullable();
            $table->text('my_number')->nullable();                      // encrypted
            $table->boolean('my_number_provided')->default(false);
            $table->unsignedSmallInteger('relation')->nullable();       // 3子 4親 5孫 6祖父母 7曾祖父母 99その他
            $table->string('relation_other', 10)->nullable();
            $table->date('birthday')->nullable();
            $table->unsignedTinyInteger('elderly_class')->default(0);   // 0非該当 1同居老親等 2その他
            $table->unsignedTinyInteger('specific_class')->default(0);  // 0どちらも非該当 1特定扶養 2特定親族（本人が選ぶ）
            $table->unsignedBigInteger('income_estimate')->nullable();
            $table->unsignedBigInteger('retirement_income')->nullable();
            $table->boolean('nonresident')->default(false);
            $table->unsignedTinyInteger('nonresident_reason')->nullable(); // 1「30歳以上70歳未満」以外 2留学 3障害者 4 38万円以上の支払
            $table->boolean('address_same')->default(true);
            $table->string('postal_code', 7)->nullable();
            $table->string('prefecture', 4)->nullable();
            $table->string('city', 35)->nullable();
            $table->string('street', 100)->nullable();
            $table->unsignedTinyInteger('disability_class')->default(0); // 0非該当 1一般 2特別 3同居特別
            $table->string('disability_handbook_type', 20)->nullable();
            $table->date('disability_issued_on')->nullable();
            $table->string('disability_detail', 50)->nullable();
            $table->json('other_earner')->nullable();                   // 控除を受ける他の所得者 {name, relation, relation_other, address_same, postal_code, prefecture, city, street}
            $table->unsignedBigInteger('remittance_amount')->nullable();
            $table->unsignedTinyInteger('remittance_unit')->nullable();
            $table->string('remittance_unit_other', 15)->nullable();
            $table->json('change')->nullable();                         // 異動 {month, day, reason, reason_other}
            $table->timestamps();
            $table->index(['submission_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suna_ga_nencho_dependents');
    }
};
