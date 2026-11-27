<?php
namespace Tests\Integration;

use App\Http\Controllers\Billing\Transport\ExpenseController;
use App\Http\Requests\Billing\Transport\SaveExpenseRequest;
use App\Models\{TransportExpense, User};
use App\Services\Billing\Transport\{TransportApplicationDate, TransportDeadline, TransportExpenseWorkflow, TransportExpenseExporter, TransportNotifier, TransportFareStock};
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema,Validator};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
require_once __DIR__.'/UnifiedMyJobCreateTest.php';

class TransportApplicationDateTest extends UnifiedMyJobCreateTest
{
    protected function setUp():void
    {
        parent::setUp();
        if (!str_starts_with($this->name(),'test_transport_')) return;
        Carbon::setTestNow(Carbon::parse('2026-11-20 10:15:00','Asia/Tokyo'));
        $views=sys_get_temp_dir().'/transport-date-views-'.getmypid();if(!is_dir($views))mkdir($views,0777,true);config(['view.compiled'=>$views]);
        DB::table('users')->where('id',1)->update(['user_role'=>'user','name'=>'合成申請者']);
        Schema::table('departments',fn(Blueprint $t)=>$t->boolean('active')->default(true));
        Schema::create('transport_expenses',function(Blueprint $t){$t->id();foreach(['user_id','company_id','department_id','charge_department_id','department_code','total_amount','reviewed_by']as$c)$t->integer($c)->nullable();$t->string('billing_month');$t->date('billing_date');$t->string('status');foreach(['submitted_at','reviewed_at','billed_at']as$c)$t->timestamp($c)->nullable();foreach(['review_comment','applicant_comment']as$c)$t->text($c)->nullable();$t->timestamps();$t->unique(['user_id','billing_month']);});
        if ($this->name() !== 'test_transport_migration_protects_existing_document_dates') (require base_path('database/migrations/2026_11_27_120000_add_billing_date_manual_to_transport_expenses.php'))->up();
        Schema::create('transport_expense_items',function(Blueprint $t){$t->id();$t->integer('transport_expense_id');$t->integer('sort_order');$t->date('occurrence_date')->nullable();foreach(['destination','purpose','purpose_text','station_from','station_to','fare_type']as$c)$t->string($c)->nullable();$t->json('via_stations')->nullable();$t->integer('amount');$t->integer('base_amount')->nullable();$t->boolean('auto_double')->default(false);$t->timestamps();});
        Schema::create('transport_expense_item_companions',function(Blueprint $t){$t->id();$t->integer('transport_expense_item_id');$t->integer('user_id');$t->timestamps();});
        Schema::create('transport_expense_events',function(Blueprint $t){$t->id();$t->integer('transport_expense_id');$t->integer('user_id');foreach(['action','from_status','to_status']as$c)$t->string($c);$t->text('comment')->nullable();$t->timestamp('created_at')->nullable();});
        Schema::create('transport_deadline_extensions',function(Blueprint $t){$t->id();$t->integer('user_id');$t->string('billing_month');$t->string('status');});
        app()->instance(TransportNotifier::class,new class extends TransportNotifier {
            public function submitted(TransportExpense $e,User $u,bool $resubmit=false,?string $comment=null):void {}
            public function reviewed(TransportExpense $e,User $u,string $action,?string $comment):void {}
        });
        app()->instance(TransportFareStock::class,new class extends TransportFareStock {public function recordFromExpense(TransportExpense $e):void {}});
    }
    protected function tearDown():void {Carbon::setTestNow();parent::tearDown();}
    private function body(array $changes=[]):array {return $changes+['billing_month'=>'2026-11','billing_date'=>'2026-11-30','items'=>[['occurrence_date'=>'2026-11-19','destination'=>'訪問先','purpose'=>'outbound','station_from'=>'駅A','station_to'=>'駅B','fare_type'=>'ic','amount'=>200]]];}
    private function validated(array $data):array {
        $r=SaveExpenseRequest::create('/synthetic','POST',$data);$r->setUserResolver(fn()=>User::findOrFail(1));
        $v=Validator::make($r->all(),$r->rules(),$r->messages());$r->withValidator($v);return $v->validate();
    }
    private function persist(array $data, int $user=1):TransportExpense {
        self::assertSame(':memory:',DB::connection()->getDatabaseName());
        return (new \ReflectionMethod(ExpenseController::class,'persist'))->invoke(app(ExpenseController::class),$data,User::findOrFail($user));
    }
    public function test_transport_migration_protects_existing_document_dates():void {
        $id=DB::table('transport_expenses')->insertGetId(['user_id'=>1,'company_id'=>1,'billing_month'=>'2026-11','billing_date'=>'2026-11-06','status'=>'draft','total_amount'=>200]);
        (require base_path('database/migrations/2026_11_27_120000_add_billing_date_manual_to_transport_expenses.php'))->up();
        $e=TransportExpense::findOrFail($id);self::assertTrue($e->billing_date_manual);self::assertSame('2026-11-06',$e->billing_date->format('Y-m-d'));self::assertSame('draft',$e->status);self::assertSame(200,$e->total_amount);
    }
    public function test_transport_automatic_date_follows_save_day_and_manual_choice_survives():void {
        $e=$this->persist($this->validated($this->body(['billing_date_manual'=>false])));
        self::assertFalse($e->billing_date_manual);self::assertSame('2026-11-20',$e->billing_date->format('Y-m-d'));
        Carbon::setTestNow(Carbon::parse('2026-11-27 09:00:00','Asia/Tokyo'));
        $e=$this->persist($this->validated($this->body(['billing_date'=>'2026-11-20','billing_date_manual'=>false])));
        self::assertSame('2026-11-27',$e->billing_date->format('Y-m-d'));
        $e=$this->persist($this->validated($this->body(['billing_date_manual'=>true])));
        self::assertTrue($e->billing_date_manual);self::assertSame('2026-11-30',$e->billing_date->format('Y-m-d'));
        $b=$this->body();unset($b['billing_date']);$e=$this->persist($this->validated($b));
        self::assertTrue($e->billing_date_manual);self::assertSame('2026-11-30',$e->billing_date->format('Y-m-d'));
        $e=$this->persist($this->validated($this->body(['billing_date_manual'=>false])));
        self::assertFalse($e->billing_date_manual);self::assertSame('2026-11-27',$e->billing_date->format('Y-m-d'));
    }
    public function test_transport_future_month_end_is_saved_and_omitted_date_preserves_it():void {
        $e=$this->persist($this->validated($this->body()));self::assertSame('2026-11-30',$e->billing_date->format('Y-m-d'));
        $b=$this->body();unset($b['billing_date']);$e=$this->persist($this->validated($b));self::assertSame('2026-11-30',$e->billing_date->format('Y-m-d'));
        $e=$this->persist($this->validated($this->body(['billing_date'=>'2026-11-27'])));self::assertSame('2026-11-27',$e->billing_date->format('Y-m-d'));self::assertSame(200,(int)$e->total_amount);
    }
    public function test_transport_invalid_date_month_and_empty_date_never_change_saved_rows():void {
        $e=$this->persist($this->validated($this->body()));
        foreach(['2026-12-01','2026-10-31','2026-11-31','bad','']as$d){try{$this->persist($this->validated($this->body(['billing_date'=>$d])));self::fail('Invalid date accepted');}catch(ValidationException $x){self::assertArrayHasKey('billing_date',$x->errors());}}
        self::assertSame('2026-11-30',$e->fresh()->billing_date->format('Y-m-d'));self::assertSame(1,DB::table('transport_expense_items')->count());
    }
    public function test_transport_submit_withdraw_return_resubmit_keep_document_date_and_real_event_times():void {
        $e=$this->persist($this->validated($this->body()));$u=User::findOrFail(1);$flow=app(TransportExpenseWorkflow::class);
        $e=$flow->apply($e,$u,'submit');self::assertSame('2026-11-30',$e->billing_date->format('Y-m-d'));self::assertSame('2026-11-20 10:15:00',$e->submitted_at->format('Y-m-d H:i:s'));
        $e=$flow->apply($e,$u,'withdraw');self::assertNull($e->submitted_at);self::assertSame('2026-11-30',$e->billing_date->format('Y-m-d'));
        Carbon::setTestNow(Carbon::parse('2026-11-23 09:00:00','Asia/Tokyo'));$e=$flow->apply($e,$u,'submit');$e=$flow->apply($e,$u,'return','日付確認');
        $e=$this->persist($this->validated($this->body(['billing_date'=>'2026-11-27'])));$e=$flow->apply($e,$u,'submit');self::assertSame('2026-11-27',$e->billing_date->format('Y-m-d'));self::assertSame('2026-11-23 09:00:00',$e->submitted_at->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-20 10:15:00',DB::table('transport_expense_events')->where('action','submit')->orderBy('id')->value('created_at'));
        self::assertSame(3,DB::table('transport_expense_events')->where('action','submit')->count());
    }
    public function test_transport_submitted_records_cannot_be_edited_or_submitted_by_other_user():void {
        $e=$this->persist($this->validated($this->body()));$e=app(TransportExpenseWorkflow::class)->apply($e,User::findOrFail(1),'submit');
        try{$this->persist($this->validated($this->body(['billing_date'=>'2026-11-26'])));self::fail('Submitted edited');}catch(HttpException $x){self::assertSame(422,$x->getStatusCode());}
        $request=\Illuminate\Http\Request::create('/synthetic','POST');$request->setUserResolver(fn()=>User::findOrFail(2));
        try{app(ExpenseController::class)->submit($request,$e);self::fail('Other user submitted');}catch(HttpException $x){self::assertSame(403,$x->getStatusCode());}
        self::assertSame('2026-11-30',$e->fresh()->billing_date->format('Y-m-d'));
    }
    public function test_transport_initial_date_is_inside_target_month_and_does_not_change_deadline_policy():void {
        self::assertSame('2026-11-20',TransportApplicationDate::initial('2026-11'));self::assertSame('2026-10-31',TransportApplicationDate::initial('2026-10'));self::assertSame('2026-12-01',TransportApplicationDate::initial('2026-12'));
        Carbon::setTestNow(Carbon::parse('2026-10-31 12:00:00','Asia/Tokyo'));self::assertFalse(TransportDeadline::passed('2026-10'));
        Carbon::setTestNow(Carbon::parse('2026-11-01 00:00:00','Asia/Tokyo'));self::assertTrue(TransportDeadline::passed('2026-10'));
        self::assertFalse(TransportDeadline::isOpen(1,'2026-10'));DB::table('transport_deadline_extensions')->insert(['user_id'=>1,'billing_month'=>'2026-10','status'=>'granted']);self::assertTrue(TransportDeadline::isOpen(1,'2026-10'));
    }
    public function test_transport_new_omitted_date_and_independent_users_keep_existing_defaults():void {
        $b=$this->body();unset($b['billing_date']);$a=$this->persist($this->validated($b));DB::table('users')->where('id',2)->update(['company_id'=>2,'department_id'=>3]);$other=$this->persist($this->validated($this->body()),2);
        self::assertNotSame($a->id,$other->id);self::assertSame(1,(int)$a->company_id);self::assertSame(2,(int)$other->company_id);self::assertSame('2026-11-20',$a->billing_date->format('Y-m-d'));self::assertSame('2026-11-30',$other->billing_date->format('Y-m-d'));
    }
    public function test_transport_invalid_date_on_direct_submit_rolls_back_status_and_history():void {
        $e=$this->persist($this->validated($this->body()));DB::table('transport_expenses')->where('id',$e->id)->update(['billing_date'=>'2026-10-31']);
        try{app(TransportExpenseWorkflow::class)->apply($e->fresh(),User::findOrFail(1),'submit');self::fail('Wrong-month date accepted');}catch(ValidationException $x){self::assertArrayHasKey('billing_date',$x->errors());}
        self::assertSame('draft',$e->fresh()->status);self::assertNull($e->fresh()->submitted_at);self::assertSame(0,DB::table('transport_expense_events')->count());
        try{$this->validated($this->body(['charge_department_id'=>3]));self::fail('Other company department accepted');}catch(ValidationException $x){self::assertArrayHasKey('charge_department_id',$x->errors());}
    }
    public function test_transport_export_excel_and_pdf_use_chosen_document_date():void {
        $e=$this->persist($this->validated($this->body()));$book=new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        (new \ReflectionMethod(TransportExpenseExporter::class,'fillSheet'))->invoke(app(TransportExpenseExporter::class),$book->getActiveSheet(),$e,collect(),0,0,200);
        $actual=\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($book->getActiveSheet()->getCell('D3')->getValue());self::assertSame('2026-11-30',$actual->format('Y-m-d'));
        $book->disconnectWorksheets();
        $pdf = new class {
            public string $view; public array $data;
            public function loadView($view,$data){$this->view=$view;$this->data=$data;return $this;}
            public function setPaper(...$args){return $this;}
            public function setOption(...$args){return $this;}
        };
        \Barryvdh\DomPDF\Facade\Pdf::swap($pdf);
        app(TransportExpenseExporter::class)->pdf($e);
        $html=view($pdf->view,$pdf->data)->render();
        self::assertStringContainsString('令和8年11月30日',$html);
    }
}
