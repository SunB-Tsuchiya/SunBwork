<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 交通費を「ユーザー × 対象月 = 1申請」にし、受理フローの列を追加する。
     * status: draft(下書き) / submitted(申請済み) / returned(差戻し) / approved(受理) / billed(請求済み)
     */
    public function up(): void
    {
        // 同じユーザー・同じ月に複数の伝票があると一意制約を付けられない（黙って壊さず、手動統合を促す）
        $dups = DB::table('transport_expenses')
            ->select('user_id', 'billing_month', DB::raw('COUNT(*) AS c'))
            ->groupBy('user_id', 'billing_month')
            ->having('c', '>', 1)
            ->get();
        if ($dups->isNotEmpty()) {
            $list = $dups->map(fn ($d) => "user_id={$d->user_id} {$d->billing_month} ({$d->c}件)")->implode(', ');
            throw new RuntimeException('transport_expenses に同一ユーザー・同一月の重複があります。統合してから再実行してください: ' . $list);
        }

        // enum → 文字列（状態を増やすため）
        DB::statement("ALTER TABLE transport_expenses MODIFY status VARCHAR(20) NOT NULL DEFAULT 'draft'");

        Schema::table('transport_expenses', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('status');
            $table->foreignId('reviewed_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_comment')->nullable()->after('reviewed_at');
            $table->timestamp('billed_at')->nullable()->after('review_comment');

            $table->unique(['user_id', 'billing_month'], 'transport_expenses_user_month_unique');
        });
    }

    public function down(): void
    {
        Schema::table('transport_expenses', function (Blueprint $table) {
            $table->dropUnique('transport_expenses_user_month_unique');
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['submitted_at', 'reviewed_by', 'reviewed_at', 'review_comment', 'billed_at']);
        });
        DB::statement("UPDATE transport_expenses SET status = 'submitted' WHERE status NOT IN ('draft','submitted')");
        DB::statement("ALTER TABLE transport_expenses MODIFY status ENUM('draft','submitted') NOT NULL DEFAULT 'draft'");
    }
};
