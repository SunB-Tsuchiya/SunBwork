<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // regular: 正社員, contract: 契約社員, dispatch: 派遣社員, outsource: 業務委託
            $table->enum('employment_type', ['regular', 'contract', 'dispatch', 'outsource'])
                ->default('regular')
                ->after('user_role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('employment_type');
        });
    }
};
