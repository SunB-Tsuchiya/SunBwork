<?php

namespace App\Services\MGinbon;

use App\Models\MGinbon\MGinbonProject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MGinbonFilenameBulkService
{
    public const MILESTONES = [
        'initial_shared_on' => '初校出',
        'initial_text_proof_started_on' => '初校校正入',
        'initial_text_proof_completed_on' => '初校校正UP',
        'reproof_shared_on' => '再校出',
        'reproof_scan_check_started_on' => '校正①校正入',
        'reproof_scan_check_completed_on' => '校正①校正UP',
        'reproof_text_proof_started_on' => '校正②校正入',
        'reproof_text_proof_completed_on' => '校正②校正UP',
        'third_shared_on' => '三校出',
        'fourth_shared_on' => '四校出',
        'fifth_shared_on' => '五校出',
        'completed_on' => '校了',
    ];

    private const MEDIA = ['Q' => '問題', 'A' => '解答のみ', 'AA' => '解説解答', 'Y' => '解答用紙', 'T' => '傾向と対策'];

    private const SUBJECTS = [
        'Ko' => ['code' => 'japanese', 'name' => '国語'],
        'Sa' => ['code' => 'math', 'name' => '算数'],
        'Sh' => ['code' => 'social', 'name' => '社会'],
        'Ri' => ['code' => 'science', 'name' => '理科'],
    ];

    public function milestoneOptions(): array
    {
        return collect(self::MILESTONES)->map(fn (string $label, string $code) => compact('code', 'label'))->values()->all();
    }

    public function parse(string $input): ?array
    {
        $filename = basename(str_replace('\\', '/', trim($input)));
        if (! preg_match('/^(?<n_code>\d{4})(?<year>\d{4})__(?<media>AA|Q|A|Y|T)(?<subject>Ko|Sa|Sh|Ri)(?i:\.pdf)$/', $filename, $matches)) {
            return null;
        }

        return [
            'filename' => $filename,
            'n_code' => $matches['n_code'],
            'year' => (int) $matches['year'],
            'media_symbol' => $matches['media'],
            'media_name' => self::MEDIA[$matches['media']],
            'subject_symbol' => $matches['subject'],
            'subject_code' => self::SUBJECTS[$matches['subject']]['code'],
            'subject_name' => self::SUBJECTS[$matches['subject']]['name'],
        ];
    }

    public function preview(MGinbonProject $project, string $filenameText, string $milestoneCode, string $date, bool $overwrite): array
    {
        $this->assertMilestone($milestoneCode);
        $lines = collect(preg_split('/\R/u', $filenameText) ?: [])->map(fn ($line) => trim((string) $line))->filter()->values();
        if ($lines->count() > 200) throw ValidationException::withMessages(['filenames' => '一度に登録できるファイル名は200件までです。']);

        $parsed = $lines->map(fn (string $line, int $index) => ['line' => $index + 1, 'input' => $line, 'parsed' => $this->parse($line)]);
        $valid = $parsed->pluck('parsed')->filter();
        $candidates = $this->candidates($project, $valid->pluck('n_code')->unique()->values()->all());
        $candidateGroups = $candidates->groupBy(fn ($row) => $this->targetKey((array) $row));
        $targetCounts = $valid->countBy(fn (array $row) => $this->targetKey($row));
        $milestones = $this->currentMilestones($candidates, $milestoneCode);

        $rows = $parsed->map(function (array $entry) use ($project, $candidateGroups, $targetCounts, $milestones, $date, $overwrite) {
            $base = ['line' => $entry['line'], 'input' => $entry['input'], 'filename' => null, 'n_code' => null, 'year' => null,
                'school_name' => null, 'media_name' => null, 'subject_name' => null, 'current_date' => null, 'new_date' => $date,
                'status' => 'invalid_format', 'message' => 'ファイル名形式が一致しません。'];
            if (! $entry['parsed']) return $base;
            $value = $entry['parsed'];
            $base = array_merge($base, collect($value)->only(['filename', 'n_code', 'year', 'media_name', 'subject_name'])->all());
            if ($value['year'] !== (int) $project->year) return array_merge($base, ['status' => 'year_mismatch', 'message' => "表示年度（{$project->year}）と異なります。"]);
            $key = $this->targetKey($value);
            if (($targetCounts[$key] ?? 0) > 1) return array_merge($base, ['status' => 'duplicate', 'message' => '同じ対象が複数行あります。']);
            $matches = $candidateGroups->get($key, collect());
            if ($matches->isEmpty()) return array_merge($base, ['status' => 'not_found', 'message' => 'Nコード・媒体・教科に一致するレコードがありません。']);
            if ($matches->count() > 1) return array_merge($base, ['status' => 'ambiguous', 'message' => '対象が複数あるため一意に判定できません。']);
            $match = $matches->first();
            $current = $milestones[$match->item_id.':'.$match->item_subject_id] ?? null;
            $base = array_merge($base, ['school_name' => $match->display_name, 'current_date' => $current,
                '_item_id' => (int) $match->item_id, '_subject_id' => (int) $match->item_subject_id]);
            if ($current === $date) return array_merge($base, ['status' => 'same_date', 'message' => '同じ日付を登録済みです。']);
            if ($current && ! $overwrite) return array_merge($base, ['status' => 'existing_value', 'message' => '別の日付が登録済みです。']);
            return array_merge($base, ['status' => $current ? 'overwrite_ready' : 'ready', 'message' => $current ? '既存日付を上書きします。' : '登録可能です。']);
        })->values();

        return [
            'milestone_code' => $milestoneCode,
            'milestone_label' => self::MILESTONES[$milestoneCode],
            'date' => $date,
            'rows' => $rows->map(fn (array $row) => collect($row)->except(['_item_id', '_subject_id'])->all())->all(),
            '_rows' => $rows->all(),
            'summary' => $rows->countBy('status')->all(),
            'registrable_count' => $rows->whereIn('status', ['ready', 'overwrite_ready'])->count(),
        ];
    }

    public function commit(MGinbonProject $project, string $filenameText, string $milestoneCode, string $date, bool $overwrite, int $userId): array
    {
        $preview = $this->preview($project, $filenameText, $milestoneCode, $date, $overwrite);
        $ready = collect($preview['_rows'])->whereIn('status', ['ready', 'overwrite_ready'])->values();
        $db = DB::connection('mginbon');
        $result = $db->transaction(function () use ($db, $project, $ready, $milestoneCode, $date, $overwrite, $userId) {
            $updated = 0; $skipped = 0;
            foreach ($ready as $row) {
                $item = $db->table('mginbon_items')->where('id', $row['_item_id'])->lockForUpdate()->first();
                if (! $item) { $skipped++; continue; }
                $query = $db->table('mginbon_milestones')->where('mginbon_item_id', $row['_item_id'])
                    ->where('mginbon_item_subject_id', $row['_subject_id'])->where('code', $milestoneCode);
                $current = $query->first();
                $old = $current?->occurred_on;
                if ($old === $date || ($old && ! $overwrite)) { $skipped++; continue; }
                $now = now();
                if ($current) $query->update(['occurred_on' => $date, 'source' => 'manual', 'updated_at' => $now]);
                else $db->table('mginbon_milestones')->insert(['mginbon_item_id' => $row['_item_id'], 'mginbon_item_subject_id' => $row['_subject_id'],
                    'code' => $milestoneCode, 'occurred_on' => $date, 'source' => 'manual', 'created_at' => $now, 'updated_at' => $now]);
                $db->table('mginbon_change_logs')->insert(['mginbon_project_id' => $project->id, 'mginbon_item_id' => $row['_item_id'],
                    'mginbon_item_subject_id' => $row['_subject_id'], 'changed_by' => $userId,
                    'field_path' => "subjects.{$row['_subject_id']}.dates.{$milestoneCode}", 'old_value' => json_encode($old),
                    'new_value' => json_encode($date), 'source' => 'manual', 'created_at' => $now, 'updated_at' => $now]);
                $db->table('mginbon_items')->where('id', $row['_item_id'])->update(['updated_at' => $now]);
                $updated++;
            }
            return compact('updated', 'skipped');
        });

        return array_merge($result, ['total' => count($preview['rows'])]);
    }

    private function assertMilestone(string $code): void
    {
        if (! isset(self::MILESTONES[$code])) throw ValidationException::withMessages(['milestone_code' => '登録対象の工程が不正です。']);
    }

    private function targetKey(array $row): string
    {
        return $row['n_code'].'|'.$row['media_name'].'|'.$row['subject_code'];
    }

    private function candidates(MGinbonProject $project, array $nCodes): Collection
    {
        if ($nCodes === []) return collect();
        return DB::connection('mginbon')->table('mginbon_production_units as units')
            ->join('mginbon_items as items', 'items.mginbon_production_unit_id', '=', 'units.id')
            ->join('mginbon_media_types as media', 'media.id', '=', 'items.mginbon_media_type_id')
            ->join('mginbon_item_subjects as item_subjects', 'item_subjects.mginbon_item_id', '=', 'items.id')
            ->join('mginbon_subjects as subjects', 'subjects.id', '=', 'item_subjects.mginbon_subject_id')
            ->where('units.mginbon_project_id', $project->id)->whereIn('units.n_code', $nCodes)
            ->get(['units.n_code', 'units.display_name', 'items.id as item_id', 'item_subjects.id as item_subject_id',
                'media.name as media_name', 'subjects.code as subject_code']);
    }

    private function currentMilestones(Collection $candidates, string $code): array
    {
        $subjectIds = $candidates->pluck('item_subject_id')->unique()->values()->all();
        if ($subjectIds === []) return [];
        return DB::connection('mginbon')->table('mginbon_milestones')->where('code', $code)
            ->whereIn('mginbon_item_subject_id', $subjectIds)->get()
            ->mapWithKeys(fn ($row) => [$row->mginbon_item_id.':'.$row->mginbon_item_subject_id => (string) $row->occurred_on])->all();
    }
}
