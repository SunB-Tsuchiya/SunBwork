<?php

namespace App\Http\Controllers\NSystem;

use App\Http\Controllers\Controller;
use App\Http\Requests\NSystem\NQuestionSearchRequest;
use App\Models\NSystem\NAnswersDaimon;
use App\Models\NSystem\NQuestionsDaimon;
use App\Models\NSystem\NSchool;
use App\Services\NSystem\NQuestionSearchService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NdemoController extends Controller
{
    private const SUBJECT_LABELS = [
        'Ko' => '国語',
        'Sa' => '算数',
        'Sh' => '社会',
        'Ri' => '理科',
    ];

    private const CATEGORY_ORDER = ['共学', '男子', '女子', '地方'];

    public function index(Request $request)
    {
        $schools = NSchool::orderBy('name')->get();

        $grouped = collect(self::CATEGORY_ORDER)
            ->mapWithKeys(function ($cat) use ($schools) {
                return [$cat => $schools->where('category', $cat)->values()];
            })
            ->filter(fn($list) => $list->isNotEmpty());

        return view('n_system.demo.index', compact('grouped'));
    }

    public function school(Request $request, int $id)
    {
        $school = NSchool::findOrFail($id);

        $tab = $request->get('tab', 'Ko');
        $mode = $request->get('mode', 'Q');
        if (! array_key_exists($tab, self::SUBJECT_LABELS)) {
            $tab = 'Ko';
        }

        $availableSubjects = $school->questions()
            ->select('subject')
            ->distinct()
            ->pluck('subject')
            ->toArray();

        $daimons = $mode === 'A'
            ? $school->answers()->where('subject', $tab)->orderBy('daimon_index')->get()
            : $school->questions()->where('subject', $tab)->orderBy('daimon_index')->get();

        $highlightInput = $request->input('highlight', []);
        $highlightTerms = collect(is_array($highlightInput) ? $highlightInput : [$highlightInput])
            ->filter(fn ($term) => is_string($term))
            ->map(fn (string $term) => mb_substr(trim($term), 0, 100))
            ->filter()
            ->unique()
            ->take(10)
            ->values()
            ->all();

        return view('n_system.demo.school', [
            'school'            => $school,
            'daimons'           => $daimons,
            'tab'               => $tab,
            'mode'              => $mode,
            'subjectLabels'     => self::SUBJECT_LABELS,
            'availableSubjects' => $availableSubjects,
            'highlightTerms'    => $highlightTerms,
        ]);
    }

    public function search(NQuestionSearchRequest $request, NQuestionSearchService $searchService): Response
    {
        $filters = $request->searchFilters();

        return Inertia::render('NSystem/Search', [
            'initialFilters' => $filters,
            'initialResults' => $searchService->search($filters),
            'schools' => NSchool::orderBy('name')->get(['id', 'name', 'year', 'category']),
            'subjectLabels' => self::SUBJECT_LABELS,
            'categories' => self::CATEGORY_ORDER,
            'isGuest' => ! auth()->check(),
        ]);
    }

    public function searchResults(NQuestionSearchRequest $request, NQuestionSearchService $searchService)
    {
        return response()->json($searchService->search($request->searchFilters()));
    }
}
