<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mginbon')->create('mginbon_legacy_actor_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mginbon_project_id')->constrained('mginbon_projects')->cascadeOnDelete();
            $table->string('legacy_value', 255);
            $table->string('target_type', 30);
            $table->unsignedBigInteger('target_id')->nullable()->comment('SBWork本体DBのusers.idまたはsubcontractors.id');
            $table->string('target_label', 255)->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable()->comment('SBWork本体DBのusers.id');
            $table->timestamps();
            $table->unique(['mginbon_project_id', 'legacy_value'], 'mginbon_actor_mapping_project_value_unique');
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('mginbon')->dropIfExists('mginbon_legacy_actor_mappings');
    }
};
