<?php

namespace App\Http\Controllers\Portfolio;

use App\Http\Controllers\Controller;
use App\Models\Portfolio\PortfolioCollection;
use App\Models\Portfolio\PortfolioItem;
use App\Models\Portfolio\PortfolioItemPage;
use App\Models\Portfolio\PortfolioTag;
use App\Services\Portfolio\PdfRasterizer;
use App\Services\Portfolio\PortfolioAccess;
use App\Services\Portfolio\PortfolioData;
use App\Services\Portfolio\PortfolioStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * PDF の取り込み（本人のみ）。
 *   init     … 作品を「アップ中」で作る
 *   chunk    … 2MB ずつ追記（さくらの Web 側アップロード上限 5MB 対策）。offset で順序と再送を検証（動画も共用）
 *   complete … PDF として検証し、ページ数を数えて「変換中」にする
 *   process  … 数ページずつ画像化（Web の実行時間上限対策）。全ページ終われば「完了」
 * 途中で失敗・離脱した作品は、本人が削除できる（実体ファイルも消える）。
 */
class PdfUploadController extends Controller
{
    /** PDF 原本の上限 */
    private const MAX_BYTES = 300 * 1024 * 1024;

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
        // 原本に加えてページ画像も保存するので、余裕を見て 2 倍で容量を確認する
        abort_unless(PortfolioStorage::hasRoom($uid, $data['size'] * 2), 422, '保存容量の上限（10GB）を超えるため、アップロードできません。');

        $item = PortfolioItem::create([
            'user_id' => $uid,
            'collection_id' => $data['collection_id'] ?? null,
            'type' => 'pdf',
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'original_name' => $data['original_name'],
            'mime' => 'application/pdf',
            'size_bytes' => $data['size'], // 宣言サイズ。complete で検証し、変換後に実サイズへ更新する
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

    public function chunk(Request $request, int $item)
    {
        $model = $this->own($request, $item, 'uploading', ['pdf', 'video']);
        $request->validate([
            'offset' => 'required|integer|min:0',
            'chunk' => 'required|file|max:3072',
        ]);

        $disk = PortfolioStorage::disk();
        $part = PortfolioStorage::partPath($model);
        $disk->makeDirectory(dirname($part));
        $abs = $disk->path($part);
        $current = file_exists($abs) ? filesize($abs) : 0;

        // 順序・再送の検証。ずれていたら現在のサイズを返し、クライアントはそこから再開できる
        if ((int) $request->input('offset') !== $current) {
            return response()->json(['message' => '送信位置がずれています。', 'size' => $current], 409);
        }

        $bytes = file_get_contents($request->file('chunk')->getRealPath());
        abort_if($current + strlen($bytes) > $model->size_bytes, 422, 'ファイルサイズが申告と一致しません。');
        file_put_contents($abs, $bytes, FILE_APPEND | LOCK_EX);

        return response()->json(['size' => $current + strlen($bytes)]);
    }

    public function complete(Request $request, int $item, PdfRasterizer $rasterizer)
    {
        $model = $this->own($request, $item, 'uploading');
        $disk = PortfolioStorage::disk();
        $part = PortfolioStorage::partPath($model);

        if (! $disk->exists($part) || $disk->size($part) !== (int) $model->size_bytes) {
            $this->discard($model);
            abort(422, 'アップロードが完了していません。もう一度お試しください。');
        }

        $abs = $disk->path($part);
        // 拡張子や申告ではなく、中身が PDF かを確認する
        $head = file_get_contents($abs, false, null, 0, 1024);
        if (! str_contains((string) $head, '%PDF-')) {
            $this->discard($model);
            abort(422, 'PDF ファイルではありません。');
        }

        $pages = $rasterizer->countPages($abs);
        if ($pages < 1) {
            $this->discard($model);
            abort(422, 'PDF を読み込めませんでした。');
        }
        if ($pages > PdfRasterizer::MAX_PAGES) {
            $this->discard($model);
            abort(422, 'ページ数が多すぎます（上限 ' . PdfRasterizer::MAX_PAGES . ' ページ、このファイルは ' . $pages . ' ページ）。');
        }

        $final = PortfolioStorage::dir($model->user_id) . '/' . Str::uuid() . '.pdf';
        $disk->move($part, $final);
        $model->update(['file_path' => $final, 'page_count' => $pages, 'status' => 'processing']);

        return response()->json(['id' => $model->id, 'page_count' => $pages]);
    }

    public function process(Request $request, int $item, PdfRasterizer $rasterizer)
    {
        $model = $this->own($request, $item, 'processing');
        $disk = PortfolioStorage::disk();
        $abs = $disk->path($model->file_path);
        $base = preg_replace('/\.pdf$/', '', $model->file_path);

        $done = $model->pages()->count();
        $target = min($model->page_count, $done + PdfRasterizer::PAGES_PER_CALL);

        try {
            // 変換前の時点で、このページ群がすでに使っている容量（原本は size_bytes に計上済み）
            $generated = (int) $model->pages()->get()->sum(fn ($p) => $disk->exists($p->path) ? $disk->size($p->path) : 0);

            for ($n = $done + 1; $n <= $target; $n++) {
                $out = $rasterizer->renderPage($abs, $n - 1);
                // 単純なベクター PDF でも、ページ画像にすると原本より大きくなることがある。書き込む前に、実際の増加分で上限を確認する
                if (! PortfolioStorage::hasRoom($model->user_id, $generated + strlen($out['blob']))) {
                    $this->discard($model);
                    abort(422, '保存容量の上限（10GB）を超えるため、このPDFは取り込めません。');
                }
                $generated += strlen($out['blob']);
                $path = sprintf('%s_p%03d.jpg', $base, $n);
                $disk->put($path, $out['blob']);
                PortfolioItemPage::updateOrCreate(
                    ['item_id' => $model->id, 'page_no' => $n],
                    ['path' => $path, 'width' => $out['width'], 'height' => $out['height']],
                );
            }

            if ($target >= $model->page_count) {
                $this->finish($model, $rasterizer);
            }
        } catch (HttpExceptionInterface $e) {
            throw $e; // 容量超過など、意図して返した応答はそのまま返す
        } catch (\Throwable $e) {
            report($e);
            $model->update(['status' => 'failed']);
            abort(422, 'PDF のページ変換に失敗しました。ファイルを確認してもう一度お試しください。');
        }

        $model->refresh();

        return response()->json([
            'done' => $model->status === 'ready',
            'processed' => $model->pages()->count(),
            'total' => $model->page_count,
            'item' => $model->status === 'ready' ? PortfolioData::item($model->load('tags')) : null,
        ]);
    }

    /** 全ページ変換後: サムネイル（1 ページ目）・寸法・実サイズを確定して「完了」にする */
    private function finish(PortfolioItem $model, PdfRasterizer $rasterizer): void
    {
        $disk = PortfolioStorage::disk();
        $pages = $model->pages()->get();
        $first = $pages->first();

        $thumbPath = preg_replace('/\.pdf$/', '', $model->file_path) . '_t.jpg';
        $disk->put($thumbPath, $rasterizer->thumbnail($disk->get($first->path)));

        $total = $disk->size($model->file_path) + $disk->size($thumbPath);
        foreach ($pages as $p) {
            $total += $disk->size($p->path);
        }

        $model->update([
            'thumb_path' => $thumbPath,
            'width' => $first->width,
            'height' => $first->height,
            'size_bytes' => $total,
            'status' => 'ready',
        ]);
    }

    /** 検証に落ちた作品を、実体ごと取り除く */
    private function discard(PortfolioItem $model): void
    {
        PortfolioStorage::deleteItemFiles($model);
        $model->delete();
    }

    private function own(Request $request, int $id, string $status, array $types = ['pdf']): PortfolioItem
    {
        $model = PortfolioAccess::editableItem($request, $id);
        abort_unless(in_array($model->type, $types, true), 404);
        abort_unless($model->status === $status, 409, 'この作品は現在この操作を受け付けられません。');

        return $model;
    }
}
