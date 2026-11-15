<?php
namespace Tests\Integration;

use App\Http\Controllers\Schedule\DepartmentWorkController;
use App\Models\PersonalWorkEntry;
use App\Services\WorkJobInput;
use App\Services\WorkJobReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;

require_once __DIR__.'/UnifiedMyJobCreateTest.php';

class MyJobSecondReviewTest extends UnifiedMyJobCreateTest
{
    public function test_second_review_foreign_history_is_hidden_and_cannot_be_changed(): void
    {
        $body=$this->payload(['broad_project_id'=>null]);
        $id=$this->save($this->request($body))->getData(true)['id'];
        $controller=app(DepartmentWorkController::class);
        foreach ([['company_id'=>2,'department_id'=>3], ['company_id'=>1,'department_id'=>2], ['company_id'=>1,'department_id'=>1,'user_id'=>2]] as $scope) {
            DB::table('personal_work_entries')->where('id',$id)->update($scope+['user_id'=>1]);
            $entry=PersonalWorkEntry::findOrFail($id);
            $before=$entry->getAttributes();
            $result=$controller->mine($this->request(['start'=>'2026-12-01','end'=>'2026-12-01']))->getData(true);
            self::assertCount(0,$result['events']);
            $body['edit_version']=app(WorkJobInput::class)->version($entry);
            foreach (['detail','update','destroy'] as $action) {
                try {
                    if ($action==='detail') app(WorkJobReader::class)->resolve($this->request()->user(),'entry',$id);
                    elseif ($action==='update') $controller->update($this->request($body),$entry);
                    else $controller->destroy($this->request(),$entry);
                    self::fail('Out of scope action accepted');
                } catch(ModelNotFoundException $e) { self::assertTrue(true); }
            }
            self::assertSame($before,$entry->fresh()->getAttributes());
        }
    }
    public function test_second_review_company_context_denied_and_own_work_still_works(): void
    {
        $body=$this->payload(['broad_project_id'=>null]);
        $id=$this->save($this->request($body))->getData(true)['id'];
        $entry=PersonalWorkEntry::findOrFail($id);
        $body['edit_version']=app(WorkJobInput::class)->version($entry);
        $controller=app(DepartmentWorkController::class);
        foreach (['mine','update','destroy'] as $action) {
            try {
                $r=$this->request($action==='mine' ? ['start'=>'2026-12-01','end'=>'2026-12-01'] : $body,null,2);
                if ($action==='mine') $controller->mine($r);
                else $controller->$action($r,$entry);
                self::fail('Changed company context accepted');
            } catch(\Illuminate\Auth\Access\AuthorizationException|\Symfony\Component\HttpKernel\Exception\HttpException $e) { self::assertTrue(true); }
        }
        self::assertCount(1,$controller->mine($this->request(['start'=>'2026-12-01','end'=>'2026-12-01']))->getData(true)['events']);
        $controller->update($this->request(array_replace($body,['title'=>'own updated'])),$entry);
        self::assertSame('own updated',$entry->fresh()->title);
        $controller->destroy($this->request(),$entry->fresh());
        self::assertNull(PersonalWorkEntry::find($id));
    }
    public function test_second_review_foreign_linked_project_is_not_exposed_in_calendar(): void
    {
        DB::table('project_jobs')->insert(['id'=>10,'company_id'=>1,'title'=>'own project']);
        $id=$this->save($this->request($this->payload(['project_job_id'=>10,'broad_project_id'=>null])))->getData(true)['id'];
        DB::table('project_jobs')->where('id',10)->update(['company_id'=>2]);
        $controller=app(DepartmentWorkController::class);
        self::assertCount(0,$controller->mine($this->request(['start'=>'2026-12-01','end'=>'2026-12-01']))->getData(true)['events']);
        try { $controller->destroy($this->request(),PersonalWorkEntry::findOrFail($id)); self::fail(); }
        catch(\Illuminate\Auth\Access\AuthorizationException $e) { self::assertTrue(true); }
        self::assertNotNull(PersonalWorkEntry::find($id));
    }
}
