<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up():void {
        Schema::create('personal_absence_plans',function(Blueprint $t){
            $t->id();$t->foreignId('company_id')->constrained();$t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->date('date');$t->string('kind',30);$t->dateTime('starts_at');$t->dateTime('ends_at');
            $t->unsignedInteger('version')->default(1);$t->uuid('request_key');$t->string('request_hash',64);$t->timestamps();$t->softDeletes();
            $t->unique(['company_id','user_id','request_key'],'absence_plan_request_unique');
            $t->index(['company_id','user_id','date'],'absence_plan_owner_date');
        });
    }
    public function down():void {Schema::dropIfExists('personal_absence_plans');}
};
