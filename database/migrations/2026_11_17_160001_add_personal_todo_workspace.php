<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up():void {
        Schema::create('personal_todo_lists',function(Blueprint $t){
            $t->id();$t->foreignId('company_id')->constrained();$t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name',80);$t->string('color_key',20)->default('blue');$t->unsignedInteger('sort_order');$t->timestamp('archived_at')->nullable();
            $t->unsignedInteger('version')->default(1);$t->uuid('request_key');$t->string('request_hash',64);$t->timestamps();
            $t->unique(['company_id','user_id','request_key'],'personal_todo_list_request_unique');
        });
        Schema::create('personal_todo_settings',function(Blueprint $t){
            $t->id();$t->foreignId('company_id')->constrained();$t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('initial_view',20)->default('all');$t->string('default_sort',20)->default('due');$t->string('sidebar_view',20)->default('upcoming');
            $t->unsignedInteger('sidebar_limit')->default(10);$t->boolean('show_completed')->default(false);$t->unsignedInteger('version')->default(1);$t->timestamps();
            $t->unique(['company_id','user_id'],'personal_todo_settings_owner_unique');
        });
        Schema::table('personal_calendar_items',function(Blueprint $t){
            $t->foreignId('todo_list_id')->nullable()->constrained('personal_todo_lists');$t->boolean('is_important')->default(false);
            $t->date('focus_date')->nullable();$t->json('steps')->nullable();
            $t->index(['company_id','user_id','focus_date'],'personal_todo_owner_focus');
        });
    }
    public function down():void {
        Schema::table('personal_calendar_items',function(Blueprint $t){$t->dropForeign(['todo_list_id']);$t->dropIndex('personal_todo_owner_focus');$t->dropColumn(['todo_list_id','is_important','focus_date','steps']);});
        Schema::dropIfExists('personal_todo_settings');Schema::dropIfExists('personal_todo_lists');
    }
};
