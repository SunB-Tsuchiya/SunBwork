<?php

namespace App\Http\Controllers\NSystem;

use App\Http\Controllers\Controller;
use App\Http\Requests\NSystem\NQuestionSearchRequest;
use App\Models\NSystem\NExam;
use App\Models\NSystem\NPublicationEdition;
use App\Models\NSystem\NPublicationEntry;
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
        $availableYears = NPublicationEdition::query()
            ->whereHas('publicationEntries.exam.documents')
            ->orderBy('admission_year')
            ->pluck('admission_year')
            ->values();

        $selectedYear = (int) $request->integer('year', (int) ($availableYears->last() ?? 2024));
        if (! $availableYears->contains($selectedYear)) {
            $selectedYear = (int) ($availableYears->last() ?? 2024);
        }

        $schools = NPublicationEntry::query()
            ->with([
                'exam',
                'school.schoolYears' => fn ($query) => $query->where('admission_year', $selectedYear),
            ])
            ->whereHas('publicationEdition', fn ($query) => $query->where('admission_year', $selectedYear))
            ->whereHas('exam.documents')
            ->orderBy('mikuni_code')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (NPublicationEntry $entry) => $this->publicationEntryView($entry, $selectedYear));

        $grouped = collect(self::CATEGORY_ORDER)
            ->mapWithKeys(function ($cat) use ($schools) {
                return [$cat => $schools->where('category', $cat)->values()];
            })
            ->filter(fn($list) => $list->isNotEmpty());

        return view('n_system.demo.index', [
            'grouped' => $grouped,
            'availableYears' => $availableYears,
            'selectedYear' => $selectedYear,
        ]);
    }

    public function school(Request $request, int $id): Response
    {
        $exam = NExam::with(['examSeries.school.schoolYears'])->findOrFail($id);
        $schoolObj = $this->examView($exam);

        $tab = $request->get('tab', 'Ko');
        $mode = $request->get('mode', 'Q');
        if (! array_key_exists($tab, self::SUBJECT_LABELS)) {
            $tab = 'Ko';
        }

        $availableSubjects = $exam->documents()
            ->where('document_type', 'Q')
            ->select('subject')
            ->distinct()
            ->pluck('subject')
            ->toArray();

        $document = $exam->documents()
            ->where('document_type', $mode === 'A' ? 'A' : 'Q')
            ->where('subject', $tab)
            ->first();
        $daimons = $document?->daimons()->orderBy('daimon_index')->get() ?? collect();

        $highlightInput = $request->input('highlight', []);
        $highlightTerms = collect(is_array($highlightInput) ? $highlightInput : [$highlightInput])
            ->filter(fn ($term) => is_string($term))
            ->map(fn (string $term) => mb_substr(trim($term), 0, 100))
            ->filter()
            ->unique()
            ->take(10)
            ->values()
            ->all();

        $assetBase = asset('n_images/');

        return Inertia::render('NSystem/School', [
            'school' => [
                'id'        => $schoolObj->id,
                'school_id' => $schoolObj->school_id,
                'code'      => $schoolObj->code,
                'name'      => $schoolObj->name,
                'year'      => $schoolObj->year,
                'category'  => $schoolObj->category,
            ],
            'daimons' => $daimons->map(fn ($d) => [
                'id'           => $d->id,
                'daimon_index' => $d->daimon_index,
                'body_html'    => str_replace('src="/n_images/', 'src="' . $assetBase, $d->body_html),
            ])->values(),
            'tab'               => $tab,
            'mode'              => $mode,
            'subjectLabels'     => self::SUBJECT_LABELS,
            'availableSubjects' => $availableSubjects,
            'highlightTerms'    => $highlightTerms,
            'isGuest'           => ! auth()->check(),
        ]);
    }

    public function search(NQuestionSearchRequest $request, NQuestionSearchService $searchService): Response
    {
        $filters = $request->searchFilters();

        return Inertia::render('NSystem/Search', [
            'initialFilters' => $filters,
            'initialResults' => $searchService->search($filters),
            'schools' => $this->searchSchools(),
            'subjectLabels' => self::SUBJECT_LABELS,
            'categories' => self::CATEGORY_ORDER,
            'isGuest' => ! auth()->check(),
        ]);
    }

    public function searchResults(NQuestionSearchRequest $request, NQuestionSearchService $searchService)
    {
        return response()->json($searchService->search($request->searchFilters()));
    }

    private function searchSchools()
    {
        return NSchool::query()
            ->whereHas('examSeries.exams.documents')
            ->with([
                'schoolYears' => fn ($query) => $query->orderByDesc('admission_year'),
                'examSeries.exams' => fn ($query) => $query
                    ->select(['id', 'exam_series_id', 'admission_year'])
                    ->whereHas('documents'),
            ])
            ->orderBy('canonical_name')
            ->get()
            ->map(function (NSchool $school) {
                $documentYear = $school->examSeries
                    ->flatMap(fn ($series) => $series->exams)
                    ->pluck('admission_year')
                    ->filter()
                    ->sortDesc()
                    ->first();
                $schoolYear = $school->schoolYears->firstWhere('admission_year', $documentYear) ?? $school->schoolYears->first();

                return [
                    'id' => $school->id,
                    'name' => $schoolYear?->school_name ?? $school->canonical_name,
                    'year' => $schoolYear?->admission_year,
                    'category' => $this->categoryLabel($schoolYear?->gender_type),
                ];
            });
    }

    private function examView(NExam $exam): object
    {
        $school = $exam->examSeries->school;
        $schoolYear = $school->schoolYears->firstWhere('admission_year', $exam->admission_year);

        return (object) [
            'id' => $exam->id,
            'school_id' => $school->id,
            'code' => $exam->n_code,
            'name' => $schoolYear?->school_name ?? $school->canonical_name,
            'year' => $exam->admission_year,
            'category' => $this->categoryLabel($schoolYear?->gender_type),
        ];
    }

    private function publicationEntryView(NPublicationEntry $entry, int $year): object
    {
        $school = $entry->school;
        $schoolYear = $school?->schoolYears->firstWhere('admission_year', $year);

        return (object) [
            'id' => $entry->exam_id,
            'school_id' => $entry->school_id,
            'code' => $entry->exam?->n_code,
            'name' => $schoolYear?->school_name ?? $entry->printed_school_name ?? $school?->canonical_name,
            'year' => $year,
            'mikuni_code' => $entry->mikuni_code,
            'category' => $entry->publication_section,
        ];
    }

    private function categoryLabel(?string $genderType): string
    {
        return match ($genderType) {
            'coed' => '共学',
            'boys' => '男子',
            'girls' => '女子',
            default => '地方',
        };
    }
}
