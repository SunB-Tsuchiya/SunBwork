<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 期限の解除を、申請者からの「依頼」から始められるようにする。
     * status: requested（依頼中）/ granted（許可＝期限を解除）/ rejected（却下）。既存の行は、総務・経理が直接許可したものなので granted。
     * reason は、依頼の理由（申請者）または直接許可の理由（総務・経理）。granted_by は、許可・却下した人。
     */
    public function up(): void
    {
        Schema::table('transport_deadline_extensions', function (Blueprint $table) {
            $table->string('status', 12)->default('granted')->after('billing_month');
            $table->timestamp('requested_at')->nullable()->after('reason');
            $table->timestamp('decided_at')->nullable()->after('requested_at');
            $table->string('decision_comment', 255)->nullable()->after('decided_at');
        });
    }

    public function down(): void
    {
        Schema::table('transport_deadline_extensions', function (Blueprint $table) {
            $table->dropColumn(['status', 'requested_at', 'decided_at', 'decision_comment']);
        });
    }
};
