<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('personal_calendar_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('item_type', 20); $t->string('title'); $t->text('note')->nullable();
            $t->date('start_date')->nullable(); $t->date('end_date')->nullable();
            $t->time('start_time')->nullable(); $t->time('end_time')->nullable();
            $t->boolean('all_day')->default(true); $t->string('color_key',20)->default('indigo');
            $t->timestamp('completed_at')->nullable(); $t->unsignedInteger('version')->default(1);
            $t->softDeletes(); $t->uuid('request_key'); $t->string('request_hash',64); $t->timestamps();
            $t->unique(['company_id','user_id','request_key'],'personal_calendar_request_unique');
            $t->index(['company_id','user_id','start_date'],'personal_calendar_owner_date');
        });
    }
    public function down(): void { Schema::dropIfExists('personal_calendar_items'); }
};
