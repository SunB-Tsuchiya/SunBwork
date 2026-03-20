<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_item_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('coefficient', 6, 3)->default(1.000);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // カテゴリー初期データ
        $now = now();
        DB::table('event_item_types')->insert([
            ['name' => '顧客訪問',       'slug' => 'customer_visit',   'coefficient' => 1.000, 'description' => '顧客先への訪問・営業活動',      'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => '打合せ（社内）', 'slug' => 'meeting_internal', 'coefficient' => 1.000, 'description' => '社内メンバーとの打合せ・協議',   'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => '打合せ（顧客）', 'slug' => 'meeting_client',   'coefficient' => 1.000, 'description' => '顧客を交えた打合せ・折衝',       'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['name' => '会議',           'slug' => 'conference',       'coefficient' => 1.000, 'description' => '社内外の会議・委員会等への参加',  'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['name' => '外出',           'slug' => 'outing',           'coefficient' => 1.000, 'description' => '業務目的の外出・移動',            'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'そのほか',       'slug' => 'other',            'coefficient' => 1.000, 'description' => '上記以外のその他の予定',          'sort_order' => 6, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('event_item_types');
    }
};
