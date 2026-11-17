import axios from 'axios';
import { computed, onScopeDispose, reactive, ref, unref, watch } from 'vue';
import { route } from 'ziggy-js';
export function usePersonalCalendarState({year,month,ownerKey,enabled,currentDate,viewMode}) {
const showJobs=ref(false),showCompleted=ref(false),page=ref(1),data=ref({items:[],todos:[],todo_total:0,todo_last_page:1}),loading=ref(false),error=ref(''),busy=ref(false),editor=ref(null);
try {showJobs.value=localStorage.getItem('personal_month_jobs_'+unref(ownerKey))==='1';}catch{}
watch(showJobs,v=>{try{localStorage.setItem('personal_month_jobs_'+unref(ownerKey),v?'1':'0');}catch{}});
const range=computed(()=>({start:`${unref(year)}-${String(unref(month)).padStart(2,'0')}-01`,end:new Date(unref(year),unref(month),0,12).toLocaleDateString('sv-SE')}));
let sequence=0;
async function load(){if(!unref(enabled))return;const seq=++sequence;loading.value=true;error.value='';try{const res=await axios.get(route('personal_calendar.items.index'),{params:{...range.value,sidebar:1,todo_page:page.value,show_completed:showCompleted.value?1:0}});if(seq===sequence)data.value=res.data;}catch(e){if(seq===sequence)error.value=e.response?.data?.message||'個人予定・TODOを読み込めませんでした。';}finally{if(seq===sequence)loading.value=false;}}
watch([range,enabled,ownerKey],(current,previous)=>{sequence++;if(previous?.length && current[2]!==previous[2]){data.value={items:[],todos:[],todo_total:0,todo_last_page:1};editor.value=null;try{showJobs.value=localStorage.getItem('personal_month_jobs_'+unref(ownerKey))==='1';todoOpen.value=localStorage.getItem('personal_calendar_todo_open_'+unref(ownerKey))!=='0';}catch{showJobs.value=false;todoOpen.value=true;}}if(!unref(enabled)){data.value={items:[],todos:[],todo_total:0,todo_last_page:1};editor.value=null;loading.value=false;return;}page.value=1;load();},{immediate:true});watch(showCompleted,()=>{if(!unref(enabled))return;page.value=1;load();});
async function changePage(value){page.value=value;await load();}
function create(date='',endDate=''){editor.value={item:null,date,endDate,type:'event'};}
function createTodo(){editor.value={item:null,date:'',type:'todo'};}
function edit(item){editor.value={item};}
async function saved(){editor.value=null;await load();}
async function toggle(item){if(busy.value)return;busy.value=true;error.value='';try{await axios.patch(route('personal_calendar.items.complete',{item:item.id}),{version:item.version,completed:!item.completed},{headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),Accept:'application/json'}});await load();}catch(e){error.value=e.response?.data?.message||'完了状態を保存できませんでした。';}finally{busy.value=false;}}

const todoOpen=ref(true), mobileOpen=ref(false);
try {todoOpen.value=localStorage.getItem('personal_calendar_todo_open_'+unref(ownerKey))!=='0';}catch{}
watch(todoOpen,value=>{try{localStorage.setItem('personal_calendar_todo_open_'+unref(ownerKey),value?'1':'0');}catch{}});
function openMobile(){mobileOpen.value=!mobileOpen.value;if(mobileOpen.value)todoOpen.value=true;}
onScopeDispose(()=>{sequence++;});
const todoHref=computed(()=>route('personal_todo.index',{date:unref(currentDate)||range.value.start,calendar_view:unref(viewMode)||'month'}));
return reactive({todoHref,showJobs,showCompleted,page,data,loading,error,busy,editor,range,todoOpen,mobileOpen,load,changePage,create,createTodo,edit,saved,toggle,openMobile});
}
