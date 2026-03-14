<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use App\Models\Stage;
use App\Models\Size;
use App\Models\Status;
use App\Models\WorkItemType;
use App\Models\Difficulty;

class WorkloadSettingController extends Controller
{
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
            ],
            'sizes' => [
                'items.*.sort_order'  => 'nullable|integer|min:0',
                'items.*.label'       => 'nullable|string|max:255',
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
        $user      = $request->user();
        $companyId = $user?->company_id ?? null;

        $stages = $this->fetchItems(Stage::class, 'order_index', $companyId);
        $workItemTypes = $this->fetchItems(WorkItemType::class, 'sort_order', $companyId);
        $sizes = $this->fetchItems(Size::class, 'sort_order', $companyId);
        $statuses = $this->fetchItems(Status::class, 'sort_order', $companyId);
        $difficulties = $this->fetchItems(Difficulty::class, 'sort_order', $companyId);

        return Inertia::render('WorkloadSetting/Index', [
            'stages' => $stages,
            'work_item_types' => $workItemTypes,
            'sizes' => $sizes,
            'statuses' => $statuses,
            'difficulties' => $difficulties,
        ]);
    }

    /**
     * 編集ページ：指定タイプのレコードだけ返す
     */
    public function edit(Request $request, string $type)
    {
        $configs = $this->typeConfig();
        abort_if(!array_key_exists($type, $configs), 404);

        $config    = $configs[$type];
        $user      = $request->user();
        $companyId = $user?->company_id ?? null;

        return Inertia::render('WorkloadSetting/Edit', [
            'type'      => $type,
            'typeLabel' => $config['label'],
            'items'     => $this->fetchItems($config['model'], $config['orderBy'], $companyId),
        ]);
    }

    /**
     * 保存：指定タイプのレコードを upsert / delete
     */
    public function store(Request $request, string $type)
    {
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
        $companyId = $user?->company_id ?? null;
        $fillable  = (new $modelClass)->getFillable();

        foreach ($payload['items'] ?? [] as $item) {
            // 既存レコードの削除
            if (!empty($item['_deleted']) && !empty($item['id'])) {
                $modelClass::where('id', $item['id'])->delete();
                continue;
            }

            // fillable フィールドだけ抽出（company_id は後で設定）
            $data = [];
            foreach ($fillable as $field) {
                if (array_key_exists($field, $item) && $field !== 'company_id') {
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

            // 会社スコープ
            if ($companyId && Schema::hasColumn((new $modelClass)->getTable(), 'company_id')) {
                $data['company_id'] = $companyId;
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

        return redirect()->route('workload_setting.index');
    }

    /**
     * モデルのレコードを会社スコープ付きで取得
     */
    private function fetchItems(string $modelClass, string $orderBy, ?int $companyId)
    {
        $query = $modelClass::orderBy($orderBy);

        if ($companyId && Schema::hasColumn((new $modelClass)->getTable(), 'company_id')) {
            $query->where(function ($q) use ($companyId) {
                $q->whereNull('company_id')->orWhere('company_id', $companyId);
            });
        }

        return $query->get();
    }
}
