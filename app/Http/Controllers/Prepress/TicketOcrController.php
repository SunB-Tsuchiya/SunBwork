<?php

namespace App\Http\Controllers\Prepress;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Prepress\Concerns\AuthorizesPrepress;
use App\Models\Client;
use App\Services\OcrSpaceService;
use App\Services\PrepressImageService;
use Illuminate\Http\Request;

class TicketOcrController extends Controller
{
    use AuthorizesPrepress;

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

        // matched_clients に in_department フラグを付与
        $deptId = $this->getPrepressDeptId($request->user());
        $matchedClients = $ocrResult['matched_clients'] ?? [];
        if ($deptId && !empty($matchedClients)) {
            $clientIds = array_column($matchedClients, 'id');
            $inDeptIds = Client::whereIn('id', $clientIds)
                ->whereHas('departments', fn($q) => $q->where('department_id', $deptId))
                ->pluck('id')
                ->toArray();
            $matchedClients = array_map(fn($c) => array_merge($c, [
                'in_department' => in_array($c['id'], $inDeptIds, true),
            ]), $matchedClients);
        }

        return response()->json([
            'jobcode'          => $ocrResult['jobcode']     ?? '',
            'client_name'      => $ocrResult['client_name'] ?? '',
            'title'            => $ocrResult['title']       ?? '',
            'matched_clients'  => $matchedClients,
            'image_url'        => \Illuminate\Support\Facades\Storage::disk('public')->url($storagePath),
            'tmp_image_path'   => $storagePath,
            'original_filename' => $imageMeta['original_filename'] ?? $file->getClientOriginalName(),
        ]);
    }

    /**
     * POST /prepress/ocr/clients/{client}/attach-department
     *
     * ログインユーザーの部署にクライアントを紐づける（OCRモーダルから呼ぶ専用API）。
     * toggleではなく attach のみ（外すことはしない）。
     */
    public function attachClientToDepartment(Request $request, Client $client)
    {
        $this->authorizePrepress($request->user());

        $user = $request->user();
        $deptId = $this->getPrepressDeptId($user);
        if (!$deptId) {
            return response()->json(['error' => '部署が設定されていません。'], 422);
        }

        // 既に紐づいていても syncWithoutDetaching で安全に追加
        $client->departments()->syncWithoutDetaching([$deptId]);

        return response()->json(['ok' => true, 'client_name' => $client->name]);
    }

}
