<?php

namespace App\Http\Controllers\Coordinator;

use App\Models\MGinbon\MGinbonProject;
use App\Services\MGinbon\MGinbonLedgerSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MGinbonSearchController extends MGinbonLedgerController
{
    public function previewCount(Request $request, MGinbonLedgerSearch $ledgerSearch): JsonResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'between:2000,2100'],
            'find' => ['nullable', 'string', 'max:20000'],
            'search' => ['nullable', 'string', 'max:100'],
            'media' => ['nullable', 'string', 'max:100'],
            'subject' => ['nullable', Rule::in(['', 'japanese', 'math', 'social', 'science'])],
            'status' => ['nullable', Rule::in(['all', 'draft', 'review_required'])],
        ]);
        $project = MGinbonProject::query()->where('year', (int) $data['year'])->firstOrFail();
        $filters = [
            'search' => trim((string) ($data['search'] ?? '')),
            'media' => (string) ($data['media'] ?? ''),
            'subject' => (string) ($data['subject'] ?? ''),
            'status' => (string) ($data['status'] ?? 'all'),
        ];
        $query = $this->applyItemFilters($this->itemsQuery($project->id), $filters);
        $count = $ledgerSearch->apply($query, $ledgerSearch->decode((string) ($data['find'] ?? '')))->count();

        return response()->json(['count' => $count]);
    }
}
