"use strict";
/**
 * Real HTTP request/response proof on disposable MODX2/3 PHP server. The
 * test router simulates HTTPS termination locally, not transport TLS.
 * All CRUD uses the actual MODX MCP HTTP API (no SQL/direct MODX content).
 */
const fs=require("node:fs");
const crypto=require("node:crypto");
const {spawnSync}=require("node:child_process");
const path=require("node:path");

if(process.env.MODXMCP_DISPOSABLE_CI!=="1"
    || !String(process.env.MODX_CONFIG_CORE||"").startsWith("/home/runner/"))
  throw Error("Refusing HTTP test outside disposable GitHub CI");
const [privateKeyFile,tokenFile,port,site]=process.argv.slice(2);
if(!privateKeyFile?.startsWith("/home/runner/")
    || !tokenFile?.startsWith("/home/runner/")
    || !/^[0-9]{4,5}$/.test(String(port))
    || !/^ci-http-modx[23]$/.test(String(site)))
  throw Error("Invalid HTTP test fixture inputs");
const token=fs.readFileSync(tokenFile,"utf8").trim();
if(!/^[0-9a-f]{64}$/.test(token))throw Error("Missing token");
const url="http://127.0.0.1:"+port+"/assets/components/modxmcp/api.php";
const signer=path.join(__dirname,"test.ci-ed25519-controller.cjs");
let checks=0;
function check(value,description){
  if(!value)throw Error("HTTP_SECURITY_CHECK_FAILED "+description);
  checks++;
}
function sign(raw,action,elementType){
  const result=spawnSync(process.execPath,[
    signer,"sign",privateKeyFile,site,action,elementType,
  ],{input:raw,encoding:"utf8",maxBuffer:128*1024});
  if(result.status!==0)throw Error("Offline controller refused request: "+result.stderr);
  const permit=JSON.parse(result.stdout);
  return Buffer.from(JSON.stringify(permit),"utf8").toString("base64");
}
async function request(method,body=null,permit=null,auth=token,headersExtra={}){
  const headers={...headersExtra};
  if(auth!==null)headers["X-MCP-Token"]=auth;
  if(permit!==null)headers["X-ModxMCP-Permit"]=permit;
  if(body!==null)headers["Content-Type"]="application/json";
  const response=await fetch(url,{
    method,headers,body,signal:AbortSignal.timeout(12000),
  });
  const content=await response.text();
  let data=null;
  try{data=JSON.parse(content);}catch(_){}
  return {status:response.status,data,content};
}
async function api(action,data={},type="",permit=null,auth=token,extra={}){
  const raw=JSON.stringify({action,type,data});
  return request("POST",raw,permit,auth,extra);
}
async function state(){
  const response=await api("get_site_state");
  check(response.status===200 && response.data?.success===true
    && Number.isSafeInteger(response.data.data.site_revision),
    "read-only site state via authenticated HTTP");
  return response.data.data.site_revision;
}
async function denied(raw,permit,code,description,auth=token){
  const response=await request("POST",raw,permit,auth);
  check(response.status===code,
    description+" (HTTP "+response.status+", "+JSON.stringify(response.data||{}).slice(0,150)+")");
  check(response.data?.success===false,
    description+" must be a structured JSON denial");
}
async function main(){
  let health;
  for(let i=0;i<45;i++){
    try{health=await request("GET");break;}catch(_){
      await new Promise(resolve=>setTimeout(resolve,100));
    }
  }
  check(health?.status===200 && health.data?.component==="modxMCP"
    && health.data?.enabled===true,"health probe over local simulated HTTPS");
  const rev0=await state();
  const unique=crypto.randomBytes(6).toString("hex");
  const title="Protected HTTP CAS "+unique;
  const guard=rev=>({observed_site_revision:rev,strict_site_revision:true,read_set:[]});
  const create={
    type:"resource",name:title,alias:"protected-http-"+unique,
    content:"BEFORE",template:0,parent:0,published:0,hidemenu:1,
    _runtime_preconditions:guard(rev0),
  };
  const raw=JSON.stringify({action:"create_element",type:"resource",data:create});
  const signature=sign(raw,"create_element","resource");

  await denied(raw,null,403,"request without independent permit");
  await denied(raw,signature,401,"signed request without HTTP API token",null);
  await denied(raw,signature,401,"signed request with incorrect API token",token.slice(0,-1)+(token.endsWith("0")?"1":"0"));
  await denied(raw+" ",signature,403,"signed HTTP payload whitespace changed");
  const bodyTampered=JSON.stringify({action:"create_element",type:"resource",
    data:{...create,content:"MALICIOUS"}});
  await denied(bodyTampered,signature,403,"signed HTTP content changed");
  const claimChanged=JSON.parse(Buffer.from(signature,"base64").toString("utf8"));
  claimChanged.claims.site_id="another-site";
  await denied(raw,Buffer.from(JSON.stringify(claimChanged)).toString("base64"),
    403,"changed permit site claim");
  await denied(raw,"not-base64",403,"invalid permit header");
  const response=await request("POST",raw,signature);
  check(response.status===200 && response.data?.success===true
    && Number(response.data.data.id)>0,
    "signed resource creation via real HTTP endpoint");
  const id=Number(response.data.data.id);
  const revAfterCreate=await state();
  check(revAfterCreate===rev0+1,"CAS advanced after first HTTP mutation");
  await denied(raw,signature,403,"same signed permit replayed");
  check(await state()===revAfterCreate,"replay did not increment revision");
  const retrieved=await api("get_element",{type:"resource",id},"resource");
  check(retrieved.status===200 && retrieved.data?.data?.content==="BEFORE"
    && Number(retrieved.data.data.published)===0
    && Number(retrieved.data.data.hidemenu)===1,
    "resource readback through HTTP API");
  const modify={type:"resource",id,content:"AFTER",
    _runtime_preconditions:guard(revAfterCreate)};
  const rawModify=JSON.stringify({action:"update_element",type:"resource",data:modify});
  const signModify=sign(rawModify,"update_element","resource");
  const changed=await request("POST",rawModify,signModify);
  check(changed.status===200 && changed.data?.success===true,
    "approved protected resource update");
  const afterUpdate=await state();
  check(afterUpdate===revAfterCreate+1,"CAS advanced after approved update");
  await denied(rawModify,signModify,403,"protected update cannot be replayed");
  const stateAfterUpdate=await api("get_element",{type:"resource",id},"resource");
  check(stateAfterUpdate.data?.data?.content==="AFTER",
    "updated resource is visible through MCP HTTP");
  const stale={...modify,content:"NEVER_WRITE",
    _runtime_preconditions:guard(rev0)};
  const rawStale=JSON.stringify({action:"update_element",type:"resource",data:stale});
  const responseStale=await request("POST",rawStale,sign(rawStale,"update_element","resource"));
  check(responseStale.status===400 && String(responseStale.data?.error||"").includes("STALE_STATE"),
    "valid signed permit cannot bypass stale CAS");
  check(await state()===afterUpdate,"stale signed write did not change revision");
  const topLevelInjection=JSON.stringify({
    action:"update_element",type:"resource",data:modify,content:"INJECTED",
  });
  await denied(topLevelInjection,sign(topLevelInjection,"update_element","resource"),
    403,"signed top-level request alias injection rejected");

  const remove={type:"resource",id,dry_run:false,
    _runtime_preconditions:guard(afterUpdate)};
  const rawRemove=JSON.stringify({action:"delete_element",type:"resource",data:remove});
  const removed=await request("POST",rawRemove,sign(rawRemove,"delete_element","resource"));
  check(removed.status===200 && removed.data?.success===true,
    "signed protected resource soft-delete");
  const readDeleted=await api("get_element",{type:"resource",id},"resource");
  check(readDeleted.status===200 && readDeleted.data?.data?.deleted,
    "soft-deleted resource confirmed via MCP HTTP readback");
  console.log("PROTECTED_ED25519_HTTP_END_TO_END_OK site="+site
    +" checks="+checks+" replay=denied cas=stale-denied");
}
main().catch(error=>{console.error(error.stack||error.message);process.exit(1);});
