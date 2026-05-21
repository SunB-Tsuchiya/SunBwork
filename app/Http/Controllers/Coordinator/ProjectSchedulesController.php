<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\NormalizesCsvEncoding;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ProjectSchedule;
use App\Models\ProjectJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProjectSchedulesController extends Controller
{
    use NormalizesCsvEncoding;

    public function index(Request $request)
    {
        // for PoC, accept project_job_id query
        $projectJobId = $request->query('project_job_id');
        $schedules = ProjectSchedule::where('project_job_id', $projectJobId)->orderBy('order')->get();
        return Inertia::render('Coordinator/ProjectSchedules/Index', [
            'project_job_id' => $projectJobId,
            'schedules' => $schedules,
        ]);
    }

    public function create(Request $request)
    {
        $projectJobId = $request->query('project_job_id');
        return Inertia::render('Coordinator/ProjectSchedules/Create', [
            'project_job_id' => $projectJobId,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'project_job_id' => 'required|exists:project_jobs,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'color' => 'nullable|string|max:32',
            'progress' => 'nullable|integer|min:0|max:100',
        ]);
        
        // Ensure default values
        $data['progress'] = $data['progress'] ?? 0;
        $data['created_by'] = $request->user()->id;
        
        $schedule = ProjectSchedule::create($data);
        
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['status' => 'ok', 'schedule' => $schedule]);
        }
        return redirect()->route('coordinator.project_schedules.index', ['project_job_id' => $data['project_job_id']]);
    }

    // Update a single schedule (PATCH)
    public function update(Request $request, ProjectSchedule $projectSchedule)
    {
        $this->authorize('update', $projectSchedule);
        $data = $request->validate([
            'start' => 'nullable|date',
            'end' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:32',
            'progress' => 'nullable|numeric|min:0|max:100',
        ]);

        $projectSchedule->fill([
            'start_date' => $data['start_date'] ?? $data['start'] ?? $projectSchedule->start_date,
            'end_date' => $data['end_date'] ?? $data['end'] ?? $projectSchedule->end_date,
            'name' => $data['name'] ?? $projectSchedule->name,
            'description' => array_key_exists('description', $data) ? $data['description'] : $projectSchedule->description,
            'color' => $data['color'] ?? $projectSchedule->color,
            'progress' => $data['progress'] ?? $projectSchedule->progress,
            'updated_by' => $request->user()->id,
        ]);
        $projectSchedule->save();
        $projectSchedule->refresh();

        // 連携設定経由で progress_rows.deadline を同期
        if ($projectSchedule->project_job_item_id) {
            $item = \App\Models\ProjectJobItem::find($projectSchedule->project_job_item_id);
            if ($item && $item->calendar_linked && $item->type === 'row' && $item->row_id) {
                \App\Models\ProgressRow::where('id', $item->row_id)
                    ->update(['deadline' => $projectSchedule->end_date?->toDateString()]);
            }
        }

        // Carbon を直接渡すと UTC ISO シリアライズで JST がずれるため toDateString() で返す
        return response()->json(['status' => 'ok', 'schedule' => [
            'id' => $projectSchedule->id,
            'name' => $projectSchedule->name,
            'description' => $projectSchedule->description,
            'start_date' => $projectSchedule->start_date ? $projectSchedule->start_date->toDateString() : null,
            'end_date' => $projectSchedule->end_date ? $projectSchedule->end_date->toDateString() : null,
            'color' => $projectSchedule->color,
            'progress' => $projectSchedule->progress,
            'project_job_id' => $projectSchedule->project_job_id,
        ]]);
    }

    public function destroy(Request $request, ProjectSchedule $projectSchedule)
    {
        $this->authorize('delete', $projectSchedule);
        $projectSchedule->delete();
        return response()->json(['status' => 'ok']);
    }

    public function uncomplete(Request $request, ProjectSchedule $projectSchedule)
    {
        $this->authorize('update', $projectSchedule);
        $projectSchedule->update(['completed_at' => null, 'progress' => 0]);

        // 紐づく schedlink セルも未完了に戻す
        \App\Models\ProgressCell::where('schedule_id', $projectSchedule->id)
            ->where('cell_type', 'schedlink')
            ->update(['completed_at' => null]);

        return response()->json(['status' => 'ok']);
    }

    // Bulk update schedules (e.g., multiple drag changes)
    public function bulkUpdate(Request $request)
    {
        $payload = $request->validate([
            'updates' => 'required|array',
            'updates.*.id' => 'required|integer|exists:project_schedules,id',
            'updates.*.start' => 'nullable|date',
            'updates.*.end' => 'nullable|date',
            'updates.*.progress' => 'nullable|numeric|min:0|max:100',
        ]);

        DB::transaction(function () use ($payload, $request) {
            foreach ($payload['updates'] as $u) {
                $s = ProjectSchedule::find($u['id']);
                if (!$s) continue;
                // optionally authorize per-schedule
                // $this->authorize('update', $s);
                $s->forceFill([
                    'start_date' => $u['start'] ?? $s->start_date,
                    'end_date' => $u['end'] ?? $s->end_date,
                    'progress' => $u['progress'] ?? $s->progress,
                    'updated_by' => $request->user()->id,
                ])->save();
            }
        });

        return response()->json(['status' => 'ok']);
    }

    /**
     * CSV エクスポート: project_job_id のスケジュールをCSVでダウンロード
     */
    public function csvExport(Request $request)
    {
        $projectJobId = $request->query('project_job_id');
        if (!$projectJobId) {
            abort(400, 'project_job_id is required');
        }

        $projectJob = ProjectJob::find($projectJobId);
        $rawTitle = $projectJob ? ($projectJob->title ?? '') : '';
        $safeTitle = preg_replace('/[\/\\\\\:\*\?"<>\|]/', '_', $rawTitle);
        $safeTitle = trim($safeTitle, '_');
        if ($safeTitle === '') {
            $safeTitle = (string) $projectJobId;
        }
        $filename = $safeTitle . '_スケジュール.csv';

        $schedules = ProjectSchedule::where('project_job_id', $projectJobId)
            ->orderBy('start_date')
            ->get(['id', 'name', 'start_date', 'end_date', 'description', 'color']);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename*=UTF-8''" . rawurlencode($filename),
        ];

        $callback = function () use ($schedules) {
            $handle = fopen('php://output', 'w');
            // BOM for Excel
            fputs($handle, "\xEF\xBB\xBF");
            // Header row
            fputcsv($handle, ['イベント名', '開始日', '終了日', 'メモ', '色']);
            foreach ($schedules as $s) {
                fputcsv($handle, [
                    $s->name ?? '',
                    $s->start_date ? $s->start_date->format('Y-m-d') : '',
                    $s->end_date   ? $s->end_date->format('Y-m-d')   : '',
                    $s->description ?? '',
                    $s->color ?? '',
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * CSV インポート: CSVファイルからスケジュールを一括登録
     */
    public function csvImport(Request $request)
    {
        $request->validate([
            'project_job_id' => 'required|exists:project_jobs,id',
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $projectJobId = $request->input('project_job_id');
        $file = $request->file('file');

        $tmpPath = $this->normalizeCsvToTemp($file);

        $handle = fopen($tmpPath, 'r');
        if ($handle === false) {
            @unlink($tmpPath);
            return response()->json(['status' => 'error', 'message' => 'ファイルを開けませんでした'], 422);
        }

        // ヘッダー行をスキップ
        fgetcsv($handle);

        $errors = [];
        $created = 0;
        $row = 2;

        DB::beginTransaction();
        try {
            while (($line = fgetcsv($handle)) !== false) {
                [$name, $startDate, $endDate, $description, $color, $progress] = array_pad($line, 6, null);

                $name = trim($name ?? '');
                if ($name === '') {
                    // イベント名が空の行（Excelの空行含む）は無視
                    $row++;
                    continue;
                }

                // 日付バリデーション（YYYY-MM-DD / YYYY/MM/DD / YYYY/M/D 0:00:00 すべて受け付ける）
                $startDate = $this->parseDateValue($startDate ?? '');
                $endDate   = $this->parseDateValue($endDate ?? '');
                if ($startDate === false) {
                    $errors[] = "{$row}行目: 開始日の形式が不正です（YYYY-MM-DD）";
                    $row++;
                    continue;
                }
                if ($endDate === false) {
                    $errors[] = "{$row}行目: 終了日の形式が不正です（YYYY-MM-DD）";
                    $row++;
                    continue;
                }

                $color = trim($color ?? '');
                if ($color && !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                    $color = null;
                }
                $progress = is_numeric(trim($progress ?? '')) ? (int) trim($progress) : 0;
                $progress = max(0, min(100, $progress));

                ProjectSchedule::create([
                    'project_job_id' => $projectJobId,
                    'name' => $name,
                    'start_date' => $startDate ?: null,
                    'end_date' => $endDate ?: null,
                    'description' => trim($description ?? ''),
                    'color' => $color ?: null,
                    'progress' => $progress,
                    'created_by' => $request->user()->id,
                ]);
                $created++;
                $row++;
            }
            fclose($handle);
            @unlink($tmpPath);

            if (!empty($errors)) {
                DB::rollBack();
                return response()->json(['status' => 'error', 'errors' => $errors], 422);
            }

            DB::commit();
            return response()->json(['status' => 'ok', 'created' => $created]);
        } catch (\Exception $e) {
            DB::rollBack();
            fclose($handle);
            @unlink($tmpPath);
            return response()->json(['status' => 'error', 'message' => 'インポートに失敗しました: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 日付文字列を YYYY-MM-DD に正規化する。
     * 空文字は null、不正な形式は false を返す。
     * 受け付ける形式: YYYY-MM-DD / YYYY/MM/DD / YYYY/M/D / YYYY/M/D H:MM:SS など
     */
    private function parseDateValue(string $value): string|null|false
    {
        // 時刻部分（スペース以降）を除去
        $value = trim(preg_replace('/[\s　].*$/', '', trim($value)));

        if ($value === '') {
            return null;
        }

        // YYYY-MM-DD または YYYY/MM/DD（ゼロ埋めあり/なし）
        if (!preg_match('/^\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}$/', $value)) {
            return false;
        }

        $normalized = str_replace('/', '-', $value);
        [$y, $m, $d] = explode('-', $normalized);

        if (!checkdate((int) $m, (int) $d, (int) $y)) {
            return false;
        }

        return sprintf('%04d-%02d-%02d', (int) $y, (int) $m, (int) $d);
    }
}
