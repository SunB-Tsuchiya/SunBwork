<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksLeaderPermission;
use App\Http\Controllers\Concerns\ResolvesContextCompany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use App\Models\Department;
use App\Models\Stage;
use App\Models\Size;
use App\Models\Status;
use App\Models\WorkItemType;
use App\Models\Difficulty;

class WorkloadSettingController extends Controller
{
    use ChecksLeaderPermission, ResolvesContextCompany;
    /**
     * 各タイプの設定（モデル・ソートカラム・ラベル）
     */
    private function typeConfig(): array
    {
        return [
            'stages' => [
                'model'   => Stage::class,
                'orderBy' => 'order_index',
                'label'   => 'Stages',
            ],
            'work_item_types' => [
                'model'   => WorkItemType::class,
                'orderBy' => 'sort_order',
                'label'   => 'Work Item Types',
            ],
            'sizes' => [
                'model'   => Size::class,
                'orderBy' => 'sort_order',
                'label'   => 'Sizes',
            ],
            'statuses' => [
                'model'   => Status::class,
                'orderBy' => 'sort_order',
                'label'   => 'Statuses',
            ],
            'difficulties' => [
                'model'   => Difficulty::class,
                'orderBy' => 'sort_order',
                'label'   => 'Difficulties',
            ],
        ];
    }

    /**
     * タイプ別バリデーションルール
     * coefficient は decimal(6,3) = 最大 999.999
     */
    private function validationRules(string $type): array
    {
        $base = [
            'items'               => 'array',
            'items.*._deleted'    => 'nullable|boolean',
            'items.*.id'          => 'nullable|integer',
            'items.*.name'        => 'required|string|max:255',
            'items.*.coefficient' => 'nullable|numeric|min:0|max:999.999',
        ];

        $extra = match ($type) {
            'stages' => [
                'items.*.order_index' => 'nullable|integer|min:0',
                'items.*.description' => 'nullable|string|max:1000',
            ],
            'work_item_types' => [
                'items.*.sort_order'  => 'nullable|integer|min:0',
                'items.*.description' => 'nullable|string|max:1000',
                'items.*.group'       => 'nullable|string|max:50',
            ],
            'sizes' => [
                'items.*.sort_order'  => 'nullable|integer|min:0',
                'items.*.label'       => 'nullable|string|max:255',
                'items.*.group'       => 'nullable|string|max:50',
            ],
            'statuses' => [
                'items.*.sort_order'  => 'nullable|integer|min:0',
            ],
            'difficulties' => [
                'items.*.sort_order'  => 'nullable|integer|min:0',
                'items.*.description' => 'nullable|string|max:1000',
            ],
            default => [],
        };

        return array_merge($base, $extra);
    }

    /**
     * 日本語バリデーションエラーメッセージ
     */
    private function validationMessages(): array
    {
        return [
            'items.*.name.required'        => ':position行目：名前は必須です。',
            'items.*.name.max'             => ':position行目：名前は255文字以内で入力してください。',
            'items.*.coefficient.numeric'  => ':position行目：係数は数値で入力してください。',
            'items.*.coefficient.min'      => ':position行目：係数は0以上で入力してください。',
            'items.*.coefficient.max'      => ':position行目：係数は999.999以下で入力してください。',
            'items.*.sort_order.integer'   => ':position行目：順序は整数で入力してください。',
            'items.*.sort_order.min'       => ':position行目：順序は0以上で入力してください。',
            'items.*.order_index.integer'  => ':position行目：順序は整数で入力してください。',
            'items.*.order_index.min'      => ':position行目：順序は0以上で入力してください。',
            'items.*.description.max'      => ':position行目：説明は1000文字以内で入力してください。',
            'items.*.label.max'            => ':position行目：ラベルは255文字以内で入力してください。',
        ];
    }

    /**
     * 一覧表示：全タイプのレコードをまとめて返す
     */
    public function index(Request $request)
    {
        $this->requireLeaderPermission('workload_setting');
        $user      = $request->user();
        $companyId = $this->contextCompanyId() ?? $user?->company_id ?? null;

        // SuperAdmin グローバルモード時は会社未選択警告
        if ($user?->isSuperAdmin() && $this->contextCompanyId() === null) {
            return Inertia::render('WorkloadSetting/Index', [
                'noCompanySelected' => true,
                'departments'       => [],
                'currentScope'      => 'company',
                'canEditScope'      => false,
                'stages'            => [],
                'work_item_types'   => [],
                'sizes'             => [],
                'statuses'          => [],
                'difficulties'      => [],
            ]);
        }

        ['department_id' => $deptId, 'scope_key' => $scopeKey] = $this->resolveScope($request, $user, $companyId);
        $canEditScope = $user->isSuperAdmin() || $user->isAdmin();
        $departments  = $this->fetchDepartments($companyId);

        $stages        = $this->fetchItems(Stage::class,        'order_index', $companyId, $deptId);
        $workItemTypes = $this->fetchItems(WorkItemType::class,  'sort_order',  $companyId, $deptId);
        $sizes         = $this->fetchItems(Size::class,         'sort_order',  $companyId, $deptId);
        $statuses      = $this->fetchItems(Status::class,       'sort_order',  $companyId, $deptId);
        $difficulties  = $this->fetchItems(Difficulty::class,   'sort_order',  $companyId, $deptId);

        // 会社全体スコープの場合のみ部署使用情報を付与
        if ($deptId === null && $departments->isNotEmpty()) {
            $workItemTypes = $this->enrichWithDeptUsage($workItemTypes, $companyId, $departments);
        }

        return Inertia::render('WorkloadSetting/Index', [
            'departments'    => $departments,
            'currentScope'   => $scopeKey,
            'canEditScope'   => $canEditScope,
            'groupOrders'    => $this->fetchGroupOrders('work_item_types', $companyId),
            'stages'         => $stages,
            'work_item_types'=> $workItemTypes,
            'sizes'          => $sizes,
            'statuses'       => $statuses,
            'difficulties'   => $difficulties,
        ]);
    }

    /**
     * 編集ページ：Index ページへリダイレクト（Edit は Index に統合済み）
     */
    public function edit(Request $request, string $type)
    {
        $this->requireLeaderPermission('workload_setting');
        $params = [];
        if ($request->has('dept')) $params['dept'] = $request->query('dept');
        return redirect()->route('workload_setting.index', $params);
    }

    /**
     * 保存：指定タイプのレコードを upsert / delete
     */
    public function store(Request $request, string $type)
    {
        $this->requireLeaderPermission('workload_setting');
        $configs = $this->typeConfig();
        abort_if(!array_key_exists($type, $configs), 404);

        $modelClass = $configs[$type]['model'];

        // 新規追加後に削除マークされた行（IDなし）はバリデーション前に除外
        $items = collect($request->input('items', []))
            ->filter(fn($item) => !(empty($item['id']) && !empty($item['_deleted'])))
            ->values()
            ->all();
        $request->merge(['items' => $items]);

        $payload = $request->validate(
            $this->validationRules($type),
            $this->validationMessages(),
        );

        $user      = $request->user();
        $companyId = $this->contextCompanyId() ?? $user?->company_id ?? null;

        // scope はリクエストボディの 'scope' キーから取得（'company' or '{id}'）
        $scopeRaw = $request->input('scope', 'company');

        // Leader は自部署以外への書き込みを禁止
        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            $leaderDeptId  = $user->department_id;
            $allowedScope  = $leaderDeptId ? (string) $leaderDeptId : 'company';
            if ($scopeRaw !== $allowedScope) {
                abort(403, '自分の部署の設定のみ編集できます。');
            }
        }

        $deptId   = ($scopeRaw === 'company') ? null : (int) $scopeRaw;
        $fillable = (new $modelClass)->getFillable();

        foreach ($payload['items'] ?? [] as $item) {
            // 既存レコードの削除
            if (!empty($item['_deleted']) && !empty($item['id'])) {
                $modelClass::where('id', $item['id'])->delete();
                continue;
            }

            // fillable フィールドを抽出（company_id / department_id は後で設定）
            $data = [];
            foreach ($fillable as $field) {
                if (array_key_exists($field, $item) && !in_array($field, ['company_id', 'department_id'])) {
                    $data[$field] = $item[$field];
                }
            }

            // slug が必要なモデルで未指定の場合は name から自動生成（重複時サフィックス付与）
            if (in_array('slug', $fillable) && empty($data['slug']) && !empty($data['name'])) {
                $base   = Str::slug($data['name']);
                $slug   = $base ?: 'item';
                $suffix = 1;
                while (
                    $modelClass::where('slug', $slug)
                    ->where('id', '!=', $item['id'] ?? 0)
                    ->exists()
                ) {
                    $slug = $base . '-' . $suffix++;
                }
                $data['slug'] = $slug;
            }

            $table = (new $modelClass)->getTable();

            // 会社スコープ
            if ($companyId && Schema::hasColumn($table, 'company_id')) {
                $data['company_id'] = $companyId;
            }

            // 部署スコープ
            if (Schema::hasColumn($table, 'department_id')) {
                $data['department_id'] = $deptId;
            }

            // upsert
            if (!empty($item['id'])) {
                $m = $modelClass::find($item['id']);
                if ($m) {
                    $m->update($data);
                    continue;
                }
            }
            $modelClass::create($data);
        }

        // グループ表示順を保存（work_item_types のみ、group_orders が送られてきた場合）
        $groupOrders = $request->input('group_orders');
        if ($type === 'work_item_types' && is_array($groupOrders) && count($groupOrders) > 0) {
            $this->saveGroupOrders($type, $companyId, $groupOrders);
        }

        // 保存後も同じスコープに留まる
        $redirectParams = $scopeRaw !== 'company' ? ['dept' => $scopeRaw] : [];
        return redirect()->route('workload_setting.index', $redirectParams);
    }

    /**
     * グループ表示順をDBに保存
     */
    private function saveGroupOrders(string $type, ?int $companyId, array $groupOrders): void
    {
        if (!Schema::hasTable('workload_group_orders')) return;

        DB::table('workload_group_orders')
            ->where('type', $type)
            ->where(function ($q) use ($companyId) {
                $q->whereNull('company_id');
                if ($companyId) $q->orWhere('company_id', $companyId);
            })
            ->delete();

        foreach ($groupOrders as $sortIdx => $groupKey) {
            DB::table('workload_group_orders')->insert([
                'company_id' => $companyId,
                'type'       => $type,
                'group_key'  => $groupKey === '__null__' ? null : ($groupKey === '' ? null : $groupKey),
                'sort_order' => (int) $sortIdx,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * 会社全体アイテムに「どの部署で同名アイテムが登録されているか」を付与する
     */
    private function enrichWithDeptUsage($items, int $companyId, $departments)
    {
        $deptItems = WorkItemType::where('company_id', $companyId)
            ->whereNotNull('department_id')
            ->select('name', 'department_id')
            ->get();

        $nameToDeptsMap = $deptItems->groupBy('name')
            ->map(fn($g) => $g->pluck('department_id')->unique()->values()->toArray());

        return $items->map(function ($item) use ($nameToDeptsMap, $departments) {
            $deptIds = $nameToDeptsMap->get($item->name, []);
            $usedByDepts = $departments
                ->filter(fn($d) => in_array($d->id, $deptIds))
                ->map(fn($d) => ['id' => $d->id, 'name' => $d->name])
                ->values();
            return array_merge($item->toArray(), ['usedByDepts' => $usedByDepts->toArray()]);
        })->values();
    }

    /**
     * 部署一覧を取得（会社スコープ）
     */
    private function fetchDepartments(int $companyId)
    {
        return Department::where('company_id', $companyId)
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name']);
    }

    /**
     * スコープを解決する（department_id と scope_key を返す）
     * - SuperAdmin / Admin: クエリパラメータ ?dept= を優先
     * - Leader: 自部署を強制使用
     */
    private function resolveScope(Request $request, $user, int $companyId): array
    {
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            $dept = $request->query('dept', 'company');
            if ($dept === 'company') {
                return ['department_id' => null, 'scope_key' => 'company'];
            }
            $deptId = (int) $dept;
            // 指定部署が当該会社に存在するか確認
            $exists = Department::where('id', $deptId)
                ->where('company_id', $companyId)
                ->where('active', true)
                ->exists();
            if (!$exists) {
                return ['department_id' => null, 'scope_key' => 'company'];
            }
            return ['department_id' => $deptId, 'scope_key' => (string) $deptId];
        }

        // Leader: 自分の department_id を強制使用
        $deptId = $user->department_id;
        return [
            'department_id' => $deptId,
            'scope_key'     => $deptId ? (string) $deptId : 'company',
        ];
    }

    /**
     * モデルのレコードをスコープ付きで取得
     * - departmentId = null: 会社全体（department_id IS NULL）
     * - departmentId = X:   部署固有（department_id = X）
     */
    private function fetchItems(string $modelClass, string $orderBy, ?int $companyId, ?int $departmentId = null)
    {
        $query = $modelClass::orderBy($orderBy);
        $table = (new $modelClass)->getTable();

        if ($companyId && Schema::hasColumn($table, 'company_id')) {
            if ($departmentId === null) {
                // 会社スコープ: company_id が一致するか NULL（グローバル共通レコード）
                $query->where(function ($q) use ($companyId) {
                    $q->whereNull('company_id')->orWhere('company_id', $companyId);
                });
            } else {
                $query->where('company_id', $companyId);
            }
        }

        if (Schema::hasColumn($table, 'department_id')) {
            if ($departmentId === null) {
                $query->whereNull('department_id');
            } else {
                $query->where('department_id', $departmentId);
            }
        }

        return $query->get();
    }

    /**
     * グループ表示順をDBから取得（保存済み順序の group_key 配列を返す）
     */
    private function fetchGroupOrders(string $type, ?int $companyId): array
    {
        if (!Schema::hasTable('workload_group_orders')) return [];

        return DB::table('workload_group_orders')
            ->where('type', $type)
            ->where(function ($q) use ($companyId) {
                $q->whereNull('company_id');
                if ($companyId) $q->orWhere('company_id', $companyId);
            })
            ->orderBy('sort_order')
            ->pluck('group_key')
            ->toArray();
    }
}
