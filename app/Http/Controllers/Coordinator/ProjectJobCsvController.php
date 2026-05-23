<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\NormalizesCsvEncoding;
use App\Models\Client;
use App\Models\PrepresSalesRep;
use App\Models\ProjectJob;
use App\Services\PrepressClientMatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProjectJobCsvController extends Controller
{
    use NormalizesCsvEncoding;

    /**
     * CSV を解析してクライアント/営業担当マッチング結果を返す（確認画面用）
     *
     * 対応列構成（ヘッダーの先頭列が No / 行番号 / 数字 の場合は offset=1）:
     *   [offset+0] 受注No.
     *   [offset+1] 得意先
     *   [offset+2] 品名
     *   [offset+3] 営業担当
     */
    public function analyzeCsv(Request $request): JsonResponse
    {
        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $tmpPath = $this->normalizeCsvToTemp($request->file('csv'));
        $handle  = fopen($tmpPath, 'r');

        // ヘッダー行を読み取り、No列の有無を判定
        $header = fgetcsv($handle);
        $offset = $this->detectNoColumnOffset($header);

        $dbClients   = Client::get(['id', 'name', 'client_code']);
        $dbSalesReps = PrepresSalesRep::orderBy('sort_order')->get(['id', 'name', 'company']);

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) < $offset + 3) continue;

            $jobcode       = PrepressClientMatcher::cleanField($line[$offset]     ?? '');
            $rawClientName = PrepressClientMatcher::cleanField($line[$offset + 1] ?? '');
            $title         = PrepressClientMatcher::cleanField($line[$offset + 2] ?? '');
            $rawSalesRep   = PrepressClientMatcher::cleanField($line[$offset + 3] ?? '');

            if ($jobcode === '' && $title === '') continue;

            $clientMatch   = PrepressClientMatcher::match($rawClientName, $dbClients);
            $salesRepMatch = PrepressClientMatcher::matchSalesRep($rawSalesRep, $dbSalesReps);

            $rows[] = [
                'jobcode'                 => $jobcode,
                'raw_client_name'         => $rawClientName,
                'title'                   => $title,
                'sales_rep'               => $rawSalesRep,
                'status'                  => $clientMatch['status'],
                'candidates'              => $clientMatch['candidates'] ?? [],
                'resolved_client_id'      => isset($clientMatch['client']) ? $clientMatch['client']->id   : null,
                'resolved_client_name'    => isset($clientMatch['client']) ? $clientMatch['client']->name : null,
                'sales_rep_status'        => $salesRepMatch['status'],
                'sales_rep_candidates'    => $salesRepMatch['candidates'] ?? [],
                'resolved_sales_rep_id'   => isset($salesRepMatch['rep']) ? $salesRepMatch['rep']->id   : null,
                'resolved_sales_rep_name' => isset($salesRepMatch['rep']) ? $salesRepMatch['rep']->name : null,
                'showSearch'              => false,
                'searchResults'           => [],
                'showSalesRepSearch'      => false,
                'salesRepSearchResults'   => [],
            ];
        }

        fclose($handle);
        @unlink($tmpPath);

        // 受注番号の重複チェック（DB + CSV内）
        $allJobcodes = array_filter(array_column($rows, 'jobcode'), fn($j) => $j !== '');
        $dbDupJobcodes = [];
        if (!empty($allJobcodes)) {
            $dbDupJobcodes = ProjectJob::whereIn('jobcode', $allJobcodes)
                ->pluck('jobcode')->flip()->all();
        }
        $seenInCsv = [];
        foreach ($rows as &$row) {
            $jc = $row['jobcode'];
            if ($jc === '') {
                $row['jobcode_dup'] = 'none';
            } elseif (isset($dbDupJobcodes[$jc])) {
                $row['jobcode_dup'] = 'db';
                $seenInCsv[$jc]    = true;
            } elseif (isset($seenInCsv[$jc])) {
                $row['jobcode_dup'] = 'csv';
            } else {
                $row['jobcode_dup'] = 'none';
                $seenInCsv[$jc]    = true;
            }
        }
        unset($row);

        return response()->json(['rows' => $rows]);
    }

    /**
     * 確認済みの CSV データを一括保存する
     */
    public function importCsv(Request $request): JsonResponse
    {
        $request->validate([
            'rows'                => ['required', 'array', 'min:1', 'max:500'],
            'rows.*.jobcode'      => ['nullable', 'string', 'max:100'],
            'rows.*.title'        => ['required', 'string', 'max:255'],
            'rows.*.sales_rep'    => ['nullable', 'string', 'max:100'],
            'rows.*.sales_rep_id' => ['nullable', 'integer', 'exists:prepress_sales_reps,id'],
            'rows.*.client_id'    => ['nullable', 'integer', 'exists:clients,id'],
            'rows.*.client_name'  => ['nullable', 'string', 'max:255'],
        ]);

        $userId = $request->user()->id;
        $now    = now();

        $requestedJobcodes = array_filter(
            array_map(fn($r) => $r['jobcode'] ?: null, $request->input('rows'))
        );
        $dbDupJobcodes = [];
        if (!empty($requestedJobcodes)) {
            $dbDupJobcodes = ProjectJob::whereIn('jobcode', $requestedJobcodes)
                ->pluck('jobcode')->flip()->all();
        }

        $inserts     = [];
        $skippedDup  = 0;
        $seenInBatch = [];

        foreach ($request->input('rows') as $row) {
            $jc = $row['jobcode'] ?: null;
            if ($jc !== null) {
                if (isset($dbDupJobcodes[$jc]) || isset($seenInBatch[$jc])) {
                    $skippedDup++;
                    continue;
                }
                $seenInBatch[$jc] = true;
            }

            $inserts[] = [
                'user_id'      => $userId,
                'jobcode'      => $jc,
                'title'        => $row['title'],
                'client_id'    => !empty($row['client_id'])    ? (int) $row['client_id']    : null,
                'sales_rep'    => $row['sales_rep']            ?: null,
                'sales_rep_id' => !empty($row['sales_rep_id']) ? (int) $row['sales_rep_id'] : null,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];
        }

        if (!empty($inserts)) {
            ProjectJob::insert($inserts);
        }

        return response()->json(['imported' => count($inserts), 'skipped_dup' => $skippedDup]);
    }

    /**
     * インラインでクライアントを新規登録する（CSV確認画面用）
     */
    public function apiClientCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'client_code' => ['nullable', 'string', 'max:50'],
        ]);

        // 同名が既存であればそれを返す
        $existing = Client::where('name', $validated['name'])->first();
        if ($existing) {
            return response()->json([
                'client'       => $existing->only(['id', 'name', 'client_code']),
                'was_existing' => true,
            ]);
        }

        $client = Client::create([
            'name'        => $validated['name'],
            'client_code' => $validated['client_code'] ?? null,
        ]);

        return response()->json(['client' => $client->only(['id', 'name', 'client_code'])]);
    }

    /**
     * サンプル CSV をダウンロードする
     */
    public function downloadSample(): Response
    {
        $rows = [
            ['受注No.', '得意先', '品名', '営業担当'],
            ['4600152', 'さんぷる株式会社', 'イオン B2ポスター 728号', '田中'],
            ['4600153', '得意先B',          'テスト案件 初校',         ''],
            ['',        '得意先C',          '□□パンフレット 再校',   '佐藤'],
        ];

        $csv = '';
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $row)) . "\r\n";
        }

        $encoded = mb_convert_encoding($csv, 'SJIS-win', 'UTF-8');

        return response($encoded, 200, [
            'Content-Type'        => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="project_jobs_sample.csv"',
        ]);
    }

    /**
     * ヘッダー行からNo列のオフセットを検出する
     * 先頭列が "No" / "no" / 数字 / 空 の場合は offset=1（No列あり）
     */
    private function detectNoColumnOffset(?array $header): int
    {
        if ($header === null) return 0;
        $first = mb_strtolower(trim(PrepressClientMatcher::cleanField($header[0] ?? '')));
        if ($first === 'no' || $first === 'no.' || $first === '' || is_numeric($first)) {
            return 1;
        }
        return 0;
    }
}
