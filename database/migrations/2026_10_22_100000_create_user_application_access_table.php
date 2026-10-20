<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 申請機能（交通費など）を、誰が使えるか（申請者管理。Admin がユーザーごとにスイッチで決める）。
     * 派遣・契約の方は、申請が必要な人と、必要でない人がいるため、全員には出さない。
     * 初期値: いま「申請」が見えている人（SuperAdmin・Admin・Clerk の全員と、user_id 24）を ON にする。
     */
    public function up(): void
    {
        Schema::create('user_application_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key', 30);                       // 申請の種類（transport = 交通費）
            $table->boolean('enabled')->default(false);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'feature_key']);
        });

        $ids = DB::table('users')
            ->where(fn ($q) => $q->whereNull('is_ghost')->orWhere('is_ghost', false))
            ->where(fn ($q) => $q->whereIn('user_role', ['superadmin', 'admin', 'clerk'])->orWhere('id', 24))
            ->pluck('id');
        $now = now();
        foreach ($ids as $id) {
            DB::table('user_application_access')->insert([
                'user_id' => $id, 'feature_key' => 'transport', 'enabled' => true, 'changed_by' => null,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_application_access');
    }
};
