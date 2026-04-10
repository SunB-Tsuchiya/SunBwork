<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') return;

        Schema::create('user_monthly_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('year_month', 7); // 'YYYY-MM'
            // JSON: {"01": {"start": "12:00", "end": "13:00"}, "15": null, ...}
            // null / キーなし → その日は休憩なし
            $table->json('schedule');
            $table->timestamps();
            $table->unique(['user_id', 'year_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_monthly_breaks');
    }
};
