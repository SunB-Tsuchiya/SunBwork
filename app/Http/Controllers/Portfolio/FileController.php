<?php

namespace App\Http\Controllers\Portfolio;

use App\Http\Controllers\Controller;
use App\Services\Portfolio\PortfolioAccess;
use App\Services\Portfolio\PortfolioStorage;
use Illuminate\Http\Request;

/**
 * 認証付きファイル配信（本人、または閲覧権限のある Admin/SuperAdmin）。公開ディレクトリには置かないため、必ずここを通す。
 * BinaryFileResponse は Range・ETag に対応する（動画のシーク用）。
 */
class FileController extends Controller
{
    public function show(Request $request, int $item, string $kind)
    {
        abort_unless(in_array($kind, ['file', 'thumb', 'poster'], true), 404);

        $model = PortfolioAccess::viewableItem($request, $item);
        $path = match ($kind) {
            'thumb' => $model->thumb_path,
            'poster' => $model->poster_path,
            default => $model->file_path,
        };
        abort_unless($path && PortfolioStorage::disk()->exists($path), 404);

        return response()->file(PortfolioStorage::disk()->path($path), [
            'Content-Type' => $kind === 'file' ? $model->mime : 'image/jpeg',
            // 本人専用の私的データ。ブラウザには短期キャッシュさせ、共有キャッシュには載せない
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /** PDF のページ画像（本人、または閲覧権限のある Admin/SuperAdmin） */
    public function page(Request $request, int $item, int $page)
    {
        $model = PortfolioAccess::viewableItem($request, $item);
        abort_unless($model->type === 'pdf' && $model->status === 'ready', 404);

        $row = $model->pages()->where('page_no', $page)->first();
        abort_unless($row && PortfolioStorage::disk()->exists($row->path), 404);

        return response()->file(PortfolioStorage::disk()->path($row->path), [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
