<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scripts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description');
            $table->string('component_key', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('scripts')->insert([
            'name'          => '画像ファイル一括リネーム',
            'slug'          => 'image-renamer',
            'description'   => 'CSVまたはExcelのIDとタイトルリストをもとに、ローカルの画像ファイルを一括でリネームします。フォルダを選択するだけで動作し、実行前にプレビューで確認できます。',
            'component_key' => 'ImageRenamer',
            'sort_order'    => 1,
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('scripts');
    }
};
