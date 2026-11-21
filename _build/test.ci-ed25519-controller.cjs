"use strict";
// MODX MCP SOURCE / ИСХОДНИК MODX MCP
// RU: Проверка «ci-ed25519-controller»; тестовый код не запускается на production.
// EN: «ci-ed25519-controller» regression test; not executed in production.
/**
 * Disposable CI-only signing fixture. Private Ed25519 key lives in RUNNER_TEMP
 * outside the MODX webroot. Actual MODX MCP receives only signed permits.
 */
const fs=require("node:fs");
const crypto=require("node:crypto");
const path=require("node:path");
const [mode,file,site="ci-site",action="none",elementType="none"]=process.argv.slice(2);
if (!process.env.MODXMCP_DISPOSABLE_CI || process.env.MODXMCP_DISPOSABLE_CI!=="1"
    || !file || !file.startsWith("/home/runner/")) {
  throw Error("Refusing signer outside disposable GitHub CI");
}
if (mode==="generate") {
  if(fs.existsSync(file))throw Error("CI private key file already exists");
  const keys=crypto.generateKeyPairSync("ed25519");
  const privateKey=keys.privateKey.export({type:"pkcs8",format:"pem"});
  fs.writeFileSync(file,privateKey,{mode:0o600,flag:"wx"});
  const raw=keys.publicKey.export({format:"der",type:"spki"});
  if(raw.subarray(0,12).toString("hex")!=="302a300506032b6570032100")throw Error("Unexpected Ed25519 key");
  process.stdout.write(raw.subarray(12).toString("hex")+"\n");
} else if (mode==="sign") {
  const raw=fs.readFileSync(0,"utf8");
  const parsed=JSON.parse(raw);
  if(parsed.action!==action)throw Error("Action mismatch in CI signer");
  const now=Math.floor(Date.now()/1000);
  const claims={
    version:"1",audience:"ci-protected",
    site_id:site,contour:"test",action,element_type:elementType,
    request_digest:crypto.createHash("sha256").update(raw).digest("hex"),
    nonce:crypto.randomBytes(16).toString("hex"),
    issued_at:String(now-2),expires_at:String(now+80),
  };
  const fields=[
    "version","audience","site_id","contour","action","element_type",
    "request_digest","nonce","issued_at","expires_at",
  ];
  const wire="MODXMCP-INDEPENDENT-ED25519-PERMIT-V1\n"+
    fields.map(k=>k+"="+claims[k]+"\n").join("");
  const key=crypto.createPrivateKey(fs.readFileSync(file));
  const signature=crypto.sign(null,Buffer.from(wire),key).toString("hex");
  process.stdout.write(JSON.stringify({claims,signature}));
} else {
  throw Error("Unknown CI signer action");
}
