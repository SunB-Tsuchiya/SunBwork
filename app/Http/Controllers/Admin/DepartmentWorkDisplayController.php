<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Services\DepartmentWorkDisplaySettings;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DepartmentWorkDisplayController extends Controller
{
    public function index(Request $request, DepartmentWorkDisplaySettings $settings)
    {
        $context=$request->session()->get('superadmin_context.company_id');
        $company=$settings->company($request->user(),$context===null ? null : (int)$context);
        return Inertia::render('Admin/DepartmentWorkDisplay/Index',
            ($company ? $settings->snapshot($company) : ['users'=>[],'version'=>''])+['noCompanySelected'=>$company===null]);
    }
    public function update(Request $request, DepartmentWorkDisplaySettings $settings)
    {
        $context=$request->session()->get('superadmin_context.company_id');
        $company=$settings->company($request->user(),$context===null ? null : (int)$context);
        abort_unless($company,422,'会社を選択してください。');
        $data=$request->validate(['version'=>['required','string','size:64'],'items'=>['required','array','min:1'],
            'items.*'=>['required','array:user_id,show_schedule,show_records'],
            'items.*.user_id'=>['required','integer','distinct'],
            'items.*.show_schedule'=>['required','boolean'],'items.*.show_records'=>['required','boolean']]);
        return response()->json($settings->save($request->user(),$company,$data['items'],$data['version']));
    }
}
