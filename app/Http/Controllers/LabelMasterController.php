<?php

namespace App\Http\Controllers;

use App\Models\LabelSchoolMaster;
use App\Models\LabelTestName;
use App\Models\LabelSubject;
use App\Models\LabelItemType;
use App\Models\LabelRoute;
use App\Models\LabelRouteStop;
use Illuminate\Http\Request;

class LabelMasterController extends Controller
{
    // ================================================================
    // 教室マスタ
    // ================================================================

    public function schoolsIndex()
    {
        return response()->json(
            LabelSchoolMaster::orderByRaw('ISNULL(stop_order), stop_order, code')
                ->get()
        );
    }

    public function schoolsStore(Request $request)
    {
        $data = $request->validate([
            'code'         => 'required|string|max:20|unique:label_school_masters,code',
            'display_name' => 'required|string|max:150',
            'area'         => 'nullable|string|max:50',
            'route'        => 'nullable|string|max:10',
            'stop_order'   => 'nullable|integer|min:1|max:65535',
            'is_active'    => 'boolean',
            'notes'        => 'nullable|string',
        ]);
        $data['area'] = $data['area'] ?? '';
        $school = LabelSchoolMaster::create($data);
        return response()->json($school, 201);
    }

    public function schoolsUpdate(Request $request, LabelSchoolMaster $school)
    {
        $data = $request->validate([
            'code'         => 'required|string|max:20|unique:label_school_masters,code,' . $school->id,
            'display_name' => 'required|string|max:150',
            'area'         => 'nullable|string|max:50',
            'route'        => 'nullable|string|max:10',
            'stop_order'   => 'nullable|integer|min:1|max:65535',
            'is_active'    => 'boolean',
            'notes'        => 'nullable|string',
        ]);
        $data['area'] = $data['area'] ?? '';
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

    // ================================================================
    // 社内便ルートマスタ
    // ================================================================

    public function routesIndex()
    {
        $routes = LabelRoute::with(['stops' => function ($q) {
            $q->orderBy('stop_order')
              ->with('schoolMaster:id,code,display_name,area,route,stop_order');
        }])->orderBy('sort_order')->get();

        return response()->json($routes);
    }

    public function routesStore(Request $request)
    {
        $data = $request->validate([
            'code'        => 'required|string|max:10|unique:label_routes,code',
            'course'      => 'required|integer|in:1,2',
            'area'        => 'nullable|string|max:50',
            'day1'        => 'nullable|string|max:20',
            'day1_start'  => 'nullable|string|max:50',
            'day2'        => 'nullable|string|max:20',
            'day2_start'  => 'nullable|string|max:50',
            'sort_order'  => 'nullable|integer',
        ]);
        $data['area']       = $data['area'] ?? '';
        $data['day1']       = $data['day1'] ?? '';
        $data['day1_start'] = $data['day1_start'] ?? '';
        $route = LabelRoute::create($data);
        return response()->json($route->load('stops'), 201);
    }

    public function routesUpdate(Request $request, LabelRoute $route)
    {
        $data = $request->validate([
            'code'        => 'required|string|max:10|unique:label_routes,code,' . $route->id,
            'course'      => 'required|integer|in:1,2',
            'area'        => 'nullable|string|max:50',
            'day1'        => 'nullable|string|max:20',
            'day1_start'  => 'nullable|string|max:50',
            'day2'        => 'nullable|string|max:20',
            'day2_start'  => 'nullable|string|max:50',
            'sort_order'  => 'nullable|integer',
        ]);
        $data['area']       = $data['area'] ?? '';
        $data['day1']       = $data['day1'] ?? '';
        $data['day1_start'] = $data['day1_start'] ?? '';
        $route->update($data);
        return response()->json($route->fresh()->load('stops'));
    }

    public function routesDestroy(LabelRoute $route)
    {
        $route->delete();
        return response()->json(null, 204);
    }

    // ── 停留所 ──

    public function stopsStore(Request $request, LabelRoute $route)
    {
        $data = $request->validate([
            'stop_order'     => 'required|integer|min:1|max:99',
            'school_code'    => 'nullable|string|max:10',
            'school_name'    => 'nullable|string|max:150',
            'arrival_time'   => 'nullable|string|max:10',
            'notes'          => 'nullable|string|max:200',
            'color_category' => 'nullable|string|max:20',
        ]);
        $data['route_id']    = $route->id;
        $data['school_name'] = $data['school_name'] ?? '';
        $stop = LabelRouteStop::updateOrCreate(
            ['route_id' => $route->id, 'stop_order' => $data['stop_order']],
            $data
        );
        return response()->json($stop->fresh()->load('schoolMaster:id,code,display_name,area,route,stop_order'), 201);
    }

    public function stopsUpdate(Request $request, LabelRouteStop $routeStop)
    {
        $data = $request->validate([
            'school_code'    => 'nullable|string|max:10',
            'school_name'    => 'nullable|string|max:150',
            'arrival_time'   => 'nullable|string|max:10',
            'notes'          => 'nullable|string|max:200',
            'color_category' => 'nullable|string|max:20',
        ]);
        $data['school_name'] = $data['school_name'] ?? '';
        $routeStop->update($data);
        return response()->json($routeStop->fresh()->load('schoolMaster:id,code,display_name,area,route,stop_order'));
    }

    public function stopsInsertAt(Request $request, LabelRoute $route)
    {
        $data = $request->validate(['stop_order' => 'required|integer|min:1|max:99']);
        $pos  = $data['stop_order'];

        \DB::transaction(function () use ($route, $pos) {
            \DB::statement(
                'UPDATE label_route_stops SET stop_order = stop_order + 1, updated_at = ? WHERE route_id = ? AND stop_order >= ? ORDER BY stop_order DESC',
                [now(), $route->id, $pos]
            );
            LabelRouteStop::create([
                'route_id'    => $route->id,
                'stop_order'  => $pos,
                'school_name' => '',
            ]);
        });

        return response()->json(
            $route->fresh()->load('stops.schoolMaster:id,code,display_name,area,route,stop_order')
        );
    }

    public function stopsDestroyShift(LabelRouteStop $routeStop)
    {
        $routeId   = $routeStop->route_id;
        $stopOrder = $routeStop->stop_order;

        \DB::transaction(function () use ($routeStop, $routeId, $stopOrder) {
            $routeStop->delete();
            LabelRouteStop::where('route_id', $routeId)
                ->where('stop_order', '>', $stopOrder)
                ->decrement('stop_order');
        });

        $route = LabelRoute::with('stops.schoolMaster:id,code,display_name,area,route,stop_order')->find($routeId);
        return response()->json($route);
    }

    public function stopsDestroy(LabelRouteStop $routeStop)
    {
        $routeStop->delete();
        return response()->json(null, 204);
    }
}
