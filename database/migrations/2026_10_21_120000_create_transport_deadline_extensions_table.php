<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 交通費の期限（当月末日）の解除。総務・経理が、特定の人・特定の月について「期限後でも申請してよい」と許可した記録 */
    public function up(): void
    {
        Schema::create('transport_deadline_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();   // 許可された人（申請者）
            $table->char('billing_month', 7);                                   // YYYY-MM
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'billing_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_deadline_extensions');
    }
};
