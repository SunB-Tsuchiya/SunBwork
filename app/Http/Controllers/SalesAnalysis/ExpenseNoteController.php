<?php

namespace App\Http\Controllers\SalesAnalysis;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SalesAnalysis\Concerns\ResolvesSalesAnalysisCompany;
use App\Services\SalesAnalysis\SalesDepartments;
use App\Services\SalesAnalysis\SalesExpenseNoteService;
use App\Services\SalesAnalysis\SalesExpenses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * 経費メモ（Phase24-5）のAPI。部署シートの科目・支払先の行、支払先カルテから使う。
 * 経費比較を見られる人は誰でも追加でき、編集・削除は書いた本人とSuperAdminだけ。
 */
class ExpenseNoteController extends Controller
{
    use ResolvesSalesAnalysisCompany;

    private function noteService(): SalesExpenseNoteService
    {
        $companyId = $this->requireSalesAnalysisCompanyId();
        abort_unless(SalesExpenses::supportsExpensesFor($companyId), 403);

        return (new SalesExpenseNoteService())->forCompany($companyId);
    }

    private function departmentRule(): array
    {
        $companyId = $this->requireSalesAnalysisCompanyId();

        return ['required', 'string', Rule::in([...SalesDepartments::enabledKeysFor($companyId), SalesExpenses::SHARED_DEPARTMENT_KEY])];
    }

    public function index(Request $request)
    {
        $service = $this->noteService();
        $data = $request->validate([
            'department_key' => $this->departmentRule(),
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        return response()->json($service->forDepartment($data['department_key'], (int) $data['fiscal_year'], Auth::user()));
    }

    public function vendor(Request $request)
    {
        $service = $this->noteService();
        $data = $request->validate(['vendor' => ['required', 'string', 'max:255']]);

        return response()->json(['notes' => $service->forVendor($data['vendor'], Auth::user())]);
    }

    public function store(Request $request)
    {
        $service = $this->noteService();
        $data = $request->validate([
            'department_key' => $this->departmentRule(),
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'category' => ['nullable', 'string', 'max:255'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'calendar_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'body' => ['required', 'string', 'max:1000'],
        ]);
        // 支払先へのメモは、どの科目の支払先かを特定するため科目が必須
        if (! empty($data['vendor_name']) && empty($data['category'])) {
            return response()->json(['message' => '支払先へのメモには科目が必要です。'], 422);
        }
        if (trim($data['body']) === '') {
            return response()->json(['message' => 'メモの内容を入力してください。'], 422);
        }

        $note = $service->create($data, Auth::user());

        return response()->json($service->presentOne($note, Auth::user()), 201);
    }

    public function update(Request $request, int $note)
    {
        $service = $this->noteService();
        $model = $service->find($note);
        abort_if($model === null, 404);
        abort_unless(SalesExpenseNoteService::canModify($model, Auth::user()), 403, '編集できるのは、書いた本人とSuperAdminだけです。');

        $data = $request->validate([
            'calendar_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'body' => ['required', 'string', 'max:1000'],
        ]);
        if (trim($data['body']) === '') {
            return response()->json(['message' => 'メモの内容を入力してください。'], 422);
        }

        $service->update($model, $data['body'], $data['calendar_month'] ?? null, Auth::user());

        return response()->json($service->presentOne($model->fresh(), Auth::user()));
    }

    public function destroy(int $note)
    {
        $service = $this->noteService();
        $model = $service->find($note);
        abort_if($model === null, 404);
        abort_unless(SalesExpenseNoteService::canModify($model, Auth::user()), 403, '削除できるのは、書いた本人とSuperAdminだけです。');

        $model->delete();

        return response()->json(['deleted' => true]);
    }
}
