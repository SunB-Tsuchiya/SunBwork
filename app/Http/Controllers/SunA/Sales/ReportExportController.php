<?php

namespace App\Http\Controllers\SunA\Sales;

use App\Http\Controllers\Controller;
use App\Models\SunA\Sales\AuditLog;
use App\Services\SunA\Sales\Analysis\DepartmentFilter;
use App\Services\SunA\Sales\Export\ReportExportException;
use App\Services\SunA\Sales\Export\ReportExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * 売上レポートのExcel出力（サンエー印刷。SUNASALES_PLAN1.md §19）。売上レポートを閲覧できる人は全員、画面と同じ条件で出力できる。
 * ルートは suna_sales_report:view で保護。ファイルは保存せず、その場で作ってダウンロードさせる。
 * 出力のたびに監査ログに「画面名と行数」だけを残す（得意先名・金額・条件の内容は入れない）。
 */
class ReportExportController extends Controller
{
    public function __construct(private ReportExportService $service, private DepartmentFilter $departments)
    {
    }

    public function annual(Request $request)
    {
        $data = $request->validate(['year' => ['required', 'integer', 'min:1990', 'max:2100'], 'dept' => ['nullable', 'string', 'max:50']]);

        return $this->respond('annual', fn () => $this->service->annual((int) $data['year'], $this->dept($data)));
    }

    public function sameMonth(Request $request)
    {
        $data = $request->validate(['month' => ['required', 'integer', 'min:1', 'max:12'], 'dept' => ['nullable', 'string', 'max:50']]);

        return $this->respond('same_month', fn () => $this->service->sameMonth((int) $data['month'], $this->dept($data)));
    }

    public function department(Request $request)
    {
        $data = $request->validate(['basis' => ['nullable', Rule::in(['same_month', 'year'])]]);

        return $this->respond('department', fn () => $this->service->department($data['basis'] ?? 'same_month'));
    }

    public function compare(Request $request)
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['year', 'month'])],
            'year_a' => ['required', 'integer', 'min:1990', 'max:2100'],
            'year_b' => ['required', 'integer', 'min:1990', 'max:2100'],
            'month_a' => ['nullable', 'integer', 'min:1', 'max:12', Rule::requiredIf(fn () => $request->input('mode') === 'month')],
            'month_b' => ['nullable', 'integer', 'min:1', 'max:12', Rule::requiredIf(fn () => $request->input('mode') === 'month')],
            'dept' => ['nullable', 'string', 'max:50'],
        ]);
        $a = ['year' => (int) $data['year_a']] + ($data['mode'] === 'month' ? ['month' => (int) $data['month_a']] : []);
        $b = ['year' => (int) $data['year_b']] + ($data['mode'] === 'month' ? ['month' => (int) $data['month_b']] : []);

        return $this->respond('compare', fn () => $this->service->compare($data['mode'], $a, $b, $this->dept($data)));
    }

    public function client(Request $request)
    {
        $data = $request->validate(['client_key' => ['required', 'string', 'max:255']]);

        return $this->respond('client', fn () => $this->service->client($data['client_key']));
    }

    /** 部門の指定を確かめる（不明なキーは422） */
    private function dept(array $data): string
    {
        $dept = $data['dept'] ?? DepartmentFilter::ALL;
        abort_unless($this->departments->isValid($dept), 422, '部門の指定が正しくありません。');

        return $dept;
    }

    /** @param  callable(): array{filename:string, workbook:\App\Services\SunA\Sales\Export\ReportWorkbook}  $build */
    private function respond(string $screen, callable $build)
    {
        try {
            $result = $build();
        } catch (ReportExportException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        $workbook = $result['workbook'];

        // 監査ログ: 画面名と行数だけ（得意先名・金額・条件は入れない）
        AuditLog::create([
            'user_id' => (int) Auth::id(),
            'action' => 'export',
            'target_type' => $screen,
            'context' => ['rows' => $workbook->tableRows()],
        ]);

        return response()->streamDownload(fn () => $workbook->write(), $result['filename'], [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store',
        ]);
    }
}
