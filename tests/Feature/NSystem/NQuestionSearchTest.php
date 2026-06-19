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
            $table->string('code');
            $table->unsignedSmallInteger('year');
            $table->string('name');
            $table->string('category')->default('');
            $table->timestamps();
        });

        Schema::create('n_questions_daimon', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->string('subject', 5);
            $table->unsignedTinyInteger('daimon_index');
            $table->text('body_html');
            $table->text('body_text');
            $table->timestamps();
        });

        $this->insertSchool(1, 'A001', '青空中学校', '共学');
        $this->insertSchool(2, 'B001', '白雲中学校', '男子');
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('n_questions_daimon');
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

    private function insertSchool(int $id, string $code, string $name, string $category): void
    {
        DB::table('n_schools')->insert([
            'id' => $id,
            'code' => $code,
            'year' => 2024,
            'name' => $name,
            'category' => $category,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertQuestion(int $id, int $schoolId, string $subject, int $daimonIndex, string $bodyText): void
    {
        DB::table('n_questions_daimon')->insert([
            'id' => $id,
            'school_id' => $schoolId,
            'subject' => $subject,
            'daimon_index' => $daimonIndex,
            'body_html' => '<p>非公開HTML</p>',
            'body_text' => $bodyText,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
