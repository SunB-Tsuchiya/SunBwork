<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 交通費の状態変更の履歴（誰が・いつ・何から何へ・コメント）。再申請しても差戻しコメントが残る */
    public function up(): void
    {
        Schema::create('transport_expense_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transport_expense_id')->constrained('transport_expenses')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20);          // submit / withdraw / approve / return / bill / unapprove / unbill
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['transport_expense_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_expense_events');
    }
};
