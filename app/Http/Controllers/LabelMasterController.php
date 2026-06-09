<?php

namespace App\Http\Controllers;

use App\Models\LabelSchoolMaster;
use App\Models\LabelTestName;
use App\Models\LabelSubject;
use App\Models\LabelItemType;
use Illuminate\Http\Request;

class LabelMasterController extends Controller
{
    // ================================================================
    // 教室マスタ
    // ================================================================

    public function schoolsIndex()
    {
        return response()->json(
            LabelSchoolMaster::orderByRaw("FIELD(area,'北海道','関東','東海','関西','中国','四国','九州・沖縄') , route, stop_order, code")
                ->get()
        );
    }

    public function schoolsStore(Request $request)
    {
        $data = $request->validate([
            'code'         => 'required|string|max:20|unique:label_school_masters,code',
            'display_name' => 'required|string|max:150',
            'area'         => 'required|string|max:50',
            'route'        => 'nullable|string|max:10',
            'stop_order'   => 'nullable|integer|min:1|max:99',
            'is_active'    => 'boolean',
            'notes'        => 'nullable|string',
        ]);
        $school = LabelSchoolMaster::create($data);
        return response()->json($school, 201);
    }

    public function schoolsUpdate(Request $request, LabelSchoolMaster $school)
    {
        $data = $request->validate([
            'code'         => 'required|string|max:20|unique:label_school_masters,code,' . $school->id,
            'display_name' => 'required|string|max:150',
            'area'         => 'required|string|max:50',
            'route'        => 'nullable|string|max:10',
            'stop_order'   => 'nullable|integer|min:1|max:99',
            'is_active'    => 'boolean',
            'notes'        => 'nullable|string',
        ]);
        $school->update($data);
        return response()->json($school);
    }

    public function schoolsDestroy(LabelSchoolMaster $school)
    {
        $school->delete();
        return response()->json(null, 204);
    }

    // ================================================================
    // テスト名マスタ
    // ================================================================

    public function testNamesIndex()
    {
        return response()->json(LabelTestName::orderBy('sort_order')->orderBy('id')->get());
    }

    public function testNamesStore(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:200',
            'sort_order' => 'integer',
            'is_active'  => 'boolean',
        ]);
        return response()->json(LabelTestName::create($data), 201);
    }

    public function testNamesUpdate(Request $request, LabelTestName $testName)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:200',
            'sort_order' => 'integer',
            'is_active'  => 'boolean',
        ]);
        $testName->update($data);
        return response()->json($testName);
    }

    public function testNamesDestroy(LabelTestName $testName)
    {
        $testName->delete();
        return response()->json(null, 204);
    }

    // ================================================================
    // 科目マスタ
    // ================================================================

    public function subjectsIndex()
    {
        return response()->json(LabelSubject::orderBy('sort_order')->orderBy('id')->get());
    }

    public function subjectsStore(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'sort_order' => 'integer',
            'is_active'  => 'boolean',
        ]);
        return response()->json(LabelSubject::create($data), 201);
    }

    public function subjectsUpdate(Request $request, LabelSubject $subject)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'sort_order' => 'integer',
            'is_active'  => 'boolean',
        ]);
        $subject->update($data);
        return response()->json($subject);
    }

    public function subjectsDestroy(LabelSubject $subject)
    {
        $subject->delete();
        return response()->json(null, 204);
    }

    // ================================================================
    // 内容マスタ
    // ================================================================

    public function itemTypesIndex()
    {
        return response()->json(LabelItemType::orderBy('sort_order')->orderBy('id')->get());
    }

    public function itemTypesStore(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:200',
            'sort_order' => 'integer',
            'is_active'  => 'boolean',
        ]);
        return response()->json(LabelItemType::create($data), 201);
    }

    public function itemTypesUpdate(Request $request, LabelItemType $itemType)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:200',
            'sort_order' => 'integer',
            'is_active'  => 'boolean',
        ]);
        $itemType->update($data);
        return response()->json($itemType);
    }

    public function itemTypesDestroy(LabelItemType $itemType)
    {
        $itemType->delete();
        return response()->json(null, 204);
    }
}
