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

/**
 * 動画の取り込み（本人のみ）。さくらに ffmpeg が無いため、圧縮はブラウザ側（WebCodecs）で済ませてから送る。
 *   init           … 作品を「アップ中」で作る
 *   chunk          … PdfUploadController::chunk と共通（2MB ずつ追記）
 *   video-complete … MP4/MOV として検証し、ポスター画像・サムネイルを作って「完了」にする
 * 失敗した作品は破棄する（実体ファイルも消える）。
 */
class VideoUploadController extends Controller
{
    /** ブラウザで圧縮した後の上限（PLAN §5.3。商品紹介などの短尺が中心。長尺でも通せる余裕を持たせる） */
    private const MAX_BYTES = 1024 * 1024 * 1024;

    public function init(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:2000',
            'collection_id' => 'nullable|integer',
            'tags' => 'nullable|array|max:20',
            'tags.*' => 'string|max:50',
            'original_name' => 'required|string|max:255',
            'size' => 'required|integer|min:1|max:' . self::MAX_BYTES,
        ]);
        $uid = PortfolioAccess::editOwnerId($request);

        if (! empty($data['collection_id'])) {
            abort_unless(PortfolioCollection::where('user_id', $uid)->whereKey($data['collection_id'])->exists(), 422, 'コレクションが不正です。');
        }
        abort_unless(PortfolioStorage::hasRoom($uid, $data['size']), 422, '保存容量の上限（10GB）を超えるため、アップロードできません。');

        $item = PortfolioItem::create([
            'user_id' => $uid,
            'collection_id' => $data['collection_id'] ?? null,
            'type' => 'video',
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'original_name' => $data['original_name'],
            'mime' => 'video/mp4',
            'size_bytes' => $data['size'], // 宣言サイズ。complete で検証し、実サイズへ更新する
            'status' => 'uploading',
            'sort_order' => (int) PortfolioItem::where('user_id', $uid)->max('sort_order') + 1,
        ]);

        if (! empty($data['tags'])) {
            $ids = collect($data['tags'])->map(fn ($n) => trim($n))->filter()->unique()
                ->map(fn ($n) => PortfolioTag::firstOrCreate(['user_id' => $uid, 'name' => $n])->id);
            $item->tags()->sync($ids->all());
        }

        return response()->json(['id' => $item->id], 201);
    }

    public function complete(Request $request, int $item, ImageProcessor $processor)
    {
        $model = PortfolioAccess::editableItem($request, $item);
        abort_unless($model->type === 'video', 404);
        abort_unless($model->status === 'uploading', 409, 'この作品は現在この操作を受け付けられません。');

        $data = $request->validate([
            'poster' => 'required|file|mimetypes:image/jpeg,image/png,image/webp|max:4096',
            'width' => 'required|integer|min:1|max:8192',
            'height' => 'required|integer|min:1|max:8192',
            'duration' => 'required|numeric|min:0|max:86400',
        ]);

        $disk = PortfolioStorage::disk();
        $part = PortfolioStorage::partPath($model);

        if (! $disk->exists($part) || $disk->size($part) !== (int) $model->size_bytes) {
            $this->discard($model);
            abort(422, 'アップロードが完了していません。もう一度お試しください。');
        }

        // 拡張子や申告ではなく、中身が MP4 / MOV（先頭 4〜8 バイトが ftyp）かを確認する
        $head = (string) file_get_contents($disk->path($part), false, null, 0, 16);
        if (substr($head, 4, 4) !== 'ftyp') {
            $this->discard($model);
            abort(422, '動画ファイル（MP4 / MOV）ではありません。');
        }
        $isMov = substr($head, 8, 4) === 'qt  ';

        try {
            $poster = $processor->process($request->file('poster')->getRealPath());
        } catch (\Throwable $e) {
            report($e);
            $this->discard($model);
            abort(422, 'ポスター画像を読み込めませんでした。');
        }

        $base = PortfolioStorage::dir($model->user_id) . '/' . Str::uuid();
        $videoPath = $base . ($isMov ? '.mov' : '.mp4');
        $disk->move($part, $videoPath);
        $disk->put("{$base}_poster.jpg", $poster['main']);
        $disk->put("{$base}_t.jpg", $poster['thumb']);

        $model->update([
            'file_path' => $videoPath,
            'poster_path' => "{$base}_poster.jpg",
            'thumb_path' => "{$base}_t.jpg",
            'mime' => $isMov ? 'video/quicktime' : 'video/mp4',
            'width' => $data['width'],
            'height' => $data['height'],
            'duration_sec' => (int) round($data['duration']),
            'size_bytes' => $disk->size($videoPath) + strlen($poster['main']) + strlen($poster['thumb']),
            'status' => 'ready',
        ]);

        return response()->json(PortfolioData::item($model->fresh('tags')));
    }

    /** 検証に落ちた作品を、実体ごと取り除く */
    private function discard(PortfolioItem $model): void
    {
        PortfolioStorage::deleteItemFiles($model);
        $model->delete();
    }
}
