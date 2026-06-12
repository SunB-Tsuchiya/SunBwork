<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('is_company_event')->default(false)->after('interruption_minutes');
            $table->enum('visibility', ['private', 'company', 'group', 'public'])->default('private')->after('is_company_event');
            $table->foreignId('organizer_id')->nullable()->after('visibility')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('room_reservation_id')->nullable()->after('organizer_id')
                ->constrained('room_reservations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['organizer_id']);
            $table->dropForeign(['room_reservation_id']);
            $table->dropColumn(['is_company_event', 'visibility', 'organizer_id', 'room_reservation_id']);
        });
    }
};
