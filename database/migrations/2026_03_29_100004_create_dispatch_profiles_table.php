<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatch_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('agency_name')->nullable()->comment('派遣会社名 / 業務委託先名');
            $table->date('contract_start')->nullable()->comment('契約開始日');
            $table->date('contract_end')->nullable()->comment('契約終了日');
            $table->text('notes')->nullable()->comment('備考');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_profiles');
    }
};
