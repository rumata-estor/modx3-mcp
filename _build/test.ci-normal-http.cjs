"use strict";
// MODX MCP SOURCE / ИСХОДНИК MODX MCP
// RU: Проверяет обычный HTTP API без Ed25519 на одноразовом MODX 2/3.
// EN: Tests ordinary HTTP API without Ed25519 on disposable MODX 2/3.
/**
 * RU: Проверяем разрешённые действия, подлинную запись и отказ по CAS.
 * EN: Tests permitted actions, actual persistence and stale CAS rejection.
 */
const fs=require("node:fs");
const crypto=require("node:crypto");
if (process.env.MODXMCP_DISPOSABLE_CI!=="1"
    || !String(process.env.MODX_CONFIG_CORE||"").startsWith("/home/runner/")) {
  throw Error("Refusing ordinary HTTP test outside disposable CI.");
}
const [tokenFile,port]=process.argv.slice(2);
if (!String(tokenFile||"").startsWith("/home/runner/")
    || !/^[0-9]{4,5}$/.test(String(port))) {
  throw Error("Invalid disposable HTTP arguments.");
}
const token=fs.readFileSync(tokenFile,"utf8").trim();
if (!/^[a-f0-9]{64}$/.test(token))throw Error("Bad disposable API token");
const url="http://127.0.0.1:"+port+"/assets/components/modxmcp/api.php";
const checks={count:0};
const assert=(ok,message)=>{
  if(!ok)throw Error("ORDINARY_HTTP_ACCEPTANCE_FAILED: "+message);
  checks.count++;
};
async function http(method,request=null,credential=token){
  const headers={};
  if(credential!==null)headers["X-MCP-Token"]=credential;
  if(request!==null)headers["Content-Type"]="application/json";
  const response=await fetch(url,{
    method,headers,body:request===null?undefined:JSON.stringify(request),
    signal:AbortSignal.timeout(12000),
  });
  const raw=await response.text();
  let body=null;
  try{body=JSON.parse(raw);}catch(_){}
  return {status:response.status,body,raw};
}
async function api(action,data={},type=""){
  const result=await http("POST",{action,type,data});
  if(result.status!==200||result.body?.success!==true){
    throw Error("API_FAILED "+action+" "+result.status+" "+result.raw.slice(0,280));
  }
  return result.body.data;
}
async function state(read_set=[]){
  const result=await api("get_site_state",{read_set});
  assert(Number.isSafeInteger(result.site_revision) && result.precondition_version===2,
    "authenticated site state with CAS v2");
  return result;
}
const guard=snapshot=>({
  observed_site_revision:snapshot.site_revision,
  strict_site_revision:true,read_set:snapshot.read_set||[],
});
async function targetGuard(id){
  const snapshot=await state([{role:"target",type:"resource",id}]);
  assert(snapshot.read_set?.length===1
    && /^[a-f0-9]{64}$/.test(snapshot.read_set[0].fingerprint),
    "full resource fingerprint from server");
  return guard(snapshot);
}
async function denied(action,data,expectedError,type=""){
  const result=await http("POST",{action,type,data});
  assert(result.status===400 && result.body?.success===false
    && String(result.body.error||"").includes(expectedError),
    action+" rejected with "+expectedError+" (got "+result.status+")");
}
async function main(){
  let id=0;
  try{
    // RU: HTTPS в этом тесте эмулирует локальный CI router, не TLS-сертификат.
    // EN: HTTPS is simulated by the CI router; this is not a TLS certificate test.
    let health;
    for(let i=0;i<35;i++){
      try{health=await http("GET",null,null);break;}catch(_){
        await new Promise(resolve=>setTimeout(resolve,100));
      }
    }
    assert(health?.status===200 && health.body?.component==="modxMCP"
      && health.body?.enabled===true,"ordinary HTTP health");
    const bad=await http("POST",{action:"get_site_state",type:"",data:{}},null);
    assert(bad.status===401,"missing API token blocked");
    const initial=await state();
    const supported=await api("list_actions");
    assert(supported && typeof supported==="object"
      && !Array.isArray(supported),"list_actions available");
    const resources=await api("list_resources",{limit:2});
    assert(Array.isArray(resources.results),"resource listing available");
    const unique=crypto.randomBytes(6).toString("hex");
    const name="Ordinary HTTP CI "+unique;
    const content="BEFORE_"+unique;
    const create={
      type:"resource",name,alias:"ordinary-http-"+unique,
      content,published:0,hidemenu:1,template:0,parent:0,
      _runtime_preconditions:guard(initial),
    };
    await denied("create_element",{
      ...create,_runtime_preconditions:undefined,
    },"CAS_REQUIRED","resource");
    const created=await api("create_element",create,"resource");
    assert(Number.isInteger(Number(created.id)) && Number(created.id)>0,
      "resource created over ordinary HTTP");
    id=Number(created.id);
    let read=await api("get_element",{type:"resource",id},"resource");
    assert(read.content===content,"resource creation persisted and read back");
    const before=await targetGuard(id);
    const update={
      type:"resource",id,content:"AFTER_"+unique,
      _runtime_preconditions:before,
    };
    await api("update_element",update,"resource");
    read=await api("get_element",{type:"resource",id},"resource");
    assert(read.content==="AFTER_"+unique,"CAS resource update persisted");
    const stale=await http("POST",{
      action:"update_element",type:"resource",data:{
        ...update,content:"MUST_NOT_APPEAR",
      },
    });
    assert(stale.status===400 && stale.body?.success===false
      && String(stale.body.error).includes("STALE_STATE"),
      "stale revision refused");
    read=await api("get_element",{type:"resource",id},"resource");
    assert(read.content==="AFTER_"+unique,"stale write had no side effects");
    const removal={type:"resource",id,dry_run:false,
      _runtime_preconditions:await targetGuard(id)};
    await api("delete_element",removal,"resource");
    read=await api("get_element",{type:"resource",id},"resource");
    assert(Boolean(read.deleted),"authorized resource soft delete persisted");
    id=0;
    process.stdout.write("ORDINARY_HTTP_FUNCTIONAL_ACCEPTANCE_OK checks="
      +checks.count+"\n");
  }finally{
    if(id>0){
      // RU: При ошибке пытаемся безопасно очистить только собственный объект.
      // EN: On failure, only attempt cleanup of this test's own resource.
      try{
        await api("delete_element",{
          type:"resource",id,dry_run:false,
          _runtime_preconditions:await targetGuard(id),
        },"resource");
      }catch(e){process.stderr.write("DISPOSABLE_HTTP_CLEANUP_ERROR "+e.message+"\n");}
    }
  }
}
main().catch(err=>{process.stderr.write(err.stack+"\n");process.exitCode=1;});
