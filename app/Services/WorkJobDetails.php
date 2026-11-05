<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Optional metadata on the same work. Never creates an assignment/event or changes quantities. */
class WorkJobDetails
{
    public function __construct(private WorkJobReader $reader) {}

    public function save(Request $request, int $id, ?int $context): array
    {
        $this->reader->resolve($request->user(), 'entry', $id, $context);
        $data = $request->validate([
            'details_version' => ['required', 'integer', 'min:0', 'max:4294967294'],
            'detail_note' => ['present', 'nullable', 'string', 'max:5000'],
            'file_info' => ['present', 'nullable', 'array:files,total_files,total_pages,total_size_bytes,summary,groups'],
            'file_info.files' => ['required_with:file_info', 'array', 'list', 'min:1', 'max:200'],
            'file_info.files.*' => ['array:name,ext,type,size,pages,doc_size,width,height,extra'],
            'file_info.files.*.name' => ['required', 'string', 'max:255'],
            'file_info.files.*.ext' => ['required', 'string', 'max:20', 'regex:/^[a-zA-Z0-9]+$/'],
            'file_info.files.*.type' => ['required', 'string', 'max:30', \Illuminate\Validation\Rule::in(['pdf','ai','indd','indd_legacy','idml','docx','pptx','eps','xlsx','svg','html','font','raw','video','psd','image','code','other'])],
            'file_info.files.*.size' => ['required', 'integer', 'min:0', 'max:2199023255552'],
            'file_info.files.*.pages' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'file_info.files.*.doc_size' => ['nullable', 'string', 'max:100'],
            'file_info.files.*.width' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'file_info.files.*.height' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'file_info.files.*.extra' => ['nullable', 'string', 'max:255'],
        ]);
        $note = trim($data['detail_note'] ?? '');
        if ($note === '') $note = null;
        $files = $data['file_info']['files'] ?? [];
        $info = $files ? $this->fileInfo($files) : null;
        if (strlen(json_encode($info, JSON_THROW_ON_ERROR)) > 262144) {
            throw ValidationException::withMessages(['file_info' => 'ファイル情報が多すぎます。200件以内で分けて登録してください。']);
        }
        return DB::transaction(function () use ($request, $id, $context, $data, $note, $info) {
            $job = $this->reader->resolve($request->user(), 'entry', $id, $context);
            $job = $job->newQuery()->whereKey($id)->lockForUpdate()->firstOrFail();
            $this->reader->resolve($request->user(), 'entry', $id, $context);
            // Exact retry is safe even after the first response was lost.
            if ($job->detail_note === $note && $job->file_info == $info) return ['id' => $id, 'details_version' => (int) $job->details_version];
            abort_unless((int) $job->details_version === (int) $data['details_version'], 409, '詳細情報がほかの画面で更新されました。再読み込みしてから編集してください。');
            $job->detail_note = $note; $job->file_info = $info;
            $job->details_version = (int) $job->details_version + 1;
            $job->save();
            return ['id' => $id, 'details_version' => (int) $job->details_version];
        }, 3);
    }

    private function fileInfo(array $files): array
    {
        $groups = []; $pages = 0; $bytes = 0;
        foreach ($files as &$file) {
            $file = array_merge(array_fill_keys(['pages', 'doc_size', 'width', 'height', 'extra'], null), $file);
            $file['size'] = (int) $file['size'];
            $file['pages'] = $file['pages'] === null ? null : (int) $file['pages'];
            $pages += $file['pages'] ?? 0; $bytes += $file['size'];
            $type = $file['type'];
            $groups[$type] ??= ['count' => 0, 'pages' => 0, 'size_bytes' => 0, 'doc_size' => $file['doc_size']];
            $groups[$type]['count']++; $groups[$type]['pages'] += $file['pages'] ?? 0; $groups[$type]['size_bytes'] += $file['size'];
        }
        unset($file);
        return ['files' => $files, 'total_files' => count($files), 'total_pages' => $pages ?: null,
            'total_size_bytes' => $bytes, 'groups' => $groups];
    }
}
