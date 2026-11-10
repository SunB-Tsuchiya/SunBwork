<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 取込の取り消し用に、取込の確定時に「変更前の行」を保存する（SUNASALES_PLAN1.md §16）。
//   kind='updated' … 取込で値が変わった行の、変更前の姿（取り消すと、この値に戻す）
//   kind='deleted' … 取込で削除された行の、削除前の姿（取り消すと、同じ id で復元する）
// 追加された行は保存しない（変更ログの「追加」の記録で特定して消す）。取り消した取込のぶんは削除する。
return new class extends Migration
{
    protected $connection = 'sales';

    public function up(): void
    {
        Schema::connection('sales')->create('suna_sl_import_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('import_id');   // この取込で変更・削除された
            $table->string('kind', 10);                // updated / deleted
            $table->unsignedBigInteger('fact_id');     // 元の売上の行の id
            $table->string('client_key', 255);
            $table->string('client_name', 255);
            $table->smallInteger('sales_year');
            $table->tinyInteger('sales_month')->unsigned();
            $table->string('department_key', 40);
            $table->string('section_key', 40)->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->unsignedInteger('order_count')->default(0);
            $table->unsignedBigInteger('fact_import_id'); // 元の行が持っていた import_id（最後にその行を書いた取込）
            $table->timestamp('fact_created_at')->nullable();
            $table->timestamp('fact_updated_at')->nullable();

            $table->index(['company_id', 'import_id'], 'suna_sl_snapshots_import_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('sales')->dropIfExists('suna_sl_import_snapshots');
    }
};
