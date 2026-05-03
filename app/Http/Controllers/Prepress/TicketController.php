<?php

namespace App\Http\Controllers\Prepress;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Department;
use App\Models\PrepressTicket;
use App\Models\ProjectJob;
use App\Services\PrepressImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function __construct(private PrepressImageService $imageService) {}

    public function index(Request $request)
    {
        $this->authorizePrepress($request->user());

        $q      = $request->input('q', '');
        $period = $request->input('period', '');
        $hideCompleted = $request->boolean('hide_completed', true);

        $query = PrepressTicket::with('user')
            ->orderByDesc('created_at');

        if ($q) {
            $kw = '%' . $q . '%';
            $query->where(function ($sq) use ($kw) {
                $sq->where('title', 'like', $kw)
                   ->orWhere('project_name', 'like', $kw)
                   ->orWhere('client_name', 'like', $kw)
                   ->orWhere('jobcode', 'like', $kw)
                   ->orWhere('memo', 'like', $kw);
            });
        }

        if ($period && $period !== 'all') {
            [$y, $m] = explode('-', $period . '-01');
            $query->whereYear('created_at', (int)$y)
                  ->whereMonth('created_at', (int)$m);
        }

        if ($hideCompleted) {
            $query->where('status', '!=', PrepressTicket::STATUS_COMPLETED);
        }

        $tickets = $query->get();

        // 直近12ヶ月のオプション
        $monthOptions = [];
        for ($i = 0; $i < 12; $i++) {
            $d = now()->subMonths($i);
            $monthOptions[] = [
                'value' => $d->format('Y-m'),
                'label' => $d->format('Y年n月'),
            ];
        }

        return inertia('Prepress/Tickets/Index', [
            'tickets'      => $tickets,
            'statuses'     => PrepressTicket::STATUS_LABELS,
            'monthOptions' => $monthOptions,
            'q'            => $q,
            'period'       => $period,
            'hide_completed' => $hideCompleted,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizePrepress($request->user());

        // 製版部署に紐づくクライアント一覧
        $dept    = Department::where('name', '製版')->first();
        $clients = Client::query()
            ->when($dept, fn($q) => $q->whereHas('departments', fn($q) => $q->where('departments.id', $dept->id)))
            ->orderBy('name')
            ->get(['id', 'name', 'is_dormant']);

        // 案件からの事前入力
        $prefill = [];
        if ($request->filled('project_job_id')) {
            $job = ProjectJob::with('client')->find($request->integer('project_job_id'));
            if ($job) {
                $prefill = [
                    'project_job_id'         => $job->id,
                    'client_id'              => $job->client_id,
                    'client_name'            => $job->client?->name,
                    'jobcode'                => $job->jobcode,
                    'title'                  => $job->title,
                    'job_image_path'         => $job->image_path,
                    'job_image_url'          => $job->image_url,
                    'job_original_filename'  => $job->original_filename,
                ];
            }
        } elseif ($request->filled('tmp_ocr_image_path')) {
            // OCR読み込みからの事前入力
            $prefill = [
                'client_id'          => $request->input('client_id')          ?: null,
                'client_name'        => $request->input('client_name',        ''),
                'jobcode'            => $request->input('jobcode',            ''),
                'title'              => $request->input('title',              ''),
                'tmp_ocr_image_path' => $request->input('tmp_ocr_image_path', ''),
                'ocr_image_url'      => $request->input('ocr_image_url',      ''),
                'original_filename'  => $request->input('original_filename',  ''),
            ];
        }

        return inertia('Prepress/Tickets/Create', [
            'statuses' => PrepressTicket::STATUS_LABELS,
            'clients'  => $clients,
            'prefill'  => $prefill,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizePrepress($request->user());

        $validated = $request->validate([
            'title'              => ['required', 'string', 'max:255'],
            'jobcode'            => ['nullable', 'string', 'max:100'],
            'client_id'          => ['nullable', 'integer', 'exists:clients,id'],
            'client_name'        => ['nullable', 'string', 'max:255'],
            'memo'               => ['nullable', 'string', 'max:5000'],
            'status'             => ['required', Rule::in(array_keys(PrepressTicket::STATUS_LABELS))],
            'image'              => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,heic,heif,pdf', 'max:20480'],
            'project_job_id'     => ['nullable', 'integer', 'exists:project_jobs,id'],
            'use_job_image'      => ['nullable', 'boolean'],
            'tmp_ocr_image_path' => ['nullable', 'string', 'max:500'],
        ]);

        // client_id が指定されていれば DB から名前を取得して上書き
        $clientName = $validated['client_name'] ?? null;
        if (!empty($validated['client_id'])) {
            $client = Client::find($validated['client_id']);
            if ($client) {
                $clientName = $client->name;
            }
        }

        // 案件の取得（画像共有のため）
        $job = !empty($validated['project_job_id'])
            ? ProjectJob::find($validated['project_job_id'])
            : null;

        $imagePath        = null;
        $originalFilename = null;

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            // 新規アップロード
            $imageMeta        = $this->imageService->convertAndStore($request->file('image'));
            $imagePath        = $imageMeta['path'] ?? null;
            $originalFilename = $imageMeta['original_filename'] ?? null;

            // 案件に画像がなければ逆向き同期（同一パスを共有）
            if ($job && !$job->image_path && $imagePath) {
                $job->update([
                    'image_path'        => $imagePath,
                    'original_filename' => $originalFilename,
                ]);
            }
        } elseif (!empty($validated['tmp_ocr_image_path'])) {
            // OCR解析時にすでに変換済みの画像を使い回す（二重変換を避ける）
            $tmpPath = $validated['tmp_ocr_image_path'];
            // セキュリティ: prepress/jobticker/ 以外のパスを拒否
            if (str_starts_with($tmpPath, 'prepress/jobticker/')
                && \Illuminate\Support\Facades\Storage::disk('public')->exists($tmpPath)
            ) {
                $imagePath        = $tmpPath;
                $originalFilename = basename($tmpPath);
            }
        } elseif ($request->boolean('use_job_image') && $job && $job->image_path) {
            // 案件の画像を伝票に共有（ファイルコピーなし・同一パス参照）
            $imagePath        = $job->image_path;
            $originalFilename = $job->original_filename;
        }

        $ticket = PrepressTicket::create([
            'user_id'           => $request->user()->id,
            'project_job_id'    => $job?->id,
            'client_id'         => !empty($validated['client_id']) ? $validated['client_id'] : null,
            'title'             => $validated['title'],
            'jobcode'           => $validated['jobcode'] ?? null,
            'project_name'      => null,
            'client_name'       => $clientName,
            'memo'              => $validated['memo'] ?? null,
            'status'            => $validated['status'],
            'image_path'        => $imagePath,
            'original_filename' => $originalFilename,
        ]);

        return redirect()->route('prepress.tickets.index')
            ->with('success', '伝票「' . $ticket->title . '」を登録しました。');
    }

    public function updateStatus(Request $request, PrepressTicket $ticket)
    {
        $this->authorizePrepress($request->user());

        $request->validate([
            'status' => ['required', Rule::in(array_keys(PrepressTicket::STATUS_LABELS))],
        ]);

        $ticket->update(['status' => $request->status]);

        return response()->json(['ok' => true, 'status' => $ticket->status]);
    }

    public function updateImage(Request $request, PrepressTicket $ticket)
    {
        $this->authorizePrepress($request->user());

        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,heic,heif,pdf', 'max:20480'],
        ]);

        // 旧画像が案件と共有されていない場合のみ削除
        if ($ticket->image_path) {
            $sharedWithJob = $ticket->project_job_id
                && optional(ProjectJob::find($ticket->project_job_id))->image_path === $ticket->image_path;
            if (!$sharedWithJob) {
                $this->imageService->delete($ticket->image_path);
            }
        }

        $imageMeta        = $this->imageService->convertAndStore($request->file('image'));
        $imagePath        = $imageMeta['path'] ?? null;
        $originalFilename = $imageMeta['original_filename'] ?? null;

        $ticket->update([
            'image_path'        => $imagePath,
            'original_filename' => $originalFilename,
        ]);

        // 案件に画像がなければ逆向き同期
        if ($ticket->project_job_id && $imagePath) {
            $job = ProjectJob::find($ticket->project_job_id);
            if ($job && !$job->image_path) {
                $job->update([
                    'image_path'        => $imagePath,
                    'original_filename' => $originalFilename,
                ]);
            }
        }

        return response()->json([
            'ok'                => true,
            'image_url'         => $imagePath ? Storage::disk('public')->url($imagePath) : null,
            'original_filename' => $originalFilename,
        ]);
    }

    public function destroy(Request $request, PrepressTicket $ticket)
    {
        $this->authorizePrepress($request->user());

        // 案件と共有している画像は削除しない
        $sharedWithJob = $ticket->project_job_id
            && optional(ProjectJob::find($ticket->project_job_id))->image_path === $ticket->image_path;

        if ($ticket->image_path && !$sharedWithJob) {
            $this->imageService->delete($ticket->image_path);
        }
        $ticket->delete();

        return redirect()->route('prepress.tickets.index')
            ->with('success', '伝票を削除しました。');
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
