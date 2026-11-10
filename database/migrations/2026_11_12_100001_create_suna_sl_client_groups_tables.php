<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 得意先の名寄せ（SUNASALES_PLAN1.md §17）。表記ゆれのある得意先（例: 「株式会社A」と「A（株）」）を1社として集計するための設定。
// 売上の行（suna_sl_facts）は変更しない。集計時に、所属グループがあればグループとして数える。
//   - グループ … 統合後の表示名
//   - メンバー … 売上の行の client_key。1つの得意先は、会社内で1つのグループにだけ所属できる
//   - 変更の記録 … 誰がいつ、どの得意先を統合・解除したか（得意先名を含む。監査ログには件数のみ）
return new class extends Migration
{
    protected $connection = 'sales';

    public function up(): void
    {
        Schema::connection('sales')->create('suna_sl_client_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name', 255);
            $table->timestamps();

            $table->index(['company_id'], 'suna_sl_client_groups_company_idx');
        });

        Schema::connection('sales')->create('suna_sl_client_group_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('group_id');
            $table->string('client_key', 255);
            $table->string('client_name', 255); // 統合したときの表示名（画面・履歴用）
            $table->timestamps();

            $table->unique(['company_id', 'client_key'], 'suna_sl_group_members_unique');
            $table->index(['company_id', 'group_id'], 'suna_sl_group_members_group_idx');
        });

        Schema::connection('sales')->create('suna_sl_client_group_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('user_id');         // 通常DB user_id。クロスDB FKは張らない
            $table->string('action', 12);                  // create / rename / add / remove / dissolve
            $table->unsignedBigInteger('group_id')->nullable();
            $table->string('group_name', 255);
            $table->string('client_key', 255)->nullable();
            $table->string('client_name', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'created_at'], 'suna_sl_group_events_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('sales')->dropIfExists('suna_sl_client_group_events');
        Schema::connection('sales')->dropIfExists('suna_sl_client_group_members');
        Schema::connection('sales')->dropIfExists('suna_sl_client_groups');
    }
};
