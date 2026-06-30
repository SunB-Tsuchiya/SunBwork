<?php

namespace App\Http\Controllers\ProofCoordinator;

use App\Http\Controllers\Controller;
use App\Models\ProjectJob;
use App\Models\ProofReservation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProofReservationController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $period = (string) $request->input('period', '');
        $dateField = (string) $request->input('date_field', 'requested_at');
        $sortOrder = $request->input('sort_order') === 'asc' ? 'asc' : 'desc';
        $dateField = in_array($dateField, ['requested_at', 'deadline_at'], true)
            ? $dateField
            : 'requested_at';

        $query = ProofReservation::with(['requester:id,name', 'projectJob.client'])
            ->orderBy('created_at', $sortOrder);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('requested_at_text', 'like', "%{$search}%")
                    ->orWhere('deadline_text', 'like', "%{$search}%")
                    ->orWhere('note', 'like', "%{$search}%")
                    ->orWhereHas('projectJob', function ($projectQuery) use ($search) {
                        $projectQuery->where('title', 'like', "%{$search}%")
                            ->orWhereHas('client', fn ($clientQuery) => $clientQuery->where('name', 'like', "%{$search}%"));
                    });
            });
        }

        if ($period !== '') {
            $query->whereRaw("DATE_FORMAT({$dateField}, '%Y-%m') = ?", [$period]);
        }

        $monthOptions = ProofReservation::query()
            ->whereNotNull($dateField)
            ->selectRaw("DATE_FORMAT({$dateField}, '%Y-%m') as value")
            ->groupByRaw("DATE_FORMAT({$dateField}, '%Y-%m')")
            ->orderByRaw("DATE_FORMAT({$dateField}, '%Y-%m') DESC")
            ->pluck('value')
            ->map(fn ($month) => [
                'value' => $month,
                'label' => Carbon::createFromFormat('Y-m', $month)->format('Y年n月'),
            ])
            ->values()
            ->all();

        return Inertia::render('ProofCoordinator/Reservations/Index', [
            'reservations' => $query->get(),
            'search' => $search,
            'period' => $period,
            'dateField' => $dateField,
            'sortOrder' => $sortOrder,
            'monthOptions' => $monthOptions,
        ]);
    }

    public function store(Request $request, ProjectJob $projectJob): RedirectResponse
    {
        $this->authorizeProject($request, $projectJob);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'requested_at_mode' => ['required', Rule::in(['datetime', 'text'])],
            'requested_at' => ['nullable', 'date'],
            'requested_at_text' => ['nullable', 'string', 'max:255'],
            'deadline_mode' => ['required', Rule::in(['datetime', 'text'])],
            'deadline_at' => ['nullable', 'date'],
            'deadline_text' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'duplicate_confirmed' => ['nullable', 'boolean'],
        ]);

        $this->validateModeValue(
            $data,
            'requested_at_mode',
            'requested_at',
            'requested_at_text',
            '依頼予定',
        );
        $this->validateModeValue(
            $data,
            'deadline_mode',
            'deadline_at',
            'deadline_text',
            '締め切り',
        );

        if (
            $data['requested_at_mode'] === 'datetime'
            && $data['deadline_mode'] === 'datetime'
            && Carbon::parse($data['requested_at'])->gte(Carbon::parse($data['deadline_at']))
        ) {
            throw ValidationException::withMessages([
                'deadline_at' => '締め切り日時は依頼予定日時より後にしてください。',
            ]);
        }

        $duplicates = $this->findDuplicates($projectJob, $data);
        if ($duplicates->isNotEmpty() && ! ($data['duplicate_confirmed'] ?? false)) {
            throw ValidationException::withMessages([
                'duplicate' => '同じ案件に重複する可能性がある校正予約があります。確認してから送信してください。',
            ]);
        }

        ProofReservation::create([
            'project_job_id' => $projectJob->id,
            'requester_id' => Auth::id(),
            'title' => $data['title'],
            'requested_at_mode' => $data['requested_at_mode'],
            'requested_at' => $data['requested_at_mode'] === 'datetime' ? $data['requested_at'] : null,
            'requested_at_text' => $data['requested_at_mode'] === 'text' ? trim($data['requested_at_text']) : null,
            'deadline_mode' => $data['deadline_mode'],
            'deadline_at' => $data['deadline_mode'] === 'datetime' ? $data['deadline_at'] : null,
            'deadline_text' => $data['deadline_mode'] === 'text' ? trim($data['deadline_text']) : null,
            'note' => $data['note'] ?? null,
        ]);

        return back()->with('success', '校正予約を送りました。');
    }

    public function sent(Request $request, ProjectJob $projectJob): JsonResponse
    {
        $this->authorizeProject($request, $projectJob);

        $reservations = ProofReservation::with('requester:id,name')
            ->where('project_job_id', $projectJob->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ProofReservation $reservation) => [
                'id' => $reservation->id,
                'title' => $reservation->title,
                'requested_at_mode' => $reservation->requested_at_mode,
                'requested_at' => $reservation->requested_at,
                'requested_at_text' => $reservation->requested_at_text,
                'deadline_mode' => $reservation->deadline_mode,
                'deadline_at' => $reservation->deadline_at,
                'deadline_text' => $reservation->deadline_text,
                'status' => $reservation->status,
                'calendar_registered_at' => $reservation->calendar_registered_at,
                'requester_name' => $reservation->requester?->name,
                'created_at' => $reservation->created_at,
            ]);

        return response()->json(['reservations' => $reservations]);
    }

    public function checkDuplicate(Request $request, ProjectJob $projectJob): JsonResponse
    {
        $this->authorizeProject($request, $projectJob);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'requested_at_mode' => ['required', Rule::in(['datetime', 'text'])],
            'requested_at' => ['nullable', 'date'],
            'deadline_mode' => ['required', Rule::in(['datetime', 'text'])],
            'deadline_at' => ['nullable', 'date'],
        ]);

        $duplicates = $this->findDuplicates($projectJob, $data)
            ->map(fn (ProofReservation $reservation) => [
                'id' => $reservation->id,
                'title' => $reservation->title,
                'title_match' => $reservation->title === $data['title'],
                'date_match' => $this->hasSameConfirmedDates($reservation, $data),
            ])
            ->values();

        return response()->json([
            'has_duplicates' => $duplicates->isNotEmpty(),
            'duplicates' => $duplicates,
        ]);
    }

    public function show(ProofReservation $reservation): Response
    {
        $reservation->load(['requester:id,name', 'projectJob.client']);

        return Inertia::render('ProofCoordinator/Reservations/Show', [
            'reservation' => $reservation,
            'canRegisterToCalendar' => $reservation->canRegisterToCalendar(),
        ]);
    }

    public function registerCalendar(ProofReservation $reservation): RedirectResponse
    {
        if (! $reservation->canRegisterToCalendar()) {
            throw ValidationException::withMessages([
                'calendar' => '依頼予定と締め切りの両方が確定日時の場合のみ登録できます。',
            ]);
        }

        $reservation->update(['calendar_registered_at' => now()]);

        return back()->with('success', '校正カレンダーに登録しました。');
    }

    public function updateStatus(Request $request, ProofReservation $reservation): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['reserved', 'in_progress', 'completed', 'deleted'])],
        ]);

        $reservation->update(['status' => $data['status']]);

        $messages = [
            'reserved' => '予約受付に戻しました。',
            'in_progress' => '校正中に変更しました。',
            'completed' => '完了に変更しました。',
            'deleted' => '削除に変更しました。',
        ];

        return back()->with('success', $messages[$data['status']]);
    }

    private function validateModeValue(
        array $data,
        string $modeKey,
        string $dateTimeKey,
        string $textKey,
        string $label,
    ): void {
        $mode = $data[$modeKey];
        $value = $mode === 'datetime'
            ? ($data[$dateTimeKey] ?? null)
            : trim((string) ($data[$textKey] ?? ''));

        if (! $value) {
            $key = $mode === 'datetime' ? $dateTimeKey : $textKey;
            throw ValidationException::withMessages([$key => "{$label}を入力してください。"]);
        }
    }

    private function authorizeProject(Request $request, ProjectJob $projectJob): void
    {
        $user = $request->user();
        $canReserve = $user->isAdmin()
            || $user->isSuperAdmin()
            || $user->isClerk()
            || (int) $projectJob->user_id === (int) $user->id
            || $projectJob->coordinators()->where('users.id', $user->id)->exists();

        abort_unless($canReserve, 403);
    }

    private function findDuplicates(ProjectJob $projectJob, array $data)
    {
        $title = trim((string) ($data['title'] ?? ''));
        $hasConfirmedDates = ($data['requested_at_mode'] ?? null) === 'datetime'
            && ($data['deadline_mode'] ?? null) === 'datetime'
            && ! empty($data['requested_at'])
            && ! empty($data['deadline_at']);

        return ProofReservation::query()
            ->where('project_job_id', $projectJob->id)
            ->where(function (Builder $query) use ($title, $hasConfirmedDates, $data) {
                $query->where('title', $title);

                if ($hasConfirmedDates) {
                    [$requestedStart, $requestedEnd] = $this->jstDayUtcRange($data['requested_at']);
                    [$deadlineStart, $deadlineEnd] = $this->jstDayUtcRange($data['deadline_at']);

                    $query->orWhere(function (Builder $dateQuery) use (
                        $requestedStart,
                        $requestedEnd,
                        $deadlineStart,
                        $deadlineEnd,
                    ) {
                        $dateQuery->where('requested_at_mode', 'datetime')
                            ->where('deadline_mode', 'datetime')
                            ->whereBetween('requested_at', [$requestedStart, $requestedEnd])
                            ->whereBetween('deadline_at', [$deadlineStart, $deadlineEnd]);
                    });
                }
            })
            ->orderByDesc('created_at')
            ->get();
    }

    private function hasSameConfirmedDates(ProofReservation $reservation, array $data): bool
    {
        if (
            $reservation->requested_at_mode !== 'datetime'
            || $reservation->deadline_mode !== 'datetime'
            || empty($data['requested_at'])
            || empty($data['deadline_at'])
        ) {
            return false;
        }

        $storedRequestedDate = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $reservation->getRawOriginal('requested_at'),
            'UTC',
        )->setTimezone('Asia/Tokyo')->toDateString();
        $storedDeadlineDate = Carbon::createFromFormat(
            'Y-m-d H:i:s',
            $reservation->getRawOriginal('deadline_at'),
            'UTC',
        )->setTimezone('Asia/Tokyo')->toDateString();

        return $storedRequestedDate
            === Carbon::parse($data['requested_at'])->setTimezone('Asia/Tokyo')->toDateString()
            && $storedDeadlineDate
            === Carbon::parse($data['deadline_at'])->setTimezone('Asia/Tokyo')->toDateString();
    }

    private function jstDayUtcRange(string $value): array
    {
        $jst = Carbon::parse($value)->setTimezone('Asia/Tokyo');

        return [
            $jst->copy()->startOfDay()->utc()->format('Y-m-d H:i:s'),
            $jst->copy()->endOfDay()->utc()->format('Y-m-d H:i:s'),
        ];
    }
}
