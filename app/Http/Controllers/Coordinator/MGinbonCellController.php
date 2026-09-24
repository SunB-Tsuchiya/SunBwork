<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\MGinbon\MGinbonItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MGinbonCellController extends Controller
{
    private const SUBJECT_DATE_CODES = [
        'manuscript_received_on', 'manuscript_due_on', 'text_input_completed_on', 'drawing_completed_on',
        'initial_shared_on', 'initial_text_proof_started_on', 'initial_text_proof_completed_on',
        'initial_returned_on', 'reproof_scan_check_started_on', 'reproof_scan_check_completed_on',
        'reproof_shared_on', 'reproof_text_proof_started_on', 'reproof_text_proof_completed_on',
        'reproof_returned_on', 'third_shared_on', 'third_returned_on', 'fourth_shared_on',
        'fourth_returned_on', 'fifth_shared_on', 'completed_on',
    ];

    private const SHARED_DATE_CODES = ['original_received_on', 'original_scan_completed_on'];

    public function updateDate(Request $request, MGinbonItem $item): JsonResponse
    {
        $validated = $request->validate([
            'updated_at' => ['required', 'string'],
            'subject_id' => ['nullable', 'integer'],
            'subject_ids' => ['nullable', 'array', 'min:1'],
            'subject_ids.*' => ['integer', 'distinct'],
            'code' => ['required', 'string'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $isShared = in_array($validated['code'], self::SHARED_DATE_CODES, true);
        abort_unless($isShared || in_array($validated['code'], self::SUBJECT_DATE_CODES, true), 422, '編集できない日付項目です。');
        abort_if($isShared && isset($validated['subject_id']), 422, '共通日付に教科は指定できません。');
        abort_if($isShared && isset($validated['subject_ids']), 422, '共通日付に教科は指定できません。');
        abort_if(! $isShared && ! isset($validated['subject_id']) && empty($validated['subject_ids']), 422, '教科を指定してください。');

        $db = DB::connection('mginbon');
        $updatedAt = $db->transaction(function () use ($request, $item, $validated, $isShared, $db) {
            $locked = $db->table('mginbon_items')->where('id', $item->id)->lockForUpdate()->first();
            if (! $locked || (string) $locked->updated_at !== $validated['updated_at']) {
                throw ValidationException::withMessages(['updated_at' => 'ほかの利用者が更新しました。画面を再読み込みしてください。']);
            }
            $subjectIds = $isShared
                ? [null]
                : array_values(array_unique(array_map('intval', $validated['subject_ids'] ?? [$validated['subject_id']])));
            if (! $isShared) {
                $validCount = $db->table('mginbon_item_subjects')->where('mginbon_item_id', $item->id)
                    ->whereIn('id', $subjectIds)->count();
                abort_unless($validCount === count($subjectIds), 422, '教科が不正です。');
            }
            $date = $validated['date'] ?? null;
            $unit = $db->table('mginbon_production_units')->where('id', $locked->mginbon_production_unit_id)->first();
            foreach ($subjectIds as $subjectId) {
                $query = $db->table('mginbon_milestones')->where('mginbon_item_id', $item->id)
                    ->where('code', $validated['code']);
                $subjectId === null ? $query->whereNull('mginbon_item_subject_id') : $query->where('mginbon_item_subject_id', $subjectId);
                $current = $query->first();
                $old = $current?->occurred_on;
                if ($old === $date) continue;
                if (! $date && $current) $query->delete();
                elseif ($current) $query->update(['occurred_on' => $date, 'source' => 'manual', 'updated_at' => now()]);
                elseif ($date) $db->table('mginbon_milestones')->insert([
                    'mginbon_item_id' => $item->id, 'mginbon_item_subject_id' => $subjectId,
                    'code' => $validated['code'], 'occurred_on' => $date, 'source' => 'manual',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $db->table('mginbon_change_logs')->insert([
                    'mginbon_project_id' => $unit->mginbon_project_id, 'mginbon_item_id' => $item->id,
                    'mginbon_item_subject_id' => $subjectId, 'changed_by' => $request->user()->id,
                    'field_path' => ($isShared ? 'shared_dates.' : "subjects.{$subjectId}.dates.").$validated['code'],
                    'old_value' => json_encode($old), 'new_value' => json_encode($date), 'source' => 'manual',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $now = now();
            $db->table('mginbon_items')->where('id', $item->id)->update(['updated_at' => $now]);
            return $now->format('Y-m-d H:i:s');
        });

        return response()->json([
            'date' => $validated['date'] ?? null,
            'subject_ids' => $isShared ? null : array_values(array_unique(array_map('intval', $validated['subject_ids'] ?? [$validated['subject_id']]))),
            'subjectIds' => $isShared ? null : array_values(array_unique(array_map('intval', $validated['subject_ids'] ?? [$validated['subject_id']]))),
            'updated_at' => $updatedAt,
        ]);
    }
}
