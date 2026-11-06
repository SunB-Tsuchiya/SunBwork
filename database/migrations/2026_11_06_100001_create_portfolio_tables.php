<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * デザイナー用ポートフォリオ（SB専用）のテーブル一式。
 * 個人専用のため user_id で絞る。設計は z_instructions/PORTFOLIO_PLAN1.md §4。
 */
return new class extends Migration
{
    public function up(): void
    {
        // 個人許可（売上分析の sales_analysis_permissions と同じ形）
        Schema::create('portfolio_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->boolean('enabled')->default(false);
            $table->foreignId('granted_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('granted_at')->nullable();
            $table->timestamps();
        });

        // 個人設定（アクセント色など）
        Schema::create('portfolio_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->string('accent_color', 7)->default('#2563eb');
            $table->string('display_name')->nullable();
            $table->string('headline')->nullable();
            $table->timestamps();
        });

        // コレクション（フォルダ）。表紙作品の FK は items 作成後に追加する
        Schema::create('portfolio_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('cover_item_id')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'sort_order']);
        });

        // 作品
        Schema::create('portfolio_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('collection_id')->nullable()->constrained('portfolio_collections')->nullOnDelete();
            $table->enum('type', ['image', 'pdf', 'video']);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('file_path')->nullable();
            $table->string('thumb_path')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration_sec')->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->enum('status', ['uploading', 'processing', 'ready', 'failed'])->default('uploading');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'collection_id', 'sort_order']);
        });

        Schema::table('portfolio_collections', function (Blueprint $table) {
            $table->foreign('cover_item_id')->references('id')->on('portfolio_items')->nullOnDelete();
        });

        // PDF のページ画像
        Schema::create('portfolio_item_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('portfolio_items')->onDelete('cascade');
            $table->unsignedInteger('page_no');
            $table->string('path');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unique(['item_id', 'page_no']);
        });

        // タグ（個人ごと）
        Schema::create('portfolio_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name', 50);
            $table->unique(['user_id', 'name']);
        });

        Schema::create('portfolio_item_tag', function (Blueprint $table) {
            $table->foreignId('item_id')->constrained('portfolio_items')->onDelete('cascade');
            $table->foreignId('tag_id')->constrained('portfolio_tags')->onDelete('cascade');
            $table->primary(['item_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_item_tag');
        Schema::dropIfExists('portfolio_tags');
        Schema::dropIfExists('portfolio_item_pages');
        Schema::table('portfolio_collections', function (Blueprint $table) {
            $table->dropForeign(['cover_item_id']);
        });
        Schema::dropIfExists('portfolio_items');
        Schema::dropIfExists('portfolio_collections');
        Schema::dropIfExists('portfolio_profiles');
        Schema::dropIfExists('portfolio_permissions');
    }
};
