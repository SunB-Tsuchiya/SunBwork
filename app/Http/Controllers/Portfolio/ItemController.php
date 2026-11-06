<?php

namespace App\Http\Controllers\Portfolio;

use App\Http\Controllers\Controller;
use App\Models\Portfolio\PortfolioCollection;
use App\Models\Portfolio\PortfolioItem;
use App\Models\Portfolio\PortfolioTag;
use App\Services\Portfolio\ImageProcessor;
use App\Services\Portfolio\PortfolioAccess;
use App\Services\Portfolio\PortfolioData;
use App\Services\Portfolio\PortfolioStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** 作品の登録・編集・削除・並べ替え。本人分のみ（Admin は閲覧専用のため書き込みは 403） */
class ItemController extends Controller
{
    /** 画像のアップロード（PDF・動画は Phase 3/4 で追加） */
    public function store(Request $request, ImageProcessor $processor)
    {
        $data = $request->validate([
            // finfo で中身の MIME を判定する（拡張子は信用しない）
            'file' => 'required|file|mimetypes:image/jpeg,image/png,image/webp|max:20480',
            'collection_id' => 'nullable|integer',
            'title' => 'nullable|string|max:150',
            'description' => 'nullable|string|max:2000',
            'tags' => 'nullable|array|max:20',
            'tags.*' => 'string|max:50',
            'owner_id' => 'nullable|integer',
        ]);
        $uid = PortfolioAccess::editOwnerId($request);
        $file = $request->file('file');

        if (! empty($data['collection_id'])) {
            abort_unless(PortfolioCollection::where('user_id', $uid)->whereKey($data['collection_id'])->exists(), 422, 'コレクションが不正です。');
        }

        try {
            $out = $processor->process($file->getRealPath());
        } catch (\Throwable $e) {
            report($e);
            abort(422, '画像を読み込めませんでした。');
        }

        $bytes = strlen($out['main']) + strlen($out['thumb']);
        abort_unless(PortfolioStorage::hasRoom($uid, $bytes), 422, '保存容量の上限（10GB）を超えるため、アップロードできません。');

        $base = PortfolioStorage::dir($uid) . '/' . Str::uuid();
        $disk = PortfolioStorage::disk();
        $disk->put("{$base}.jpg", $out['main']);
        $disk->put("{$base}_t.jpg", $out['thumb']);

        $item = PortfolioItem::create([
            'user_id' => $uid,
            'collection_id' => $data['collection_id'] ?? null,
            'type' => 'image',
            'title' => ($data['title'] ?? null) ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'description' => $data['description'] ?? null,
            'original_name' => $file->getClientOriginalName(),
            'mime' => 'image/jpeg',
            'size_bytes' => $bytes,
            'file_path' => "{$base}.jpg",
            'thumb_path' => "{$base}_t.jpg",
            'width' => $out['width'],
            'height' => $out['height'],
            'status' => 'ready',
            'sort_order' => (int) PortfolioItem::where('user_id', $uid)->max('sort_order') + 1,
        ]);

        if (! empty($data['tags'])) {
            $this->syncTags($uid, $item, $data['tags']);
        }

        return response()->json(PortfolioData::item($item->load('tags')), 201);
    }

    public function update(Request $request, int $item)
    {
        $model = $this->own($request, $item);
        $uid = $model->user_id;

        $data = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:2000',
            'collection_id' => 'nullable|integer',
            'tags' => 'nullable|array|max:20',
            'tags.*' => 'string|max:50',
        ]);

        if (! empty($data['collection_id'])) {
            abort_unless(PortfolioCollection::where('user_id', $uid)->whereKey($data['collection_id'])->exists(), 422, 'コレクションが不正です。');
        }

        $model->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'collection_id' => $data['collection_id'] ?? null,
        ]);

        if (array_key_exists('tags', $data)) {
            $this->syncTags($uid, $model, $data['tags'] ?? []);
        }

        return response()->json(PortfolioData::item($model->fresh('tags')));
    }

    /** ファイル実体も削除する（ゴミ箱は作らない） */
    public function destroy(Request $request, int $item)
    {
        $model = $this->own($request, $item)->load('pages');
        PortfolioStorage::deleteItemFiles($model);

        // 表紙に使われていたら外す
        PortfolioCollection::where('user_id', $model->user_id)->where('cover_item_id', $model->id)->update(['cover_item_id' => null]);
        $model->delete();
        $this->deleteUnusedTags($model->user_id);

        return response()->json(['ok' => true]);
    }

    public function reorder(Request $request)
    {
        $data = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $uid = PortfolioAccess::editOwnerId($request);

        foreach (array_values($data['ids']) as $i => $id) {
            PortfolioItem::where('user_id', $uid)->where('id', $id)->update(['sort_order' => $i]);
        }

        return response()->json(['ok' => true]);
    }

    private function syncTags(int $uid, PortfolioItem $item, array $names): void
    {
        $ids = collect($names)
            ->map(fn ($n) => trim($n))->filter()->unique()
            ->map(fn ($n) => PortfolioTag::firstOrCreate(['user_id' => $uid, 'name' => $n])->id);
        $item->tags()->sync($ids->all());
        $this->deleteUnusedTags($uid);
    }

    private function deleteUnusedTags(int $uid): void
    {
        PortfolioTag::where('user_id', $uid)->whereDoesntHave('items')->delete();
    }

    private function own(Request $request, int $id): PortfolioItem
    {
        return PortfolioAccess::editableItem($request, $id);
    }
}
