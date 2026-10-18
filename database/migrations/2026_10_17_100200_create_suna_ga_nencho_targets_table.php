<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // サンエー印刷 総務: 受付の対象者
        Schema::create('suna_ga_nencho_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('suna_ga_nencho_campaigns')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_excluded')->default(false);
            $table->string('excluded_reason', 200)->nullable();
            $table->boolean('added_manually')->default(false);
            $table->timestamps();
            $table->unique(['campaign_id', 'user_id'], 'suna_ga_nencho_target_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suna_ga_nencho_targets');
    }
};
