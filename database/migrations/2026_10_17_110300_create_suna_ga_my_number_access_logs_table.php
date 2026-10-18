<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // マイナンバーの閲覧・登録・変更の記録（だれが・いつ・だれの番号か）。ユーザーが削除されても記録は残す
        Schema::create('suna_ga_my_number_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('viewer_name', 100);
            $table->foreignId('employee_profile_id')->nullable()->constrained('suna_ga_employee_profiles')->nullOnDelete();
            $table->string('employee_name', 100);
            $table->string('action', 20);                            // view / update / clear
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('accessed_at')->useCurrent();
            $table->index('accessed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suna_ga_my_number_access_logs');
    }
};
