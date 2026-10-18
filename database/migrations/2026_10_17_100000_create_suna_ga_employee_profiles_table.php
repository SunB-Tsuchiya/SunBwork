<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // サンエー印刷 総務: 社員プロフィール（住所録の土台。年末調整の本人情報の初期値）
        Schema::create('suna_ga_employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 30)->nullable();                 // 姓 + 空白 + 名
            $table->string('name_kana', 60)->nullable();            // 全角カナ
            $table->date('birthday')->nullable();
            $table->string('postal_code', 7)->nullable();           // 半角数字7桁
            $table->string('prefecture', 4)->nullable();
            $table->string('city', 35)->nullable();
            $table->string('street', 100)->nullable();              // 丁番地以降
            $table->string('household_head_name', 30)->nullable();
            $table->unsignedSmallInteger('household_head_relation')->nullable(); // 1本人 2配偶者 3子 4親 5孫 6祖父母 99その他
            $table->string('household_head_relation_other', 10)->nullable();
            $table->string('employee_number', 20)->nullable();      // 社員番号（半角英数）
            $table->timestamps();
            $table->unique('user_id');
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suna_ga_employee_profiles');
    }
};
