<?php

namespace App\Services;

use App\Models\ProjectJobAssignment;
use App\Models\ProjectJob;
use App\Models\ProgressCell;
use App\Models\WorkflowCell;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Trial-only, authorized canonical received-request defaults. GET never mutates work. */
class ReceivedJobPrefill
{
    public function seed(Request $request, int $id, bool $lock = false): array
    {
        $user = $request->user();
        $context = $request->session()->get('superadmin_context.company_id');
        abort_unless($user && app(UnifiedMyJobBox::class)->available($user, $context === null ? null : (int) $context), 403);
        $query = ProjectJobAssignment::whereKey($id)->where('user_id', $user->id)->coordinatorAssigned();
        if ($lock) $query->lockForUpdate();
        $source = $query->firstOrFail();
        $projectQuery = ProjectJob::whereKey($source->project_job_id);
        if ($lock) $projectQuery->lockForUpdate();
        $project = $projectQuery->firstOrFail();
        $project->load('client');
        $source->setRelation('projectJob', $project);
        abort_unless(($source->company_id === null || (int) $source->company_id === (int) $user->company_id)
            && ($source->department_id === null || (int) $source->department_id === (int) $user->department_id)
            && ($project->company_id === null || (int) $project->company_id === (int) $user->company_id), 403);
        if ($source->completed || $source->is_registered || $source->supersededBy()->exists()) {
            throw ValidationException::withMessages(['source' => 'この依頼は完了済みか登録済みです。依頼一覧を開き直してください。']);
        }
        $sourceAmounts = null;
        $sourceAmountsUnit = 'page';
        $estimatedSizeId = null;

        if (!empty($source->file_info)) {
            $fi = is_array($source->file_info) ? $source->file_info : json_decode($source->file_info, true);
            if ($fi) {
                // ページ数・ファイル数を amounts に自動設定
                $totalPages = $fi['total_pages'] ?? 0;
                $totalFiles = $fi['total_files'] ?? 0;
                if ($totalPages > 0) {
                    $sourceAmounts = $totalPages;
                    $sourceAmountsUnit = 'page';
                } elseif ($totalFiles > 0) {
                    $sourceAmounts = $totalFiles;
                    $sourceAmountsUnit = 'file';
                }

                // ページ型ファイル（PDF・Word・AI・EPS）のサイズ推定
                // 全ファイルが同一 doc_size の場合のみ Size レコードにマッチング
                if (empty($source->size_id) && !empty($fi['files'])) {
                    $pageExts = ['pdf', 'ai', 'docx', 'doc', 'eps'];
                    $pageFiles = array_filter($fi['files'], fn($f) =>
                        in_array(strtolower($f['ext'] ?? ''), $pageExts)
                    );
                    if (!empty($pageFiles)) {
                        $docSizes = array_unique(array_filter(
                            array_map(fn($f) => $f['doc_size'] ?? null, $pageFiles)
                        ));
                        // 全ページ型ファイルが同一サイズ（かつPDFのみ or サイズ差異なし）
                        if (count($docSizes) === 1) {
                            $docSize = reset($docSizes);
                            // "B5(JIS)" → "B5"、"A4" → "A4" のように括弧内を除去してマッチング
                            $baseName = preg_replace('/\s*\([^)]*\)\s*$/', '', $docSize);
                            try {
                                $sizeRecord = \App\Models\Size::where('name', $baseName)
                                    ->orWhere('name', $docSize)
                                    ->orderByRaw('CASE WHEN name = ? THEN 0 ELSE 1 END', [$baseName])
                                    ->first();
                                if ($sizeRecord) $estimatedSizeId = $sizeRecord->id;
                            } catch (\Throwable $__e) {}
                        }
                    }
                }
            }
        }

        // amounts フォールバック: file_info → assignment.amounts → projectJob.page_count
        if ($sourceAmounts === null && $source->amounts !== null) {
            $sourceAmounts     = (int) $source->amounts;
            $sourceAmountsUnit = $source->amounts_unit ?? 'page';
        }
        if ($sourceAmounts === null && $source->projectJob?->page_count) {
            $sourceAmounts     = (int) $source->projectJob->page_count;
            $sourceAmountsUnit = 'page';
        }

        $prefill = [
            'project_job_id' => $source->project_job_id,
            '_client_id' => $source->projectJob?->client?->id ?? '',
            'title_suffix' => $source->title ?? '',
            'detail' => $source->detail ?? '',
            'work_item_type_id' => $source->work_item_type_id ?? null,
            'size_id' => $source->size_id ?? $estimatedSizeId ?? $source->projectJob->size_id,
            'stage_id' => $source->stage_id ?? null,
            'difficulty_id' => $source->difficulty_id ?? null,
            'desired_end_date' => $source->desired_end_date
                ? (is_string($source->desired_end_date) ? $source->desired_end_date : $source->desired_end_date->format('Y-m-d'))
                : null,
            'desired_time' => $source->desired_time ?? null,
            'estimated_hours' => $source->estimated_hours ?? null,
            'sender_id' => $user ? $user->id : null,
            'amounts' => $sourceAmounts,
            'amounts_unit' => $sourceAmountsUnit,
            'status_id' => null,
            // ファイル情報を引き継ぐ（表示・保存用）
            'file_info' => $source->file_info ?? null,
            // 依頼ジョブをマイジョブで置き換えたことを記録（coordinator jobbox の非表示フィルタに使用）
            'supersedes_assignment_id' => (int) $source->id,
        ];
        $prefill['user_id'] = $user->id;
        $prefill['company_id'] = $user->company_id;
        $prefill['department_id'] = $user->department_id;
        if ($source->coordinator_assignment_id) {
            $coordinatorQuery = ProjectJobAssignment::whereKey($source->coordinator_assignment_id);
            if ($lock) $coordinatorQuery->lockForUpdate();
            $coordinator = $coordinatorQuery->firstOrFail();
            abort_unless((int) $coordinator->project_job_id === (int) $source->project_job_id
                && ($coordinator->company_id === null || (int) $coordinator->company_id === (int) $user->company_id), 403);
        }
        $prefill['coordinator_assignment_id'] = $source->coordinator_assignment_id;
        foreach ($this->links($source, $lock) as $key => $value) $prefill[$key] = $value;
        $projectData = $project->only(['id', 'title', 'client_id', 'size_id', 'page_count', 'company_id']);
        $client = $project->client?->only(['id', 'name']);
        $projectData['client'] = $client;
        $revision = hash_hmac('sha256', json_encode([$source->getRawOriginal(), $project->getRawOriginal(), $prefill], JSON_THROW_ON_ERROR), (string) config('app.key'));
        $prefill['_request_revision'] = $revision;
        return ['assignment' => $prefill, 'project' => $projectData, 'client' => $client];
    }

    private function links(ProjectJobAssignment $source, bool $lock): array
    {
        $links = [];
        foreach ([[ProgressCell::class, 'col_key', '_progress_sheet_id'], [WorkflowCell::class, 'stage_key', '_workflow_sheet_id']] as [$model, $column, $sheetKey]) {
            $query = $model::where('assignment_id', $source->id)->orderBy('id');
            if ($lock) $query->lockForUpdate();
            $cells = $query->limit(2)->get();
            if ($cells->count() > 1 || ($cells->isNotEmpty() && $links)) {
                throw ValidationException::withMessages(['source' => '元依頼の表の関連を一意に確認できません。管理者に確認してください。']);
            }
            if (! $cell = $cells->first()) continue;
            $row = $cell->row; $sheet = $row?->sheet;
            abort_unless($row && $sheet && (int) $sheet->project_job_id === (int) $source->project_job_id, 403);
            $links = [$sheetKey => $sheet->id, '_row_id' => $row->id, '_col_key' => $cell->$column];
        }
        return $links;
    }

    public function mergeProps(array $props, array $seed): array
    {
        $props['receivedRequestPrefillEnabled'] = true;
        $props['projectJob'] = $seed['project'];
        $props['assignments'] = [$seed['assignment']];
        $props['userProjects'] = collect($props['userProjects'] ?? [])->prepend($seed['project'])->unique('id')->values()->all();
        if ($seed['client']) $props['userClients'] = collect($props['userClients'] ?? [])->prepend($seed['client'])->unique('id')->values()->all();
        return $props;
    }
}
