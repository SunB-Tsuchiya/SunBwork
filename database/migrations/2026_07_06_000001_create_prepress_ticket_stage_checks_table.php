<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prepress_ticket_stage_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prepress_ticket_id')->constrained('prepress_tickets')->cascadeOnDelete();
            $table->enum('stage', ['初校', '再校', '三校', '下版']);
            $table->boolean('check_finish_size')->default(false);
            $table->boolean('check_trim_marks')->default(false);
            $table->boolean('check_imposition')->default(false);
            $table->boolean('check_color_count')->default(false);
            $table->boolean('check_screen_ruling')->default(false);
            $table->boolean('check_n_mark_trap')->default(false);
            $table->boolean('check_color_correction')->default(false);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['prepress_ticket_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prepress_ticket_stage_checks');
    }
};
