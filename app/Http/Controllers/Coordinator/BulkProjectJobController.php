<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\NormalizesCsvEncoding;
use App\Models\Client;
use App\Models\ProjectJob;
use App\Models\ProjectJobTemplate;
use App\Models\ProjectTeamMember;
use App\Models\Size;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BulkProjectJobController extends Controller
{
    use NormalizesCsvEncoding;

    /** 一括作成ハブページ */
    public function index(Request $request)
    {
        $user      = $request->user();
        $userId    = $user->id;
        $companyId = $this->resolveCompanyId($user);

        $templates = ProjectJobTemplate::where('is_shared', true)
            ->orWhere('created_by', $userId)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn($t) => [
                'id'           => $t->id,
                'name'         => $t->name,
                'description'  => $t->description,
                'fixed_fields' => $t->fixed_fields ?? [],
                'team_members' => $t->team_members ?? [],
                'is_shared'    => $t->is_shared,
                'created_by'   => $t->created_by,
            ]);

        $coordinatorCandidates = User::where(function ($q) {
                $q->where('user_role', 'coordinator')
                  ->orWhere('user_role', 'clerk')
                  ->orWhereHas('assignment', fn($q2) => $q2->where('code', 'shinko'));
            })
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->ordered()
            ->get(['id', 'name']);

        $sizes = Size::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group']);

        $users = User::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->ordered()->get(['id', 'name']);

        // チームメンバー選択モーダル用のデータ（同一会社のみ）
        $departments = $companyId
            ? \App\Models\Department::where('company_id', $companyId)->orderBy('name')->get()
            : \App\Models\Department::all();
        $assignments = \App\Models\Assignment::all();
        $members = User::when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->ordered()->with(['department', 'assignment'])->get();

        return Inertia::render('Coordinator/ProjectJobs/BulkCreate', [
            'templates'             => $templates,
            'coordinatorCandidates' => $coordinatorCandidates,
            'sizes'                 => $sizes,
            'users'                 => $users,
            'departments'           => $departments,
            'assignments'           => $assignments,
            'members'               => $members,
        ]);
    }

    /**
     * サンプルCSVダウンロード
     * GET ?template_id=1
     */
    public function downloadSample(Request $request)
    {
        $templateId = $request->query('template_id');
        $template   = $templateId ? ProjectJobTemplate::findOrFail($templateId) : null;
        $fixed      = $template?->fixed_fields ?? [];

        $headers = ['jobcode', 'title'];
        if (empty($fixed['client_id']))                     $headers[] = 'client_name';
        if (empty($fixed['user_id']))                       $headers[] = 'leader_name';
        if (empty($fixed['size_id']))                       $headers[] = 'size_name';
        if (!array_key_exists('page_count', $fixed))        $headers[] = 'page_count';
        if (!array_key_exists('detail', $fixed))            $headers[] = 'detail';

        $sampleData = array_map(fn($h) => $this->sampleValue($h), $headers);

        $csv = "\xEF\xBB\xBF"; // UTF-8 BOM（Excel対応）
        foreach ([$headers, $sampleData] as $row) {
            $csv .= implode(',', array_map(
                fn($v) => '"' . str_replace('"', '""', (string) $v) . '"',
                $row,
            )) . "\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="project_jobs_sample.csv"',
        ]);
    }

    /**
     * CSVプレビュー（バリデーション結果を返す）
     * POST multipart: csv_file, template_id
     */
    public function preview(Request $request)
    {
        $request->validate([
            'csv_file'    => 'required|file|mimes:csv,txt|max:2048',
            'template_id' => 'nullable|integer|exists:project_job_templates,id',
        ]);

        $templateId = $request->input('template_id');
        $template   = $templateId ? ProjectJobTemplate::find($templateId) : null;
        $fixed      = $template?->fixed_fields ?? [];

        $companyId = $this->resolveCompanyId($request->user());

        try {
            $rows = $this->parseCsv($request->file('csv_file'));

            // CSVが空の場合
            if (empty($rows)) {
                return redirect()
                    ->route('coordinator.project_jobs.bulk_create.index')
                    ->withErrors(['csv_file' => 'CSVファイルが空か、有効なデータ行がありません']);
            }

            $results = [];

            foreach ($rows as $rowNum => $row) {
                [$data, $errors, $warnings] = $this->validateRow($row, $rowNum + 1, $fixed, $companyId);
                $results[] = [
                    'rowNum'   => $rowNum + 1,
                    'data'     => $data,
                    'errors'   => $errors,
                    'warnings' => $warnings,
                    'valid'    => empty($errors),
                ];
            }

            $validCount = count(array_filter($results, fn($r) => $r['valid']));
            $errorCount = count($results) - $validCount;

            return Inertia::render('Coordinator/ProjectJobs/BulkCreate', array_merge(
                $this->sharedProps($request),
                [
                    'previewData' => [
                        'rows'        => $results,
                        'validCount'  => $validCount,
                        'errorCount'  => $errorCount,
                        'templateId'  => $templateId ? (int) $templateId : null,
                    ],
                ],
            ));
        } catch (\Exception $e) {
            \Log::error('CSV アップロード エラー', [
                'error'    => $e->getMessage(),
                'file'     => $e->getFile(),
                'line'     => $e->getLine(),
                'user_id'  => $request->user()->id,
                'filename' => $request->file('csv_file')?->getClientOriginalName(),
            ]);

            return redirect()
                ->route('coordinator.project_jobs.bulk_create.index')
                ->withErrors([
                    'csv_file' => 'CSVファイルの処理中にエラーが発生しました: ' . 
                                  (str_contains($e->getMessage(), 'encoding') || str_contains($e->getMessage(), 'BOM') 
                                      ? 'ファイルの文字コードをUTF-8にして保存し直してください' 
                                      : $e->getMessage())
                ]);
        }
    }

    /**
     * 一括登録実行
     * POST JSON: { rows: [...], template_id: 1 }
     */
    public function store(Request $request)
    {
        $request->validate([
            'rows'        => 'required|array|min:1',
            'template_id' => 'nullable|integer|exists:project_job_templates,id',
        ]);

        $templateId = $request->input('template_id');
        $template   = $templateId ? ProjectJobTemplate::find($templateId) : null;
        $fixed      = $template?->fixed_fields ?? [];
        $teamMembersFixed = $template?->team_members ?? [];

        $rows      = $request->input('rows');
        $companyId = $this->resolveCompanyId($request->user());
        $created   = [];

        DB::transaction(function () use ($rows, $fixed, $teamMembersFixed, $companyId, &$created) {
            foreach ($rows as $row) {
                if (!($row['valid'] ?? false)) {
                    continue;
                }

                $data = $row['data'];

                $jobData = [
                    'title'      => $data['title'],
                    'jobcode'    => $data['jobcode'] ?: null,
                    'client_id'  => (int) $data['client_id'],
                    'user_id'    => (int) ($data['user_id'] ?? $fixed['user_id'] ?? null),
                    'company_id' => $companyId,
                    'size_id'    => $data['size_id'] ? (int) $data['size_id'] : null,
                    'page_count' => $data['page_count'] ? (int) $data['page_count'] : null,
                    'detail'     => $data['detail'] ?? ($fixed['detail'] ?? null),
                ];
                Arr::pull($jobData, 'schedule'); // さくら本番: project_jobs.schedule カラムなし

                $job = ProjectJob::create($jobData);

                // サブCo sync
                $subIds = $fixed['sub_coordinator_ids'] ?? [];
                $syncIds = array_values(array_filter($subIds, fn($id) => $id != $job->user_id));
                if (!empty($syncIds)) {
                    $job->coordinators()->sync($syncIds);
                }

                // チームメンバー（テンプレート固定）
                foreach ($teamMembersFixed as $member) {
                    $userId = $member['user_id'] ?? null;
                    if ($userId) {
                        ProjectTeamMember::create([
                            'project_job_id' => $job->id,
                            'user_id'        => (int) $userId,
                        ]);
                    }
                }

                $created[] = $job->id;
            }
        });

        return redirect()
            ->route('coordinator.project_jobs.index')
            ->with('success', count($created) . ' 件の案件を一括登録しました。');
    }

    // ─────────────────────────────────────────────────────────────────────

    /** CSVファイルをパースして行配列を返す（ヘッダー行はキーとして使用） */
    private function parseCsv(\Illuminate\Http\UploadedFile $file): array
    {
        try {
            $content = $this->normalizeCsvContent($file->get() ?: '');

            if (empty($content)) {
                throw new \Exception('CSVファイルが空です');
            }

            $lines = preg_split('/\n/', trim($content));

            if (empty($lines)) {
                throw new \Exception('CSVファイルにデータ行がありません');
            }

            // ヘッダー行の解析
            $headerLine = array_shift($lines);
            if (empty($headerLine)) {
                throw new \Exception('CSVのヘッダー行が見つかりません');
            }

            $headers = str_getcsv($headerLine);
            if ($headers === false) {
                throw new \Exception('CSVのヘッダー行の形式が正しくありません');
            }

            $headers = array_map('trim', $headers);
            
            // 必須ヘッダーの確認（最低限必要なもの）
            $requiredHeaders = ['title']; // titleは必須
            $missingHeaders = array_diff($requiredHeaders, $headers);
            if (!empty($missingHeaders)) {
                throw new \Exception('必須のヘッダーが不足しています: ' . implode(', ', $missingHeaders));
            }

            $rows = [];
            $lineNumber = 2; // ヘッダー行の次から

            foreach ($lines as $line) {
                if (trim($line) === '') {
                    $lineNumber++;
                    continue;
                }

                $values = str_getcsv($line);
                if ($values === false) {
                    throw new \Exception("行 {$lineNumber}: CSV形式が正しくありません");
                }

                $row = [];
                foreach ($headers as $i => $header) {
                    $row[$header] = trim($values[$i] ?? '');
                }
                $rows[] = $row;
                $lineNumber++;
            }

            if (empty($rows)) {
                throw new \Exception('CSVファイルにデータ行がありません（ヘッダー行のみです）');
            }

            return $rows;

        } catch (\Exception $e) {
            // 元の例外を再投げ（詳細な情報を保持）
            throw new \Exception('CSVパースエラー: ' . $e->getMessage());
        }
    }

    /**
     * 1行のバリデーション。
     * @return array{0: array, 1: string[], 2: string[]} [$data, $errors, $warnings]
     */
    private function validateRow(array $row, int $rowNum, array $fixed, ?int $companyId = null): array
    {
        $errors   = [];
        $warnings = [];
        $data     = $row;

        // title
        if (empty(trim($row['title'] ?? ''))) {
            $errors[] = "title は必須です";
        }

        // client 解決（テンプレート固定を優先）
        $clientId = null;
        $clientName = null;

        if (!empty($fixed['client_id'])) {
            $clientId = $fixed['client_id'];
            // 固定クライアントの名前を取得
            $client = Client::where('id', $clientId)->first(['id', 'name']);
            if ($client) {
                $clientName = $client->name;
            }
        } elseif (!empty($row['client_id'])) {
            $clientId = (int) $row['client_id'];
            $query = Client::where('id', $clientId);
            if ($companyId) {
                $query->forCompany($companyId);
            }
            $client = $query->first(['id', 'name']);
            if (!$client) {
                $errors[] = "client_id={$clientId} が見つかりません";
                $clientId = null;
            } else {
                $clientName = $client->name;
            }
        } elseif (!empty($row['client_name'])) {
            $inputName = trim($row['client_name']);
            $matchedClient = $this->findClientByFlexibleName($inputName, $companyId);
            
            if (!$matchedClient) {
                $errors[] = "クライアント「{$inputName}」が見つかりません。登録されているクライアント名を確認してください";
            } else {
                $clientId = $matchedClient->id;
                $clientName = $matchedClient->name;
                
                // 入力名と正式名が異なる場合は警告
                if ($inputName !== $matchedClient->name) {
                    $warnings[] = "クライアント名「{$inputName}」→「{$matchedClient->name}」に自動補正";
                }
            }
        } else {
            $errors[] = "client_id または client_name が必要です（テンプレートでクライアントが固定されていません）";
        }
        
        $data['client_id'] = $clientId;
        $data['client_name'] = $clientName; // プレビュー表示用にクライアント名を保存

        // user_id（テンプレート固定なければ CSV から解決）
        if (empty($fixed['user_id'])) {
            if (!empty($row['leader_name'])) {
                $leaderQuery = User::where('name', $row['leader_name']);
                if ($companyId) {
                    $leaderQuery->where('company_id', $companyId);
                }
                $leader = $leaderQuery->first();
                if (!$leader) {
                    $errors[] = "リーダー「{$row['leader_name']}」が見つかりません";
                } else {
                    $data['user_id'] = $leader->id;
                }
            } else {
                $errors[] = "leader_name は必須です（テンプレートでリーダーが固定されていません）";
            }
        } else {
            $data['user_id'] = $fixed['user_id'];
        }

        // size_id（任意）
        if (empty($fixed['size_id']) && !empty($row['size_name'])) {
            $size = Size::where('name', $row['size_name'])->first();
            if (!$size) {
                $warnings[] = "サイズ「{$row['size_name']}」が見つかりません（空で登録）";
                $data['size_id'] = null;
            } else {
                $data['size_id'] = $size->id;
            }
        } elseif (!empty($fixed['size_id'])) {
            $data['size_id'] = $fixed['size_id'];
        } else {
            $data['size_id'] = null;
        }

        // page_count（任意）
        if (isset($row['page_count']) && $row['page_count'] !== '') {
            $pc = (int) $row['page_count'];
            if ($pc < 1 || $pc > 99999) {
                $errors[] = "page_count は 1〜99999 の整数で入力してください";
            } else {
                $data['page_count'] = $pc;
            }
        } else {
            $data['page_count'] = $fixed['page_count'] ?? null;
        }

        // jobcode（任意）
        if (!empty($row['jobcode']) && !preg_match('/^[0-9\-]+$/', $row['jobcode'])) {
            $errors[] = "jobcode は数字とハイフンのみ使用できます";
        }

        // detail（テンプレート優先、CSV があれば上書き）
        if (isset($row['detail']) && $row['detail'] !== '') {
            $data['detail'] = $row['detail'];
        } elseif (!empty($fixed['detail'])) {
            $data['detail'] = $fixed['detail'];
        } else {
            $data['detail'] = null;
        }

        return [$data, $errors, $warnings];
    }

    /**
     * 柔軟なクライアント名マッチング
     * 株式会社の有無や位置を許容し、最も適切なクライアントを返す
     */
    private function findClientByFlexibleName(string $inputName, ?int $companyId = null): ?Client
    {
        $baseQuery = fn() => $companyId ? Client::forCompany($companyId) : Client::query();

        // 1. 完全一致チェック
        $client = $baseQuery()->where('name', $inputName)->first(['id', 'name']);
        if ($client) {
            return $client;
        }

        // 2. 正規化した名前での完全一致チェック
        $normalizedInput = $this->normalizeClientName($inputName);

        $clients = $baseQuery()->get(['id', 'name']);
        foreach ($clients as $client) {
            $normalizedClient = $this->normalizeClientName($client->name);
            
            // 正規化後の完全一致
            if ($normalizedInput === $normalizedClient) {
                return $client;
            }
        }

        // 3. 部分一致チェック（正規化後）
        foreach ($clients as $client) {
            $normalizedClient = $this->normalizeClientName($client->name);
            
            // どちらかがもう片方を含む場合（短い方が80%以上一致）
            $similarity = 0;
            similar_text($normalizedInput, $normalizedClient, $similarity);
            
            if ($similarity >= 80) {
                // より詳細なチェック：株式会社位置の違いのみかチェック
                if ($this->isCompanySuffixOnlyDifference($normalizedInput, $normalizedClient)) {
                    return $client;
                }
            }
        }

        // 4. レーベンシュタイン距離による類似チェック（タイポ対応）
        $bestMatch = null;
        $minDistance = PHP_INT_MAX;
        
        foreach ($clients as $client) {
            $normalizedClient = $this->normalizeClientName($client->name);
            $distance = levenshtein($normalizedInput, $normalizedClient);
            
            // 距離が短く、かつ元の長さの20%以内の誤差なら候補とする
            $maxAllowedDistance = (int) (strlen($normalizedInput) * 0.2);
            if ($distance <= $maxAllowedDistance && $distance < $minDistance) {
                $minDistance = $distance;
                $bestMatch = $client;
            }
        }

        return $bestMatch;
    }

    /**
     * クライアント名の正規化
     * 株式会社の除去、空白除去、英数字半角化等
     */
    private function normalizeClientName(string $name): string
    {
        $normalized = trim($name);
        
        // 全角英数字を半角に変換
        $normalized = mb_convert_kana($normalized, 'a', 'UTF-8');
        
        // 前後の空白、改行を除去
        $normalized = preg_replace('/\s+/', '', $normalized);
        
        // 株式会社、有限会社等の会社形態を除去（前後両方）
        $companyTypes = [
            '株式会社', '有限会社', '合同会社', '合資会社', '合名会社',
            '一般社団法人', '一般財団法人', '公益社団法人', '公益財団法人',
            'カブシキガイシャ', 'ユウゲンガイシャ', 'ゴウドウガイシャ',
            '㈱', '㈲', '(株)', '(有)', '（株）', '（有）'
        ];
        
        foreach ($companyTypes as $type) {
            // 前方の会社形態を除去
            if (mb_strpos($normalized, $type) === 0) {
                $normalized = mb_substr($normalized, mb_strlen($type));
            }
            
            // 後方の会社形態を除去
            $typeLen = mb_strlen($type);
            if (mb_substr($normalized, -$typeLen) === $type) {
                $normalized = mb_substr($normalized, 0, -$typeLen);
            }
        }
        
        // 再度空白除去
        $normalized = preg_replace('/\s+/', '', $normalized);
        
        return $normalized;
    }

    /**
     * 株式会社の位置の違いのみかチェック
     */
    private function isCompanySuffixOnlyDifference(string $name1, string $name2): bool
    {
        // 両方から会社形態を完全に除去した場合の比較
        $core1 = $this->removeAllCompanyTypes($name1);
        $core2 = $this->removeAllCompanyTypes($name2);
        
        return $core1 === $core2 && !empty($core1);
    }

    /**
     * 全ての会社形態を除去
     */
    private function removeAllCompanyTypes(string $name): string
    {
        $companyTypes = [
            '株式会社', '有限会社', '合同会社', '合資会社', '合名会社',
            '一般社団法人', '一般財団法人', '公益社団法人', '公益財団法人',
            'カブシキガイシャ', 'ユウゲンガイシャ', 'ゴウドウガイシャ',
            '㈱', '㈲', '(株)', '(有)', '（株）', '（有）'
        ];
        
        $result = $name;
        foreach ($companyTypes as $type) {
            $result = str_replace($type, '', $result);
        }
        
        return trim($result);
    }

    private function sampleValue(string $col): string
    {
        return match ($col) {
            'jobcode'      => '2026-001',
            'title'        => '〇〇書籍 本文',
            'client_name'  => 'サンプル会社',  // 注: 株式会社の有無は自動補正されます
            'leader_name'  => '田中Co',
            'size_name'    => 'B5',
            'page_count'   => '128',
            'detail'       => '通常校正あり',
            default        => '',
        };
    }

    /** BulkCreate.vue に渡す共通 props */
    private function sharedProps(Request $request): array
    {
        $user      = $request->user();
        $userId    = $user->id;
        $companyId = $this->resolveCompanyId($user);

        $templates = ProjectJobTemplate::where('is_shared', true)
            ->orWhere('created_by', $userId)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn($t) => [
                'id'           => $t->id,
                'name'         => $t->name,
                'description'  => $t->description,
                'fixed_fields' => $t->fixed_fields ?? [],
                'team_members' => $t->team_members ?? [],
                'is_shared'    => $t->is_shared,
                'created_by'   => $t->created_by,
            ]);

        return [
            'templates'             => $templates,
            'coordinatorCandidates' => User::where(function ($q) {
                    $q->where('user_role', 'coordinator')
                      ->orWhere('user_role', 'clerk')
                      ->orWhereHas('assignment', fn($q2) => $q2->where('code', 'shinko'));
                })
                ->when($companyId, fn($q) => $q->where('company_id', $companyId))
                ->ordered()->get(['id', 'name']),
            'sizes'                 => Size::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group']),
            'users'                 => User::when($companyId, fn($q) => $q->where('company_id', $companyId))
                ->ordered()->get(['id', 'name']),
        ];
    }

    private function resolveCompanyId(\App\Models\User $user): ?int
    {
        if ($user->isSuperAdmin()) {
            $id = (int) (session('superadmin_context.company_id') ?? $user->company_id ?? 0);
            return $id ?: null;
        }
        return $user->company_id ?? null;
    }
}
