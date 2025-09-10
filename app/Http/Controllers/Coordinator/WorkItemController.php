<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\WorkItem;
use App\Models\WorkItemType;
use App\Models\Size;
use App\Models\Stage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Status;
use App\Models\Company;
use App\Models\Department;

class WorkItemController extends Controller
{
    public function index(Request $request)
    {
        $query = WorkItem::with(['type', 'size'])->orderBy('created_at', 'desc');

        // Filters (include company and department so frontend can send them)
        $filters = $request->only(['type', 'size', 'status', 'stage', 'company', 'department']);

        if (!empty($filters['type'])) {
            $query->where('work_item_type_id', $filters['type']);
        }

        if (!empty($filters['size'])) {
            $query->where('size_id', $filters['size']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // stage filter: find work_items that have an entry with requested stage
        if (!empty($filters['stage'])) {
            $stageId = (int) $filters['stage'];
            $query->whereExists(function ($q) use ($stageId) {
                $q->select(DB::raw(1))
                    ->from('work_item_stage_entries')
                    ->whereColumn('work_item_stage_entries.work_item_id', 'work_items.id')
                    ->where('work_item_stage_entries.stage_id', $stageId);
            });
        }

        $workItems = $query->paginate(10)->withQueryString();

        // lookup lists (order by sort_order when available)
        $types = WorkItemType::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'company_id', 'department_id']);
        $sizes = Size::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'width', 'height', 'unit', 'company_id', 'department_id']);
        $stages = Stage::orderBy('sort_order')->orderBy('order_index')->get(['id', 'name', 'company_id', 'department_id']);
        $statuses = Status::orderBy('sort_order')->get(['id', 'name', 'slug', 'company_id', 'department_id']);

        // determine company/department to show in header
        // priority: explicit query params (filters) > selected size ownership > user's team/company
        $company = null;
        $department = null;

        // 1) explicit filters from query
        if (!empty($filters['company'])) {
            $c = Company::find($filters['company']);
            if ($c) {
                $company = ['id' => $c->id, 'name' => $c->name];
            }
        }
        if (!empty($filters['department'])) {
            $d = Department::find($filters['department']);
            if ($d) {
                $department = ['id' => $d->id, 'name' => $d->name];
            }
        }

        // 2) if still not set, prefer company/department from selected size
        if ((!$company || !$department) && !empty($filters['size'])) {
            $selectedSize = Size::with(['company', 'department'])->find($filters['size']);
            if ($selectedSize) {
                if (!$company && $selectedSize->company) {
                    $company = ['id' => $selectedSize->company->id, 'name' => $selectedSize->company->name];
                }
                if (!$department && $selectedSize->department) {
                    $department = ['id' => $selectedSize->department->id, 'name' => $selectedSize->department->name];
                }
            }
        }

        // 3) fallback to user's team/company if still null
        if (!$company || !$department) {
            $user = $request->user();
            if ($user) {
                if (isset($user->current_team) && $user->current_team) {
                    $t = $user->current_team;
                    if (!$company) $company = $t->company ? ['id' => $t->company->id, 'name' => $t->company->name] : null;
                    if (!$department) $department = isset($t->department_name) ? ['name' => $t->department_name] : null;
                }
                if (!$company && isset($user->company) && $user->company) {
                    $company = ['id' => $user->company->id, 'name' => $user->company->name];
                }
                if (!$department && isset($user->department) && $user->department) {
                    $department = ['id' => $user->department->id, 'name' => $user->department->name];
                }
            }
        }

        // prepare companies list based on user role
        $companies = collect();
        $userRole = null;
        $userCompanyId = null;
        $userDepartmentId = null;
        $user = $request->user();
        if ($user) {
            $userRole = $user->user_role ?? null;
            $userCompanyId = $user->company_id ?? null;
            $userDepartmentId = $user->department_id ?? null;
        }

        if ($userRole === 'superadmin') {
            $companies = Company::with(['departments' => function ($q) {
                $q->orderBy('sort_order');
            }])->orderBy('name')->get();
        } elseif ($userRole === 'admin') {
            if ($userCompanyId) {
                $companies = Company::where('id', $userCompanyId)->with(['departments' => function ($q) {
                    $q->orderBy('sort_order');
                }])->get();
            }
        } else {
            // leader/coordinator: no companies list (they only see their department)
            $companies = collect();
        }

        return Inertia::render('Coordinator/WorkItems/Index', [
            'workItems' => $workItems,
            'types' => $types,
            'sizes' => $sizes,
            'stages' => $stages,
            'statuses' => $statuses,
            'filters' => $filters,
            'company' => $company,
            'department' => $department,
            'companies' => $companies,
            'user_role' => $userRole,
            'user_company_id' => $userCompanyId,
            'user_department_id' => $userDepartmentId,
        ]);
    }

    /**
     * Apply a preset: duplicate a preset work item into a new draft work item
     */
    public function applyPreset(Request $request, WorkItem $work_item)
    {
        // Only allow applying items that are presets (status = preset)
        if ($work_item->status !== 'preset') {
            return redirect()->back()->with('error', '指定された項目はプリセットではありません。');
        }

        // Duplicate
        $new = $work_item->replicate();
        $new->status = 'draft';
        $new->created_by = $request->user() ? $request->user()->id : null;
        $new->push();

        return redirect()->route('coordinator.work_items.index')->with('success', 'プリセットを適用しました。');
    }

    public function create()
    {
        return Inertia::render('Coordinator/WorkItems/Create');
    }

    /**
     * Save ordering for lookup tables. Expects JSON: { table: 'types'|'sizes'|'stages', ids: [1,2,3...] }
     */
    public function saveLookupOrder(Request $request)
    {
        $data = $request->validate([
            'table' => ['required', 'string'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $table = $data['table'];
        $ids = $data['ids'];

        $modelMap = [
            'types' => WorkItemType::class,
            'sizes' => Size::class,
            'stages' => Stage::class,
            'statuses' => Status::class,
        ];

        if (!isset($modelMap[$table])) {
            return response()->json(['error' => 'Invalid table'], 400);
        }

        $modelClass = $modelMap[$table];

        DB::beginTransaction();
        try {
            foreach ($ids as $index => $id) {
                $modelClass::where('id', $id)->update(['sort_order' => $index]);
            }
            DB::commit();
            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a new lookup item (type, size, stage)
     * Expects: { table: 'types'|'sizes'|'stages', name: '...' }
     */
    public function storeLookup(Request $request)
    {
        $data = $request->validate([
            'table' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'width' => ['nullable', 'numeric'],
            'height' => ['nullable', 'numeric'],
            'unit' => ['nullable', 'string', 'max:20'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $table = $data['table'];
        $name = $data['name'];

        $modelMap = [
            'types' => WorkItemType::class,
            'sizes' => Size::class,
            'stages' => Stage::class,
            'statuses' => Status::class,
        ];

        if (!isset($modelMap[$table])) {
            return response()->json(['error' => 'Invalid table'], 400);
        }

        $modelClass = $modelMap[$table];

        // determine next sort_order
        $max = $modelClass::max('sort_order');
        $nextOrder = is_null($max) ? 0 : ($max + 1);

        // duplicate check: for sizes check width/height/unit or name; for others check slug/name
        if ($table === 'sizes') {
            $width = $request->input('width');
            $height = $request->input('height');
            $unit = $request->input('unit', 'mm');
            // If width/height provided, check duplicates by those values
            if ($width && $height) {
                $exists = Size::where('width', $width)->where('height', $height)->where('unit', $unit)->exists();
                if ($exists) return response()->json(['error' => '同じサイズが既に存在します'], 422);
            } else {
                $exists = Size::where('name', $name)->exists();
                if ($exists) return response()->json(['error' => '同じ名前のサイズが既に存在します'], 422);
            }

            $item = Size::create([
                'name' => $name,
                'label' => $name,
                'width' => $width ?: null,
                'height' => $height ?: null,
                'unit' => $unit,
                'company_id' => $request->input('company_id') ?: null,
                'department_id' => $request->input('department_id') ?: null,
                'sort_order' => $nextOrder,
            ]);
        } else {
            // types/stages/statuses
            if ($table === 'types' || $table === 'statuses') {
                $slug = Str::slug($name);
                $exists = $modelClass::where('slug', $slug)->exists();
                if ($exists) return response()->json(['error' => '同じ項目が既に存在します'], 422);
                $item = $modelClass::create([
                    'name' => $name,
                    'slug' => $slug,
                    'company_id' => $request->input('company_id') ?: null,
                    'department_id' => $request->input('department_id') ?: null,
                    'sort_order' => $nextOrder,
                ]);
            } else {
                // stages
                $exists = Stage::where('name', $name)->exists();
                if ($exists) return response()->json(['error' => '同じステージ名が既に存在します'], 422);
                $item = $modelClass::create([
                    'name' => $name,
                    'company_id' => $request->input('company_id') ?: null,
                    'department_id' => $request->input('department_id') ?: null,
                    'sort_order' => $nextOrder,
                ]);
            }
        }

        return response()->json(['status' => 'ok', 'item' => $item]);
    }

    /**
     * Update an existing lookup item (types, sizes, stages, statuses)
     * PATCH /coordinator/work-items/lookups/{table}/{id}
     */
    public function updateLookup(Request $request, $table, $id)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'width' => ['nullable', 'numeric'],
            'height' => ['nullable', 'numeric'],
            'unit' => ['nullable', 'string', 'max:20'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $modelMap = [
            'types' => WorkItemType::class,
            'sizes' => Size::class,
            'stages' => Stage::class,
            'statuses' => Status::class,
        ];

        if (!isset($modelMap[$table])) {
            return response()->json(['error' => 'Invalid table'], 400);
        }

        $modelClass = $modelMap[$table];
        $item = $modelClass::find($id);
        if (!$item) return response()->json(['error' => 'Not found'], 404);

        // validation for duplicates
        if ($table === 'sizes') {
            if (isset($data['width']) && isset($data['height'])) {
                $exists = Size::where('width', $data['width'])->where('height', $data['height'])->where('unit', $data['unit'] ?? $item->unit)->where('id', '!=', $id)->exists();
                if ($exists) return response()->json(['error' => '同じサイズが既に存在します'], 422);
            } else {
                $exists = Size::where('name', $data['name'])->where('id', '!=', $id)->exists();
                if ($exists) return response()->json(['error' => '同じ名前のサイズが既に存在します'], 422);
            }
        } else {
            // types/stages/statuses
            if ($table === 'types' || $table === 'statuses') {
                $slug = Str::slug($data['name']);
                $exists = $modelClass::where('slug', $slug)->where('id', '!=', $id)->exists();
                if ($exists) return response()->json(['error' => '同じ項目が既に存在します'], 422);
                $item->slug = $slug;
            } else {
                $exists = Stage::where('name', $data['name'])->where('id', '!=', $id)->exists();
                if ($exists) return response()->json(['error' => '同じステージ名が既に存在します'], 422);
            }
        }

        // apply updates
        $item->name = $data['name'];
        if ($table === 'sizes') {
            $item->width = $data['width'] ?? $item->width;
            $item->height = $data['height'] ?? $item->height;
            $item->unit = $data['unit'] ?? $item->unit;
        }

        // company/department update if provided
        if (array_key_exists('company_id', $data)) {
            $item->company_id = $data['company_id'];
        }
        if (array_key_exists('department_id', $data)) {
            $item->department_id = $data['department_id'];
        }

        $item->save();

        return response()->json(['status' => 'ok', 'item' => $item]);
    }
}
