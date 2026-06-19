<?php

namespace App\Services\NSystem;

use App\Models\NSystem\NQuestionsDaimon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class NQuestionSearchService
{
    public const PER_PAGE = 20;

    public function search(array $filters): array
    {
        $queryText = $filters['q'] ?? '';
        $mode = $filters['mode'] ?? 'exact';

        if ($queryText === '') {
            return $this->emptyResult($filters);
        }

        $terms = $this->terms($queryText, $mode);
        $query = NQuestionsDaimon::query()
            ->select('n_questions_daimon.*')
            ->with('school:id,name,year,category');

        $this->applyFilters($query, $filters);
        $fullTextQuery = $this->applyFullTextCandidateFilter($query, $terms, $mode);
        $this->applyLiteralMatch($query, $terms, $mode);

        if ($fullTextQuery !== null) {
            $query->selectRaw(
                'MATCH(n_questions_daimon.body_text) AGAINST (? IN BOOLEAN MODE) AS relevance',
                [$fullTextQuery]
            );
        }

        $subjectOrder = "CASE subject WHEN 'Ko' THEN 1 WHEN 'Sa' THEN 2 WHEN 'Sh' THEN 3 WHEN 'Ri' THEN 4 ELSE 5 END";
        $paginator = $query
            ->join('n_schools', 'n_schools.id', '=', 'n_questions_daimon.school_id')
            ->when($fullTextQuery !== null, fn (Builder $builder) => $builder->orderByDesc('relevance'))
            ->orderBy('n_schools.name')
            ->orderByRaw($subjectOrder)
            ->orderBy('daimon_index')
            ->paginate(self::PER_PAGE, ['*'], 'page', $filters['page'] ?? 1);

        $items = $paginator->getCollection()->map(function (NQuestionsDaimon $question) use ($terms) {
            return [
                'id' => $question->id,
                'school' => [
                    'id' => $question->school->id,
                    'name' => $question->school->name,
                    'year' => $question->school->year,
                    'category' => $question->school->category,
                ],
                'subject' => $question->subject,
                'daimon_index' => $question->daimon_index,
                'url' => route('n-demo.school', [
                    'id' => $question->school->id,
                    'tab' => $question->subject,
                    'mode' => 'Q',
                    'highlight' => $terms,
                ]) . '#daimon-' . $question->daimon_index,
                'snippet' => $this->snippet($question->body_text, $terms),
            ];
        })->values()->all();

        return [
            'items' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'filters' => $filters,
        ];
    }

    public function terms(string $query, string $mode): array
    {
        if ($mode === 'exact') {
            return [$query];
        }

        return array_values(array_unique(preg_split('/[\s　]+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: []));
    }

    public function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    public function snippet(string $text, array $terms, int $contextLength = 100): array
    {
        $matchedTerm = '';
        $matchedPosition = null;

        foreach ($terms as $term) {
            $position = mb_stripos($text, $term);
            if ($position !== false && ($matchedPosition === null || $position < $matchedPosition)) {
                $matchedPosition = $position;
                $matchedTerm = mb_substr($text, $position, mb_strlen($term));
            }
        }

        if ($matchedPosition === null) {
            return [
                'before' => mb_substr($text, 0, $contextLength * 2),
                'match' => '',
                'after' => '',
                'leading_ellipsis' => false,
                'trailing_ellipsis' => mb_strlen($text) > $contextLength * 2,
            ];
        }

        $start = max(0, $matchedPosition - $contextLength);
        $afterStart = $matchedPosition + mb_strlen($matchedTerm);
        $after = mb_substr($text, $afterStart, $contextLength);

        return [
            'before' => mb_substr($text, $start, $matchedPosition - $start),
            'match' => $matchedTerm,
            'after' => $after,
            'leading_ellipsis' => $start > 0,
            'trailing_ellipsis' => $afterStart + mb_strlen($after) < mb_strlen($text),
        ];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['subject'] ?? null, fn (Builder $builder, string $subject) => $builder->where('subject', $subject))
            ->when($filters['school_id'] ?? null, fn (Builder $builder, int $schoolId) => $builder->where('school_id', $schoolId))
            ->when($filters['category'] ?? null, function (Builder $builder, string $category) {
                $builder->whereHas('school', fn (Builder $school) => $school->where('category', $category));
            });
    }

    private function applyLiteralMatch(Builder $query, array $terms, string $mode): void
    {
        $method = $mode === 'any' ? 'orWhereRaw' : 'whereRaw';

        $query->where(function (Builder $builder) use ($terms, $method) {
            foreach ($terms as $term) {
                $builder->{$method}("n_questions_daimon.body_text LIKE ? ESCAPE '!'", ['%' . $this->escapeLike($term) . '%']);
            }
        });
    }

    private function applyFullTextCandidateFilter(Builder $query, array $terms, string $mode): ?string
    {
        $cannotUseFullText = DB::connection()->getDriverName() !== 'mysql'
            || collect($terms)->contains(fn (string $term) => mb_strlen($term) < 2)
            || collect($terms)->contains(fn (string $term) => preg_match('/^[\p{L}\p{N}\s　]+$/u', $term) !== 1);

        if ($cannotUseFullText) {
            return null;
        }

        $quotedTerms = array_map(function (string $term) {
            $term = str_replace(['\\', '"'], ['\\\\', '\\"'], $term);
            return '"' . $term . '"';
        }, $terms);

        $booleanQuery = $mode === 'all'
            ? implode(' ', array_map(fn (string $term) => '+' . $term, $quotedTerms))
            : implode(' ', $quotedTerms);

        $query->whereRaw('MATCH(n_questions_daimon.body_text) AGAINST (? IN BOOLEAN MODE)', [$booleanQuery]);

        return $booleanQuery;
    }

    private function emptyResult(array $filters): array
    {
        return [
            'items' => [],
            'pagination' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => self::PER_PAGE,
                'total' => 0,
                'from' => null,
                'to' => null,
            ],
            'filters' => $filters,
        ];
    }
}
