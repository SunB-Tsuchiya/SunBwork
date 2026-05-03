<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OcrSpaceService
{
    private const API_URL = 'https://api.ocr.space/parse/image';

    // =========================================================================
    // 【安全性についてのメモ】
    //
    // このサービスは外部クラウド OCR API（ocr.space / a9t9 software GmbH, Germany）
    // を利用しています。利用にあたっての安全性の考え方を以下に記載します。
    //
    // ■ ocr.space 公式プライバシーポリシー（https://ocr.space/privacypolicy）
    //   "All uploaded documents are deleted after processing. We do not keep any of your data."
    //   → 処理完了後、送信ファイルはサーバーから即時削除。データは一切保持しない。
    //   → GDPR（EU一般データ保護規則）準拠。ドイツ法のデータ保護が適用される。
    //   → IPアドレスのログのみ1ヶ月保存（その後削除）。
    //
    // ■ 本実装での設計上の配慮
    //   - 指示書PDF/JPG全体は送信しない。
    //   - 受注番号行＋品目名行の2行分だけを1枚のクロップ画像として送信する
    //     （APIコール1回 = レート制限に強い、タイムアウトリスク大幅削減）。
    //   - 各クロップは独立した断片であり、どの書類から切り出したかは外部から判別できない。
    //   - 送信は HTTPS（通信経路は暗号化済み）。
    //
    // ■ 将来的に機密性の高い書類が増えた場合の移行先候補
    //   - Azure Computer Vision / Google Cloud Vision（国内リージョン選択可、SOC2準拠）
    //   - セルフホスト Tesseract（Docker / VPS 環境）
    //   - ocr.space Enterprise プラン（GDPR データ処理契約書の締結が可能）
    // =========================================================================

    /**
     * 結合クロップ領域（受注番号行＋品目名行の両データ行をまとめてカバー）
     * 従来の3回→1回のAPIコールに削減し、レート制限タイムアウトを回避する。
     *
     * 伝票レイアウト:
     *   row1 (y≈8.8%〜12.8%): [受注番号 label][黄:受注番号][得意先 label][コード][緑:クライアント名]
     *   row2 (y≈12.7%〜16.7%): [品名 label][青:品目名テキスト]
     *
     *   ↓ 2行をまとめて1クロップ → 1 APIコール
     *   combined (y=8.8%〜17.0%)
     */
    private const COMBINED_REGION = [0.003, 0.088, 0.660, 0.170];

    // Imagick で拡大する際の最低高さ（px）。1行あたりの基準。
    private const MIN_CROP_HEIGHT_PX = 80;

    public function analyze(string $storagePath): array
    {
        $absPath = Storage::disk('public')->path($storagePath);

        // セキュリティ: ディレクトリトラバーサル防止
        $realPath = realpath($absPath);
        $baseDir  = realpath(Storage::disk('public')->path(''));
        if (!$realPath || !str_starts_with($realPath, $baseDir)) {
            Log::warning('OcrSpaceService: invalid path rejected', ['path' => $storagePath]);
            return $this->emptyResult();
        }

        $apiKey = config('services.ocr_space.api_key');
        if (!$apiKey) {
            Log::error('OcrSpaceService: OCR_SPACE_API_KEY not configured');
            return $this->emptyResult();
        }

        if (!extension_loaded('imagick') || !class_exists(\Imagick::class)) {
            Log::error('OcrSpaceService: Imagick not available');
            return $this->emptyResult();
        }

        try {
            $imagick = new \Imagick($realPath);
            $w = $imagick->getImageWidth();
            $h = $imagick->getImageHeight();

            // 1回のAPIコールで全フィールドを取得（レート制限対策）
            $rawText = $this->fetchCombinedText($imagick, $w, $h, $apiKey);

            // 失敗時: 4秒待ってリトライ1回
            if ($rawText === null) {
                Log::info('OcrSpaceService: first attempt failed, retrying after 4s...');
                sleep(4);
                $rawText = $this->fetchCombinedText($imagick, $w, $h, $apiKey);
            }

            $imagick->clear();
            $imagick->destroy();

            $rawText = $rawText ?? '';

            Log::info('OcrSpaceService: raw OCR text', ['text' => $rawText]);

            $jobcode    = $this->parseJobcode($rawText);
            $clientName = $this->parseClientName($rawText);
            $title      = $this->parseTitle($rawText);

            $matchedClients = $this->searchClients($clientName);

            Log::info('OcrSpaceService: OCR completed', [
                'jobcode'     => $jobcode,
                'client_name' => $clientName,
                'title'       => mb_substr($title, 0, 50),
            ]);

            return [
                'jobcode'         => $jobcode,
                'client_name'     => $clientName,
                'title'           => $title,
                'matched_clients' => $matchedClients,
            ];

        } catch (\Throwable $e) {
            Log::error('OcrSpaceService: exception', ['message' => $e->getMessage()]);
            return $this->emptyResult();
        }
    }

    /**
     * 受注番号行＋品目名行を1枚の結合クロップとしてOCRし、rawテキストを返す。
     * APIエラー・タイムアウト時は null を返す（呼び出し側でリトライ）。
     */
    private function fetchCombinedText(\Imagick $imagick, int $w, int $h, string $apiKey): ?string
    {
        [$x1p, $y1p, $x2p, $y2p] = self::COMBINED_REGION;

        $x  = (int)($w * $x1p);
        $y  = (int)($h * $y1p);
        $cw = max(1, (int)($w * ($x2p - $x1p)));
        $ch = max(1, (int)($h * ($y2p - $y1p)));

        $crop = clone $imagick;
        $crop->cropImage($cw, $ch, $x, $y);
        $crop->setImagePage($cw, $ch, 0, 0);

        // 2行分の高さが不足する場合は拡大
        if ($ch < self::MIN_CROP_HEIGHT_PX * 2) {
            $scale = (int)ceil(self::MIN_CROP_HEIGHT_PX * 2 / $ch);
            $crop->resizeImage($cw * $scale, $ch * $scale, \Imagick::FILTER_LANCZOS, 1);
        }

        $crop->transformImageColorspace(\Imagick::COLORSPACE_GRAY);
        $crop->normalizeImage();
        $crop->setImageFormat('jpeg');
        $crop->setImageCompressionQuality(95);
        $jpegBlob = $crop->getImageBlob();
        $crop->clear();
        $crop->destroy();

        $base64 = base64_encode($jpegBlob);

        try {
            $response = Http::timeout(25)->asForm()->post(self::API_URL, [
                'apikey'            => $apiKey,
                'base64Image'       => 'data:image/jpeg;base64,' . $base64,
                'language'          => 'jpn',
                'OCREngine'         => '2',
                'isOverlayRequired' => 'false',
                'scale'             => 'true',
                'isTable'           => 'false',
            ]);

            if (!$response->successful()) {
                Log::warning('OcrSpaceService: API error', ['status' => $response->status()]);
                return null;
            }

            $data = $response->json();
            if ($data['IsErroredOnProcessing'] ?? false) {
                Log::warning('OcrSpaceService: processing error', ['data' => $data]);
                return null;
            }

            return $data['ParsedResults'][0]['ParsedText'] ?? '';

        } catch (\Throwable $e) {
            Log::warning('OcrSpaceService: API call failed', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * rawテキストから受注番号（5〜12桁の数字列）を抽出する。
     * rawテキストから受注番号を抽出する。
     * OCR結果例: "受注番 号\n4505963\n品名\n「得意先\n05660\n（株）文化工房\n..."
     * → 7〜10桁の連続数字行を受注番号とする。
     */
    private function parseJobcode(string $text): string
    {
        // 「受注番号」ラベルの後（OCRミス "受注番 号" にも対応）
        if (preg_match('/受注[\s　番]*号[\s　\r\n]*(\d{5,12})/u', $text, $m)) {
            return $m[1];
        }
        // フォールバック: 7〜10桁の単独数字（得意先コード5桁と区別）
        if (preg_match('/\b(\d{7,10})\b/', $text, $m)) {
            return $m[1];
        }
        return '';
    }

    /**
     * rawテキストからクライアント名を抽出する。
     * OCRは「得意先コード（4〜6桁数字の行）」の直後の行をクライアント名として返す。
     * 列単位で読まれるためコードと名前が別行になることが多い。
     */
    private function parseClientName(string $text): string
    {
        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $text)),
            fn($l) => $l !== ''
        ));

        // 4〜6桁の数字行（jobcodeと区別するため7桁以上は除外）の直後の行がクライアント名
        foreach ($lines as $i => $line) {
            $digits = preg_replace('/[^0-9]/', '', $line);
            if (strlen($digits) >= 4 && strlen($digits) <= 6 && !preg_match('/\d{7,}/', $line)) {
                for ($j = $i + 1; $j < count($lines); $j++) {
                    $next = $lines[$j];
                    if (mb_strlen($next) >= 2 && !preg_match('/^\d+$/', $next)) {
                        return $next;
                    }
                }
            }
        }

        // フォールバック: 「得意先」ラベル+コードの後のテキスト（同行または改行）
        if (preg_match('/得意先.{0,30}\d{4,6}.{0,5}[\r\n]+([^\d\r\n][^\r\n]*)/us', $text, $m)) {
            $name = trim($m[1]);
            if (mb_strlen($name) >= 2) return $name;
        }

        return '';
    }

    /**
     * rawテキストから品目名を抽出する。
     * クライアント名行（コード行の次）の後に続く行群が品目名。
     * OCRの列読み取り順の都合で「品名」ラベルがクライアント列より先に来るため、
     * 「品名」ラベルではなくクライアント名行位置を基準に抽出する。
     */
    private function parseTitle(string $text): string
    {
        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $text)),
            fn($l) => $l !== ''
        ));

        // クライアント名行のインデックスを特定（parseClientNameと同じロジック）
        $clientNameIdx = -1;
        foreach ($lines as $i => $line) {
            $digits = preg_replace('/[^0-9]/', '', $line);
            if (strlen($digits) >= 4 && strlen($digits) <= 6 && !preg_match('/\d{7,}/', $line)) {
                for ($j = $i + 1; $j < count($lines); $j++) {
                    if (mb_strlen($lines[$j]) >= 2 && !preg_match('/^\d+$/', $lines[$j])) {
                        $clientNameIdx = $j;
                        break;
                    }
                }
                break;
            }
        }

        if ($clientNameIdx >= 0) {
            $titleLines = array_slice($lines, $clientNameIdx + 1);
            $titleLines = array_filter($titleLines, fn($l) => mb_strlen($l) >= 2);
            if (!empty($titleLines)) {
                return trim(implode(' ', $titleLines));
            }
        }

        // フォールバック: 「品名」ラベルの後のテキスト
        if (preg_match('/(?:品名|品目)[\s　\r\n]+(.+)/us', $text, $m)) {
            $after = trim(preg_replace('/\s+/', ' ', $m[1]));
            if (mb_strlen($after) >= 2) return $after;
        }

        return '';
    }

    /**
     * 会社形態を表す決まり語句のリスト。
     * DB 検索前に OCR 読み取り名と検索キーワード双方から除去して正規化する。
     */
    private const COMPANY_WORDS = [
        '株式会社', '有限会社', '合同会社', '合資会社', '合名会社',
        '一般社団法人', '一般財団法人', '公益社団法人', '公益財団法人',
        '特定非営利活動法人', 'NPO法人', '医療法人', '学校法人',
        '（株）', '(株)', '㈱', '（有）', '(有)', '㈲',
        '（合）', '(合)', '（名）', '(名)',
    ];

    /**
     * 会社形態語句を除去して本体名だけを返す。
     * 例: "（株）文化工房" → "文化工房"
     *     "株式会社ABC商事" → "ABC商事"
     */
    private function normalizeCompanyName(string $name): string
    {
        $normalized = $name;
        foreach (self::COMPANY_WORDS as $word) {
            $normalized = str_replace($word, '', $normalized);
        }
        return trim(preg_replace('/[\s　]+/u', ' ', $normalized));
    }

    /**
     * クライアント名でDB部分一致検索（最大10件）。
     *
     * 検索戦略（3段階）:
     *   1. 元の名称でそのまま部分一致
     *   2. 会社形態語句を除去した本体名で部分一致
     *   3. 本体名をさらに半角スペース区切りで分割し、各単語でAND検索
     *
     * 結果は重複排除後にクライアント名順で返す。
     */
    public function searchClients(string $name): array
    {
        $name = trim($name);
        if (mb_strlen($name) < 2) {
            return [];
        }

        $ids      = [];
        $results  = [];

        // ── Step 1: 元の名称で部分一致 ──────────────────────────────────────
        $step1 = Client::where('name', 'like', '%' . $name . '%')
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'is_dormant']);

        foreach ($step1 as $c) {
            if (!in_array($c->id, $ids, true)) {
                $ids[]     = $c->id;
                $results[] = $c->toArray();
            }
        }

        // ── Step 2: 会社形態語句を除去した本体名で部分一致 ──────────────────
        $normalized = $this->normalizeCompanyName($name);

        if ($normalized !== $name && mb_strlen($normalized) >= 2) {
            $step2 = Client::where('name', 'like', '%' . $normalized . '%')
                ->orderBy('name')
                ->limit(10)
                ->get(['id', 'name', 'is_dormant']);

            foreach ($step2 as $c) {
                if (!in_array($c->id, $ids, true)) {
                    $ids[]     = $c->id;
                    $results[] = $c->toArray();
                }
            }
        }

        // ── Step 3: 本体名をスペース分割して各単語でAND検索 ─────────────────
        $keywords = array_filter(
            preg_split('/[\s　]+/u', $normalized),
            fn($k) => mb_strlen($k) >= 2
        );

        if (count($keywords) > 1) {
            $query = Client::query();
            foreach ($keywords as $kw) {
                $query->where('name', 'like', '%' . $kw . '%');
            }
            $step3 = $query->orderBy('name')->limit(10)->get(['id', 'name', 'is_dormant']);

            foreach ($step3 as $c) {
                if (!in_array($c->id, $ids, true)) {
                    $ids[]     = $c->id;
                    $results[] = $c->toArray();
                }
            }
        }

        // 最大10件に絞り込んで返す
        return array_slice($results, 0, 10);
    }

    private function emptyResult(): array
    {
        return [
            'jobcode'         => '',
            'client_name'     => '',
            'title'           => '',
            'matched_clients' => [],
        ];
    }
}
