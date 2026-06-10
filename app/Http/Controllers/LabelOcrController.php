<?php

namespace App\Http\Controllers;

use App\Models\LabelTestName;
use App\Services\LabelItemPdfParser;
use App\Services\OcrSpaceService;
use App\Services\PrepressImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LabelOcrController extends Controller
{
    /**
     * アイテムPDF（またはスキャン画像）をOCR解析してテスト・アイテム情報を返す。
     *
     * POST /label-ocr/analyze
     */
    public function analyze(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,jpeg,jpg,png|max:20480',
        ]);

        $imageService = app(PrepressImageService::class);
        $stored       = $imageService->convertAndStore($request->file('file'));

        if (!$stored) {
            return response()->json(['error' => '画像変換に失敗しました。Imagickが利用可能か確認してください。'], 422);
        }

        try {
            $absPath = Storage::disk('public')->path($stored['path']);
            $ocrText = app(OcrSpaceService::class)->recognizeFullPage($absPath);

            $dbTestNames = LabelTestName::where('is_active', true)
                ->get(['id', 'name'])
                ->toArray();

            $parsed = app(LabelItemPdfParser::class)->parse($ocrText, $dbTestNames);

            return response()->json([
                'tests'     => $parsed['tests'],
                'items'     => $parsed['items'],
                'ichishiki' => $parsed['ichishiki'],
                'ocr_text'  => $ocrText,
            ]);

        } finally {
            $imageService->delete($stored['path']);
        }
    }
}
