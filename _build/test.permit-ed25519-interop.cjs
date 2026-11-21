"use strict";
// MODX MCP SOURCE / ИСХОДНИК MODX MCP
// RU: Проверка «permit-ed25519-interop»; тестовый код не запускается на production.
// EN: «permit-ed25519-interop» regression test; not executed in production.
/*
 * Test-only external signer. No signing key is stored in the MODX connector.
 * The PHP verifier receives ONLY the detached signature and public key.
 */
const crypto=require("node:crypto");
const {spawnSync}=require("node:child_process");
const path=require("node:path");
const fields=[
  "version","audience","site_id","contour","action","element_type",
  "request_digest","nonce","issued_at","expires_at",
];
const now=Math.floor(Date.now()/1000);
const requestBody=JSON.stringify({
  action:"update_element",
  type:"chunk",
  data:{id:7,content:"New contents",_runtime_preconditions:{
    observed_site_revision:123,strict_site_revision:true,read_set:[],
  }},
});
const digest=crypto.createHash("sha256").update(requestBody).digest("hex");
const claims={
  version:"1",audience:"ci-review-only",site_id:"temporary-modx3",
  contour:"test",action:"update_element",element_type:"chunk",
  request_digest:digest,nonce:crypto.randomBytes(16).toString("hex"),
  issued_at:String(now-3),expires_at:String(now+60),
};
const wire="MODXMCP-INDEPENDENT-ED25519-PERMIT-V1\n"
  +fields.map(k=>k+"="+claims[k]+"\n").join("");
const keys=crypto.generateKeyPairSync("ed25519");
const signature=crypto.sign(null,Buffer.from(wire,"utf8"),keys.privateKey).toString("hex");
const exported=keys.publicKey.export({format:"der",type:"spki"});
const prefix="302a300506032b6570032100";
if(exported.subarray(0,12).toString("hex")!==prefix)throw Error("Unexpected Ed25519 SPKI");
const publicKey=exported.subarray(12).toString("hex");
const input=JSON.stringify({
  claims,signature,public_key:publicKey,request_digest:digest,
  trusted:{audience:"ci-review-only",site_id:"temporary-modx3",contour:"test"},
  now,
});
const php=process.env.MODXMCP_PHP_BINARY||"php";
const checker=path.join(__dirname,"test.permit-ed25519-verify.php");
const result=spawnSync(php,[checker],{input,encoding:"utf8",maxBuffer:512*1024});
if(result.status!==0)throw Error("Ed25519 cross-language verification failed: "
  +result.stderr+" "+result.stdout);
if(!result.stdout.includes("ED25519_INDEPENDENT_PERMIT_CRYPTO_OK checks=14"))
  throw Error("Unexpected verifier output: "+result.stdout);
process.stdout.write(result.stdout);
