<?php
namespace Tests\Integration;

use App\Http\Controllers\User\QuickBroadProjectController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
require_once __DIR__.'/UnifiedMyJobCreateTest.php';

class QuickBroadProjectTest extends UnifiedMyJobCreateTest
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('broad_projects', function (Blueprint $t) { $t->integer('sort_order')->nullable(); $t->timestamps(); });
        Schema::table('clients', function (Blueprint $t) { $t->boolean('is_dormant')->default(false); });
    }
    private function actor(array $extra=[]): User { return (new User)->forceFill($extra+['id'=>1,'user_role'=>'user','company_id'=>1,'department_id'=>1]); }
    private function data(array $extra=[]): array { return $extra+['name'=>'新しい分類','group_type'=>'middle','parent_id'=>1,'client_ids'=>[],'scope'=>['company_id'=>1,'department_id'=>1]]; }
    public function test_create_returns_canonical_selectable_row_without_creating_a_job_and_defaults_active(): void
    {
        $controller=app(QuickBroadProjectController::class);
        $options=$controller->options($this->request([], $this->actor()))->getData(true);
        self::assertSame(['company_id'=>1,'department_id'=>1],$options['scope']);
        self::assertSame([1],array_column($options['projects'],'id'));
        $response=$controller->store($this->request($this->data(['is_active'=>false,'company_id'=>2]),$this->actor()));
        self::assertSame(201,$response->getStatusCode());$row=$response->getData(true)['project'];
        self::assertTrue($row['selectable']);self::assertTrue((bool)$row['is_active']);self::assertSame(1,$row['parent_id']);
        self::assertStringContainsString('新しい分類',$row['path']);self::assertSame([], $row['clients']);
        self::assertSame(1,(int)DB::table('broad_projects')->where('id',$row['id'])->value('company_id'));
        self::assertSame(0,DB::table('personal_work_entries')->count());self::assertSame(0,DB::table('project_job_assignments')->count());
    }
    public function test_duplicate_retry_foreign_parent_and_clients_and_paused_parent_do_not_create_partial_rows(): void
    {
        $controller=app(QuickBroadProjectController::class);
        $controller->store($this->request($this->data(),$this->actor()));$count=DB::table('broad_projects')->count();
        foreach ([$this->data(),$this->data(['name'=>'別名','parent_id'=>2]),$this->data(['name'=>'別名','client_ids'=>[999]])] as $data) {
            try {$controller->store($this->request($data,$this->actor()));self::fail('must reject');} catch (ValidationException $e) {self::assertNotEmpty($e->errors());}
            self::assertSame($count,DB::table('broad_projects')->count());
        }
        DB::table('broad_projects')->where('id',1)->update(['is_active'=>false]);
        try {$controller->store($this->request($this->data(['name'=>'休止先']),$this->actor()));self::fail('paused parent');}catch(ValidationException $e){self::assertArrayHasKey('parent_id',$e->errors());}
        self::assertSame($count,DB::table('broad_projects')->count());
    }
    public function test_company_department_and_context_are_checked_on_both_routes(): void
    {
        $controller=app(QuickBroadProjectController::class);
        foreach ([$this->actor(['company_id'=>2,'department_id'=>3]),$this->actor(['department_id'=>2])] as $actor) {
            foreach (['options','store'] as $method) {
                try {$controller->$method($this->request($this->data(),$actor));self::fail('denied');}catch(HttpException $e){self::assertSame(403,$e->getStatusCode());}
            }
        }
        foreach (['options','store'] as $method) {
            try {$controller->$method($this->request($this->data(),$this->actor(['user_role'=>'superadmin']),2));self::fail('context denied');}catch(HttpException $e){self::assertSame(403,$e->getStatusCode());}
        }
        try {$controller->store($this->request($this->data(['scope'=>['company_id'=>1,'department_id'=>2]]),$this->actor()));self::fail('stale scope');}catch(HttpException $e){self::assertSame(409,$e->getStatusCode());}
        self::assertSame(2,DB::table('broad_projects')->count());
    }
    public function test_large_group_and_related_clients_remain_supported(): void
    {
        DB::table('clients')->insert(['id'=>10,'name'=>'関連会社','company_id'=>1]);DB::table('company_clients')->insert(['company_id'=>1,'client_id'=>10]);
        $data=$this->data(['group_type'=>'large','name'=>'新しい大グループ','client_ids'=>[10],'fiscal_year'=>2027]);unset($data['parent_id']);
        $row=app(QuickBroadProjectController::class)->store($this->request($data,$this->actor()))->getData(true)['project'];
        self::assertNull($row['parent_id']);self::assertSame(2027,$row['fiscal_year']);self::assertSame('関連会社',$row['clients'][0]['name']);
    }
}
