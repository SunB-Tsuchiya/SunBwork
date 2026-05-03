<?php

namespace App\Http\Controllers\Prepress;

use App\Http\Controllers\Controller;
use App\Services\OcrSpaceService;
use App\Services\PrepressImageService;
use Illuminate\Http\Request;

class TicketOcrController extends Controller
{
    public function __construct(
        private PrepressImageService $imageService,
        private OcrSpaceService $ocrService,
    ) {}

    /**
     * POST /prepress/ocr/analyze
     *
     * アップロードされた画像/PDFをJPGに変換し、ocr.space APIでOCR解析する。
     * メインフォームへの一時的な解析専用エンドポイント（DBには保存しない）。
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function analyze(Request $request)
    {
        $this->authorizePrepress($request->user());

        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,heic,heif,pdf', 'max:20480'],
        ]);

        $file = $request->file('image');

        // JPG変換・一時保存（PrepressImageService を流用）
        $imageMeta = $this->imageService->convertAndStore($file);

        if (!$imageMeta || empty($imageMeta['path'])) {
            return response()->json([
                'error'    => '画像の変換に失敗しました。',
                'jobcode'  => '',
                'client_name' => '',
                'title'    => '',
                'matched_clients' => [],
                'image_url' => null,
                'tmp_image_path' => null,
            ], 422);
        }

        $storagePath = $imageMeta['path'];

        // OCR解析
        $ocrResult = $this->ocrService->analyze($storagePath);

        return response()->json([
            'jobcode'          => $ocrResult['jobcode']     ?? '',
            'client_name'      => $ocrResult['client_name'] ?? '',
            'title'            => $ocrResult['title']       ?? '',
            'matched_clients'  => $ocrResult['matched_clients'] ?? [],
            'image_url'        => \Illuminate\Support\Facades\Storage::disk('public')->url($storagePath),
            'tmp_image_path'   => $storagePath,
            'original_filename' => $imageMeta['original_filename'] ?? $file->getClientOriginalName(),
        ]);
    }

    protected function authorizePrepress($user): void
    {
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return;
        }
        if (!$user->department || $user->department->name !== '製版') {
            abort(403, 'Prepress エリアは製版部署のみアクセスできます。');
        }
    }
}
