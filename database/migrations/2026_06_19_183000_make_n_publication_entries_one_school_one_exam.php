<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('n_publication_entries')) {
            return;
        }

        Schema::table('n_publication_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('n_publication_entries', 'school_id')) {
                $table->foreignId('school_id')->nullable()->after('publication_edition_id')->constrained('n_schools')->cascadeOnDelete();
            }

            if (! Schema::hasColumn('n_publication_entries', 'exam_id')) {
                $table->foreignId('exam_id')->nullable()->after('school_id')->constrained('n_exams')->cascadeOnDelete();
            }
        });

        if (Schema::hasTable('n_publication_entry_exams') && DB::getDriverName() !== 'sqlite') {
            DB::statement(
                'UPDATE n_publication_entries pe
                INNER JOIN n_publication_entry_exams pee ON pee.publication_entry_id = pe.id
                INNER JOIN n_exams e ON e.id = pee.exam_id
                INNER JOIN n_exam_series es ON es.id = e.exam_series_id
                SET pe.exam_id = pee.exam_id,
                    pe.school_id = es.school_id
                WHERE pe.exam_id IS NULL OR pe.school_id IS NULL'
            );
        }

        Schema::table('n_publication_entries', function (Blueprint $table) {
            $table->dropUnique(['publication_edition_id', 'mikuni_code']);
        });

        if (Schema::hasTable('n_publication_entry_exams')) {
            Schema::drop('n_publication_entry_exams');
        }

        Schema::table('n_publication_entries', function (Blueprint $table) {
            $table->unique(['publication_edition_id', 'exam_id']);
            $table->unique(['publication_edition_id', 'mikuni_code', 'exam_id'], 'n_pub_entries_year_mikuni_exam_unique');
            $table->index(['publication_edition_id', 'mikuni_code', 'sort_order'], 'n_pub_entries_year_mikuni_sort_idx');
            $table->index(['publication_edition_id', 'school_id'], 'n_pub_entries_year_school_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('n_publication_entries')) {
            return;
        }

        Schema::create('n_publication_entry_exams', function (Blueprint $table) {
            $table->foreignId('publication_entry_id')->constrained('n_publication_entries')->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained('n_exams')->cascadeOnDelete();
            $table->boolean('is_primary')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['publication_entry_id', 'exam_id']);
        });

        if (Schema::hasColumn('n_publication_entries', 'exam_id')) {
            DB::table('n_publication_entries')
                ->whereNotNull('exam_id')
                ->orderBy('id')
                ->get(['id', 'exam_id'])
                ->each(function (object $entry): void {
                    DB::table('n_publication_entry_exams')->insert([
                        'publication_entry_id' => $entry->id,
                        'exam_id' => $entry->exam_id,
                        'is_primary' => true,
                        'created_at' => now(),
                    ]);
                });
        }

        Schema::table('n_publication_entries', function (Blueprint $table) {
            $table->dropUnique(['publication_edition_id', 'exam_id']);
            $table->dropUnique('n_pub_entries_year_mikuni_exam_unique');
            $table->dropIndex('n_pub_entries_year_mikuni_sort_idx');
            $table->dropIndex('n_pub_entries_year_school_idx');
            $table->dropForeign(['school_id']);
            $table->dropForeign(['exam_id']);
            $table->dropColumn(['school_id', 'exam_id']);
            $table->unique(['publication_edition_id', 'mikuni_code']);
        });
    }
};
