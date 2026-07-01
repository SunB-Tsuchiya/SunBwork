<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prepress_tickets', function (Blueprint $table) {
            $table->boolean('check_finish_size')->default(false)->after('sb_delivery_date');
            $table->boolean('check_trim_marks')->default(false)->after('check_finish_size');
            $table->boolean('check_imposition')->default(false)->after('check_trim_marks');
            $table->boolean('check_color_count')->default(false)->after('check_imposition');
            $table->boolean('check_screen_ruling')->default(false)->after('check_color_count');
            $table->boolean('check_n_mark_trap')->default(false)->after('check_screen_ruling');
            $table->boolean('check_color_correction')->default(false)->after('check_n_mark_trap');
            $table->string('indesign_version', 20)->nullable()->after('check_color_correction');
            $table->string('illustrator_version', 20)->nullable()->after('indesign_version');
            $table->text('check_memo')->nullable()->after('illustrator_version');
        });
    }

    public function down(): void
    {
        Schema::table('prepress_tickets', function (Blueprint $table) {
            $table->dropColumn([
                'check_finish_size', 'check_trim_marks', 'check_imposition',
                'check_color_count', 'check_screen_ruling', 'check_n_mark_trap',
                'check_color_correction', 'indesign_version', 'illustrator_version', 'check_memo',
            ]);
        });
    }
};
