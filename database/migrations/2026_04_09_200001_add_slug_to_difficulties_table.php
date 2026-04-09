<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite: add column without default constraint issues
            Schema::table('difficulties', function (Blueprint $table) {
                $table->string('slug', 50)->nullable()->after('name');
            });
        } else {
            Schema::table('difficulties', function (Blueprint $table) {
                $table->string('slug', 50)->nullable()->after('name');
            });
        }

        // Backfill slug for standard difficulty names
        $map = [
            '軽い' => 'light',
            '普通' => 'normal',
            '重い' => 'heavy',
            '重大' => 'serious',
        ];
        foreach ($map as $name => $slug) {
            DB::table('difficulties')->where('name', $name)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('difficulties', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
