<?php

namespace Tests\Feature\NSystem;

use App\Http\Middleware\NSystem\GuestAuth;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NQuestionSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('n_schools', function (Blueprint $table) {
            $table->id();
            $table->string('n_code_prefix');
            $table->string('canonical_name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('n_school_years', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedSmallInteger('admission_year');
            $table->string('school_name');
            $table->string('normalized_name');
            $table->string('gender_type');
            $table->timestamps();
        });

        Schema::create('n_exam_series', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->string('series_key');
            $table->timestamps();
        });

        Schema::create('n_exams', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_series_id');
            $table->unsignedSmallInteger('admission_year');
            $table->string('n_code');
            $table->timestamps();
        });

        Schema::create('n_publication_editions', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('admission_year');
            $table->string('title');
            $table->string('source_filename')->nullable();
            $table->timestamps();
        });

        Schema::create('n_publication_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('publication_edition_id');
            $table->unsignedBigInteger('school_id')->nullable();
            $table->unsignedBigInteger('exam_id')->nullable();
            $table->unsignedSmallInteger('mikuni_code');
            $table->string('publication_section');
            $table->unsignedSmallInteger('sort_order');
            $table->string('printed_school_name');
            $table->string('printed_exam_label')->nullable();
            $table->unsignedSmallInteger('source_row_number')->nullable();
            $table->text('source_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('n_exam_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_id');
            $table->string('subject', 5);
            $table->char('document_type', 1);
            $table->timestamps();
        });

        Schema::create('n_exam_daimons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_document_id');
            $table->unsignedTinyInteger('daimon_index');
            $table->text('body_html');
            $table->text('body_text');
            $table->timestamps();
        });

        $this->insertSchool(1, 'A001', '青空中学校', '共学');
        $this->insertSchool(2, 'B001', '白雲中学校', '男子');
        $this->insertPublicationEdition(1, 2024);
        $this->insertPublicationEntry(1, 1, 1, 102, '共学', 2, '青空中学校');
        $this->insertPublicationEntry(2, 1, 2, 101, '共学', 1, '白雲中学校');
        $this->insertQuestion(101, 1, 'Ko', 1, '青空の問題');
        $this->insertQuestion(102, 2, 'Ko', 1, '白雲の問題');
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('n_exam_daimons');
        Schema::dropIfExists('n_exam_documents');
        Schema::dropIfExists('n_publication_entries');
        Schema::dropIfExists('n_publication_editions');
        Schema::dropIfExists('n_exams');
        Schema::dropIfExists('n_exam_series');
        Schema::dropIfExists('n_school_years');
        Schema::dropIfExists('n_schools');
        parent::tearDown();
    }

    public function test_unauthenticated_user_is_redirected_from_results_api(): void
    {
        $this->get(route('n-demo.search.results', ['q' => '平安時代']))
            ->assertRedirect(route('n-guest.login', ['for' => 'n-demo']));
    }

    public function test_exact_mode_does_not_return_an_ngram_only_match(): void
    {
        $this->insertQuestion(1, 1, 'Sh', 1, '平安時代の文化について答えなさい。');
        $this->insertQuestion(2, 1, 'Sh', 2, '大正時代の文化について答えなさい。');

        $response = $this->withoutMiddleware(GuestAuth::class)
            ->getJson(route('n-demo.search.results', ['q' => '平安時代', 'mode' => 'exact']));

        $response->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('items.0.id', 1)
            ->assertJsonPath('items.0.snippet.match', '平安時代');

        parse_str(parse_url($response->json('items.0.url'), PHP_URL_QUERY), $linkQuery);
        $this->assertSame(['平安時代'], $linkQuery['highlight']);
    }

    public function test_search_page_is_rendered_as_the_nsystem_inertia_page(): void
    {
        $this->withoutMiddleware(GuestAuth::class)
            ->get(route('n-demo.search'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // config/inertia.php currently points tests at lowercase js/pages.
                ->component('NSystem/Search', false)
                ->has('schools', 2)
                ->where('initialFilters.mode', 'exact')
                ->where('initialResults.pagination.total', 0));
    }

    public function test_school_index_uses_the_selected_year_and_mikuni_order(): void
    {
        $this->withoutMiddleware(GuestAuth::class)
            ->get(route('n-demo.index'))
            ->assertOk()
            ->assertSee('2024年度')
            ->assertSee('Mコード 101')
            ->assertSee('Nコード A001')
            ->assertSeeInOrder(['Mコード 101', 'Mコード 102']);
    }

    public function test_school_index_shows_only_document_available_year_buttons(): void
    {
        $this->insertSchool(3, 'C001', '緑丘中学校', '共学', 2025);
        $this->insertPublicationEdition(2, 2025);
        $this->insertPublicationEntry(3, 2, 3, 101, '共学', 1, '緑丘中学校');

        $this->withoutMiddleware(GuestAuth::class)
            ->get(route('n-demo.index'))
            ->assertOk()
            ->assertSee('2024年度')
            ->assertDontSee('href="' . route('n-demo.index', ['year' => 2025]) . '"', false);
    }

    public function test_school_page_reads_daimons_from_the_exam_document(): void
    {
        $this->insertQuestion(1, 1, 'Ko', 1, '本文表示テスト');

        $this->withoutMiddleware(GuestAuth::class)
            ->get(route('n-demo.school', ['id' => 1, 'tab' => 'Ko', 'mode' => 'Q']))
            ->assertOk()
            ->assertSee('Nコード A001')
            ->assertSee('非公開HTML');
    }

    public function test_all_and_any_modes_have_distinct_meanings(): void
    {
        $this->insertQuestion(1, 1, 'Sh', 1, '平安時代の京都を扱う問題です。');
        $this->insertQuestion(2, 1, 'Sh', 2, '京都の地理を扱う問題です。');

        $this->withoutMiddleware(GuestAuth::class)
            ->getJson(route('n-demo.search.results', ['q' => '平安 京都', 'mode' => 'all']))
            ->assertJsonPath('pagination.total', 1);

        $this->withoutMiddleware(GuestAuth::class)
            ->getJson(route('n-demo.search.results', ['q' => '平安 京都', 'mode' => 'any']))
            ->assertJsonPath('pagination.total', 2);
    }

    public function test_percent_and_underscore_are_searched_as_literal_characters(): void
    {
        $this->insertQuestion(1, 1, 'Sa', 1, '濃度は10%です。');
        $this->insertQuestion(2, 1, 'Sa', 2, '割合を求めなさい。');
        $this->insertQuestion(3, 1, 'Ko', 3, '記号_Aを選びなさい。');

        $this->withoutMiddleware(GuestAuth::class)
            ->getJson(route('n-demo.search.results', ['q' => '%', 'mode' => 'exact']))
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('items.0.id', 1);

        $this->withoutMiddleware(GuestAuth::class)
            ->getJson(route('n-demo.search.results', ['q' => '_', 'mode' => 'exact']))
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('items.0.id', 3);
    }

    public function test_subject_school_and_category_filters_are_applied(): void
    {
        $this->insertQuestion(1, 1, 'Sh', 1, '歴史の問題');
        $this->insertQuestion(2, 2, 'Ri', 1, '歴史の問題');

        $this->withoutMiddleware(GuestAuth::class)
            ->getJson(route('n-demo.search.results', [
                'q' => '歴史',
                'mode' => 'exact',
                'subject' => 'Ri',
                'school_id' => 2,
                'category' => '男子',
            ]))
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('items.0.school.id', 2);
    }

    public function test_results_are_paginated_and_do_not_expose_body_html(): void
    {
        foreach (range(1, 21) as $index) {
            $this->insertQuestion($index, 1, 'Ko', $index, "検索対象 {$index}");
        }

        $response = $this->withoutMiddleware(GuestAuth::class)
            ->getJson(route('n-demo.search.results', ['q' => '検索対象', 'mode' => 'exact']));

        $response->assertOk()
            ->assertJsonCount(20, 'items')
            ->assertJsonPath('pagination.total', 21)
            ->assertJsonPath('pagination.last_page', 2)
            ->assertJsonMissingPath('items.0.body_html');
    }

    private function insertSchool(int $id, string $code, string $name, string $category, int $year = 2024): void
    {
        DB::table('n_schools')->insert([
            'id' => $id,
            'n_code_prefix' => substr($code, 0, 3),
            'canonical_name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('n_school_years')->insert([
            'school_id' => $id,
            'admission_year' => $year,
            'school_name' => $name,
            'normalized_name' => $name,
            'gender_type' => match ($category) {
                '共学' => 'coed', '男子' => 'boys', '女子' => 'girls', default => 'unknown',
            },
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('n_exam_series')->insert([
            'id' => $id,
            'school_id' => $id,
            'series_key' => 'n-' . strtolower($code),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('n_exams')->insert([
            'id' => $id,
            'exam_series_id' => $id,
            'admission_year' => $year,
            'n_code' => $code,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertPublicationEdition(int $id, int $year): void
    {
        DB::table('n_publication_editions')->insert([
            'id' => $id,
            'admission_year' => $year,
            'title' => "{$year}年度版",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertPublicationEntry(int $id, int $editionId, int $examId, int $mikuniCode, string $section, int $sortOrder, string $printedSchoolName): void
    {
        DB::table('n_publication_entries')->insert([
            'id' => $id,
            'publication_edition_id' => $editionId,
            'school_id' => $examId,
            'exam_id' => $examId,
            'mikuni_code' => $mikuniCode,
            'publication_section' => $section,
            'sort_order' => $sortOrder,
            'printed_school_name' => $printedSchoolName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertQuestion(int $id, int $schoolId, string $subject, int $daimonIndex, string $bodyText): void
    {
        $documentId = $schoolId * 10 + array_search($subject, ['Ko', 'Sa', 'Sh', 'Ri'], true);
        DB::table('n_exam_documents')->updateOrInsert(
            ['id' => $documentId],
            [
                'exam_id' => $schoolId,
                'subject' => $subject,
                'document_type' => 'Q',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        DB::table('n_exam_daimons')->insert([
            'id' => $id,
            'exam_document_id' => $documentId,
            'daimon_index' => $daimonIndex,
            'body_html' => '<p>非公開HTML</p>',
            'body_text' => $bodyText,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
