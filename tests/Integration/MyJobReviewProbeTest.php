<?php
namespace Tests\Integration;

use App\Http\Controllers\Schedule\DepartmentWorkController;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/UnifiedMyJobCreateTest.php';

class MyJobReviewProbeTest extends UnifiedMyJobCreateTest
{
    public function test_review_retry_and_changed_payload(): void {
        $body=$this->payload();
        $first=$this->save($this->request($body))->getData(true);
        $second=$this->save($this->request($body))->getData(true);
        self::assertSame($first['id'],$second['id']);
        self::assertTrue($second['replayed']);
        self::assertSame(1,DB::table('personal_work_entries')->count());
        try { $this->save($this->request(array_replace($body,['title'=>'different']))); self::fail(); }
        catch(HttpException $e) { self::assertSame(422,$e->getStatusCode()); }
        $this->save($this->request(array_replace($body,['request_token'=>(string) \Illuminate\Support\Str::uuid()])));
        self::assertSame(2,DB::table('personal_work_entries')->count());
    }
    public function test_review_legacy_quote_can_be_edited(): void {
        DB::table('project_jobs')->insert(['id'=>1,'company_id'=>null,'title'=>'legacy']);
        DB::table('project_job_assignments')->insert(['id'=>1,'project_job_id'=>1,'user_id'=>1,'sender_id'=>1]);
        $body=$this->payload(['reuse_assignment_id'=>1,'project_job_id'=>1,'broad_project_id'=>null]);
        $id=$this->save($this->request($body))->getData(true)['id'];
        $entry=\App\Models\PersonalWorkEntry::findOrFail($id);
        $body['edit_version']=app(\App\Services\WorkJobInput::class)->version($entry);
        $body['title']='edited';
        app(DepartmentWorkController::class)->update($this->request($body),$entry);
        self::assertSame('edited',$entry->fresh()->title);
    }
    public function test_review_stale_edit_is_rejected(): void {
        $id=$this->save($this->request($this->payload()))->getData(true)['id'];
        $entry=\App\Models\PersonalWorkEntry::findOrFail($id);
        $version=app(\App\Services\WorkJobInput::class)->version($entry);
        app(DepartmentWorkController::class)->update($this->request($this->payload(['edit_version'=>$version,'title'=>'newer title'])),$entry);
        try { app(DepartmentWorkController::class)->update($this->request($this->payload(['edit_version'=>$version])),$entry); self::fail(); }
        catch(HttpException $e) { self::assertSame(409,$e->getStatusCode()); }
        self::assertSame('newer title',$entry->fresh()->title);
    }

    public function test_review_deleted_creation_is_not_recreated(): void {
        $body=$this->payload();
        $id=$this->save($this->request($body))->getData(true)['id'];
        \App\Models\PersonalWorkEntry::findOrFail($id)->delete();
        try { $this->save($this->request($body)); self::fail(); }
        catch(HttpException $e) { self::assertSame(410,$e->getStatusCode()); }
        self::assertSame(0,DB::table('personal_work_entries')->count());
    }
    public function test_review_missing_version_cannot_overwrite_and_other_company_stays_denied(): void {
        $id=$this->save($this->request($this->payload()))->getData(true)['id'];
        $entry=\App\Models\PersonalWorkEntry::findOrFail($id);
        try { app(DepartmentWorkController::class)->update($this->request($this->payload()),$entry); self::fail(); }
        catch(\Illuminate\Validation\ValidationException $e) { self::assertArrayHasKey('edit_version',$e->errors()); }
        DB::table('project_jobs')->insert(['id'=>2,'company_id'=>2,'title'=>'foreign']);
        $version=app(\App\Services\WorkJobInput::class)->version($entry);
        try { app(DepartmentWorkController::class)->update($this->request($this->payload(['edit_version'=>$version,'project_job_id'=>2,'broad_project_id'=>null])),$entry); self::fail(); }
        catch(HttpException $e) { self::assertSame(403,$e->getStatusCode()); }
    }
}
