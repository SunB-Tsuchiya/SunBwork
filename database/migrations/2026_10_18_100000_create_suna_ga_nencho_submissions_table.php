<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 年末調整の提出物（従業員 1 人×受付 1 件）。繰り返し項目は別テーブル。ユーザーが削除されても消えない（保存義務 7 年）
        Schema::create('suna_ga_nencho_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('suna_ga_nencho_campaigns')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name', 100)->nullable();
            $table->unsignedBigInteger('company_id');                  // 従業員の所属会社（受付の会社と同じ）
            $table->string('status', 20)->default('draft');           // draft / submitted / returned
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->foreignId('entered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entered_by_type', 10)->default('self');   // self / proxy
            $table->timestamp('submitted_at')->nullable();
            $table->json('snapshot')->nullable();                      // 提出時の内容（マイナンバーは含めない）

            // 本人情報（社員情報から初期表示）
            $table->string('name', 30)->nullable();
            $table->string('name_kana', 60)->nullable();
            $table->date('birthday')->nullable();
            $table->string('postal_code', 7)->nullable();
            $table->string('prefecture', 4)->nullable();
            $table->string('city', 35)->nullable();
            $table->string('street', 100)->nullable();
            $table->string('household_head_name', 30)->nullable();
            $table->unsignedSmallInteger('household_head_relation')->nullable();
            $table->string('household_head_relation_other', 10)->nullable();
            $table->text('my_number')->nullable();                     // 提出時に社員情報から暗号化コピー（encrypted キャスト）
            $table->boolean('my_number_provided')->default(false);

            // 本人の区分
            $table->boolean('no_category')->default(false);            // いずれも該当しない
            $table->unsignedTinyInteger('disability_class')->default(0);   // 0非該当 1一般 2特別
            $table->string('disability_handbook_type', 20)->nullable();
            $table->date('disability_issued_on')->nullable();
            $table->string('disability_detail', 50)->nullable();
            $table->unsignedTinyInteger('single_parent_class')->default(0); // 0非該当 1寡婦 2ひとり親
            $table->boolean('working_student')->default(false);
            $table->string('school_name', 30)->nullable();
            $table->date('enrolled_on')->nullable();
            $table->unsignedTinyInteger('resident_tax_single_parent_class')->default(0); // 住民税: 0非該当 1寡婦 2ひとり親
            $table->boolean('has_secondary_job_form')->default(false);  // 従たる給与についての扶養控除等申告書の提出
            $table->json('category_changes')->nullable();               // 区分の異動 {disability|single_parent|working_student: {month, day, reason, reason_other}}

            // 所得の見積り（本人が入力。後から総務の事前投入に切り替え可能）
            $table->unsignedBigInteger('salary_income')->nullable();
            $table->unsignedBigInteger('salary_income_estimate')->nullable();
            $table->bigInteger('other_income_estimate')->nullable();
            $table->bigInteger('total_income_estimate')->nullable();

            // 小規模企業共済等掛金控除
            $table->unsignedBigInteger('small_biz_kyosai')->nullable();
            $table->unsignedBigInteger('dc_corporate')->nullable();
            $table->unsignedBigInteger('dc_individual')->nullable();
            $table->unsignedBigInteger('disabled_kyosai')->nullable();

            $table->json('life_under23_dependent')->nullable();         // 23歳未満の扶養親族の情報（生命保険料控除の特例用）
            $table->timestamps();
            $table->unique(['campaign_id', 'user_id'], 'suna_ga_nencho_submission_unique');
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suna_ga_nencho_submissions');
    }
};
