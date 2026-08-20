<?php

namespace App\Http\Controllers\Clerk;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\NormalizesCsvEncoding;
use App\Http\Controllers\Concerns\ResolvesContextCompany;
use App\Models\ClerkEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ClerkEventController extends Controller
{
    use NormalizesCsvEncoding, ResolvesContextCompany;

    public function index()
    {
        $events = ClerkEvent::where('company_id', $this->companyId())
            ->with('user:id,name')
            ->orderBy('starts_at')
            ->get();

        return response()->json($events->map(fn($e) => [
            'id'          => $e->id,
            'title'       => $e->title,
            'start'       => $e->all_day
                ? $e->starts_at->format('Y-m-d')
                : $e->starts_at->format('Y-m-d\TH:i:s'),
            'end'         => $e->ends_at
                ? ($e->all_day
                    ? $e->ends_at->copy()->addDay()->format('Y-m-d')
                    : $e->ends_at->format('Y-m-d\TH:i:s'))
                : null,
            'allDay'      => $e->all_day,
            'description' => $e->description,
            'user_name'   => $e->user?->name,
            'color_key'   => $e->color_key,
            'completed'   => $e->completed_at !== null,
        ]));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'starts_at'   => 'required|date',
            'ends_at'     => 'nullable|date|after_or_equal:starts_at',
            'all_day'     => 'boolean',
            'color_key'   => 'nullable|string|max:20',
        ]);

        $event = ClerkEvent::create(array_merge($validated, [
            'company_id' => $this->companyId(),
            'user_id'    => Auth::id(),
        ]));

        return response()->json($event, 201);
    }

    public function update(Request $request, ClerkEvent $event)
    {
        abort_unless($event->company_id === $this->companyId(), 404);

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'starts_at'   => 'required|date',
            'ends_at'     => 'nullable|date',
            'all_day'     => 'boolean',
            'color_key'   => 'nullable|string|max:20',
        ]);

        $event->update($validated);

        return response()->json($event);
    }

    public function destroy(ClerkEvent $event)
    {
        abort_unless($event->company_id === $this->companyId(), 404);

        $event->delete();

        return response()->noContent();
    }

    public function complete(ClerkEvent $event)
    {
        abort_unless($event->company_id === $this->companyId(), 404);

        $event->update(['completed_at' => $event->completed_at ? null : now()]);

        return response()->json(['completed' => $event->completed_at !== null]);
    }

    public function csvExport()
    {
        $events = ClerkEvent::where('company_id', $this->companyId())
            ->orderBy('starts_at')
            ->get();

        $bom = "\xEF\xBB\xBF";
        $csv = $bom . "タイトル,開始日,終了日,内容\n";
        foreach ($events as $e) {
            $title = '"' . str_replace('"', '""', $e->title ?? '') . '"';
            $start = $e->starts_at ? $e->starts_at->format('Y-m-d') : '';
            $end   = $e->ends_at   ? $e->ends_at->format('Y-m-d')   : '';
            $desc  = '"' . str_replace(['"', "\n", "\r"], ['""', ' ', ' '], $e->description ?? '') . '"';
            $csv  .= "{$title},{$start},{$end},{$desc}\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="clerk_events_' . $this->companyId() . '.csv"',
        ]);
    }

    public function csvImport(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);

        $path = $request->file('file')->store('temp_csv', 'local');
        $this->normalizeCsvStoredFile($path);

        $handle  = fopen(Storage::path($path), 'r');
        $errors  = [];
        $rows    = [];
        $lineNum = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNum++;
            if ($lineNum === 1) continue; // skip header
            if (count($row) < 1 || trim($row[0] ?? '') === '') continue;

            $title    = trim($row[0] ?? '');
            $startStr = trim($row[1] ?? '');
            $endStr   = trim($row[2] ?? '');
            $desc     = trim($row[3] ?? '');

            if (!empty($startStr) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startStr)) {
                $errors[] = "{$lineNum}行目: 開始日の形式が不正です (YYYY-MM-DD)";
                continue;
            }
            if (!empty($endStr) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endStr)) {
                $errors[] = "{$lineNum}行目: 終了日の形式が不正です (YYYY-MM-DD)";
                continue;
            }

            $start = $startStr ?: now()->format('Y-m-d');
            $rows[] = [
                'title'       => $title,
                'description' => $desc ?: null,
                'starts_at'   => $start,
                'ends_at'     => $endStr ?: $start,
                'all_day'     => true,
            ];
        }
        fclose($handle);
        Storage::delete($path);

        if (!empty($errors)) {
            return response()->json(['errors' => $errors], 422);
        }

        $created = 0;
        foreach ($rows as $row) {
            ClerkEvent::create(array_merge($row, [
                'company_id' => $this->companyId(),
                'user_id'    => Auth::id(),
            ]));
            $created++;
        }

        return response()->json(['created' => $created]);
    }

    private function companyId(): int
    {
        return $this->contextCompanyId() ?? Auth::user()->company_id;
    }
}
