// MODX MCP SOURCE / ИСХОДНИК MODX MCP
// RU: Проверка защиты секретов в тестовом окружении.
// EN: Verifies secret protection in a test environment.
// Integration: actual modxApiRequest and audit-hook functions with fake I/O, no site access.
const fs = require("fs");
const vm = require("vm");
const assert = require("assert");
const path = require("path");
const src = fs.readFileSync(path.join(__dirname,"../client/index.js"),"utf8");
const slice = (start,end) => {
 const a=src.indexOf(start), b=src.indexOf(end,a);
 assert(a>=0 && b>a, "source boundaries");
 return src.slice(a,b);
};
let lastInput=null;
let responseMode="success";
let hookOutput = "";
const stderr = [];
const fakeConsole={error:(value)=>stderr.push(String(value))};
const axios={
  post: async (_url,payload,_options) => {
    if (responseMode==="network") throw new Error("password=FAKE_SECRET_NET");
    if (responseMode==="http") {
      const e=new Error("transport failed");
      e.response={data:{success:false,error:'password=FAKE_SECRET_HTTP',details:{api_token:'FAKE_SECRET_RESPONSE'}}};
      throw e;
    }
    return {data:{success:false,error:'credentials=FAKE_SECRET_APP'}};
  }
};
const source = slice("const SECRET_KEY =", "const PROJECT_LOCK_READ_ONLY_TOOLS")
  + slice("const AUDIT_READ_OPERATION", "\n\nif (!MODX_SITE_URL)")
  + slice("async function modxApiRequest", "\nfunction safeFilePart");
const context={
  console:fakeConsole, axios, AUDIT_HOOK:"/fake/audit-hook",
  SITE_ID:"test", MODX_SITE_URL:"https://invalid.test/fake",
  API_TOKEN:"FAKE_SECRET_TOKEN", lastCaps:null,
  process:{env:{}},
  spawnSync: (_hook,_args,opts)=>{ lastInput=JSON.parse(opts.input); return {status:0,stdout:hookOutput}; },
  noteCaps:()=>{}, isProjectMutationTool:()=>true,
  setTimeout, Date, JSON, Error, String, Object, Array
};
const api=vm.runInNewContext(source+"\n({modxApiRequest,runMutationAuditHook,redactText})", context);
(async()=>{
 for(const mode of ["success","http","network"]){
   responseMode=mode;
   let error=null;
   try { await api.modxApiRequest({action:"test"}); } catch (e) { error=e; }
   assert(error, "error missing for "+mode);
   assert(!error.message.includes("FAKE_SECRET_"),"leaked API error in "+mode);
   assert(error.message.includes("[REDACTED]"),"redaction missing in "+mode);
 }
 api.runMutationAuditHook("modx_update_element",{password:"FAKE_SECRET_ARG",nested:{api_key:"FAKE_SECRET_NESTED"}},{password:"FAKE_SECRET_RESULT"},[]);
 assert(lastInput, "audit hook missing");
 const serialized=JSON.stringify(lastInput);
 assert(!serialized.includes("FAKE_SECRET_"),"leaked audit hook payload");
 assert.strictEqual(lastInput.args.password,"[REDACTED]");
 hookOutput="password=FAKE_SECRET_STDOUT";
 api.runMutationAuditHook("modx_update_element",{key:"ok"},{status:"ok"},[]);
 assert(!stderr.join("\n").includes("FAKE_SECRET_"), "audit output leak");
 console.log("SECRET_REDACTION_CLIENT_INTEGRATION_OK");
})().catch(e=>{console.error(e);process.exit(1)});
