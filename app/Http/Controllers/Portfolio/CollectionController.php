<?php

namespace App\Http\Controllers\Portfolio;

use App\Http\Controllers\Controller;
use App\Models\Portfolio\PortfolioCollection;
use App\Services\Portfolio\PortfolioAccess;
use Illuminate\Http\Request;

/** コレクション（フォルダ）の CRUD・並べ替え。本人分のみ（Admin は閲覧専用のため書き込み不可） */
class CollectionController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
        ]);
        $uid = PortfolioAccess::editOwnerId($request);

        $collection = PortfolioCollection::create($data + [
            'user_id' => $uid,
            'sort_order' => (int) PortfolioCollection::where('user_id', $uid)->max('sort_order') + 1,
        ]);

        return response()->json(['id' => $collection->id], 201);
    }

    public function update(Request $request, int $collection)
    {
        $model = $this->own($request, $collection);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
            'cover_item_id' => 'nullable|integer',
        ]);

        // 表紙は、そのコレクション内の自分の作品だけ指定できる
        if (! empty($data['cover_item_id'])) {
            abort_unless(
                $model->items()->where('id', $data['cover_item_id'])->exists(),
                422,
                '表紙にできるのは、このコレクション内の作品だけです。'
            );
        }
        $model->update($data);

        return response()->json(['ok' => true]);
    }

    /** 作品は消さず「未分類」に戻る（FK nullOnDelete） */
    public function destroy(Request $request, int $collection)
    {
        $this->own($request, $collection)->delete();

        return response()->json(['ok' => true]);
    }

    public function reorder(Request $request)
    {
        $data = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $uid = PortfolioAccess::editOwnerId($request);

        foreach (array_values($data['ids']) as $i => $id) {
            PortfolioCollection::where('user_id', $uid)->where('id', $id)->update(['sort_order' => $i]);
        }

        return response()->json(['ok' => true]);
    }

    private function own(Request $request, int $id): PortfolioCollection
    {
        return PortfolioAccess::editableCollection($request, $id);
    }
}
