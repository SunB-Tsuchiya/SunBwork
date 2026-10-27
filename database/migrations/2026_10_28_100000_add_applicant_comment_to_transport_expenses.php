<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 交通費: 申請者のコメント（申請・再申請に添える。差戻しへの返事など）。
 * この列は「次に申請するときのコメント」の下書き。申請すると、履歴（transport_expense_events.comment）に残し、この列は空に戻す。
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('transport_expenses', 'applicant_comment')) {
            Schema::table('transport_expenses', function (Blueprint $table) {
                $table->text('applicant_comment')->nullable()->after('review_comment');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transport_expenses', 'applicant_comment')) {
            Schema::table('transport_expenses', fn (Blueprint $table) => $table->dropColumn('applicant_comment'));
        }
    }
};
