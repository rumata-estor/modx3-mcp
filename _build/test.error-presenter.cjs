// MODX MCP SOURCE / ИСХОДНИК MODX MCP
// RU: Проверка понятных ошибок, сохранности секретов и запрета слепого повтора.
// EN: Human readable errors, secret isolation and no blind mutation retry.
"use strict";
const assert = require("assert");
const presenter = require("../client/error-presenter");
const cases = [
  { code: "TV_RENDERING_DISABLED", outcome: "not_applied", retry_policy: "after_admin_check" },
  { code: "AUTH_DENIED", outcome: "not_applied", retry_policy: "after_authorization" },
  { code: "MANUAL_REVIEW_REQUIRED", outcome: "not_applied", retry_policy: "after_review" },
  { code: "CAS_REQUIRED", outcome: "not_applied", retry_policy: "after_refresh" },
  { code: "STALE_STATE", outcome: "not_applied", retry_policy: "after_review" },
  { code: "MUTATION_OUTCOME_UNCERTAIN", outcome: "unknown",
    retry_policy: "verify_before_retry" },
];
for (const c of cases) {
  const result = presenter.fromHttp({
    success: false, error: c.code + ": NEVER_SHOW_PASSWORD=SUPER_SECRET",
    code: c.code, outcome: c.outcome, retry_policy: c.retry_policy,
    message_ru: "Запрос остановлен.", reason_ru: "Проверка операции не пройдена.",
    next_action_ru: "Перечитайте объект до повторного изменения.",
    error_id: "modxmcp_1234567890abcdef12345678",
  }, 400, "update_element");
  assert.strictEqual(result.code, c.code);
  assert.strictEqual(result.outcome, c.outcome);
  assert.strictEqual(result.retry_policy, c.retry_policy);
  assert.strictEqual(result.error_id, "modxmcp_1234567890abcdef12345678");
  const visible = presenter.display(result);
  assert(visible.includes("Что делать:"));
  assert(visible.includes("Номер диагностики:"));
  assert(!visible.includes("SUPER_SECRET"), "server exception must not be printed");
  assert(!presenter.debugLine(result).includes("SUPER_SECRET"));
}
const legacy = presenter.fromHttp(
  { error: "CAS_REQUIRED: token=SUPER_SECRET" }, 400, "update_element");
assert.strictEqual(legacy.code, "CAS_REQUIRED");
assert.strictEqual(legacy.outcome, "not_applied");
assert(!presenter.display(legacy).includes("SUPER_SECRET"));
const unknown = presenter.fromHttp(
  { error: "Unexpected password=SUPER_SECRET", error_id: "unsafe\nsecret" },
  500, "update_element");
assert.strictEqual(unknown.code, "INTERNAL_ERROR");
assert(!presenter.display(unknown).includes("SUPER_SECRET"));
assert(unknown.error_id.startsWith("mcpnode_"));
const timedOut = presenter.fromNetwork(
  { code: "ECONNRESET", message: "Authorization=SUPER_SECRET" }, "update_element", false);
assert.strictEqual(timedOut.outcome, "unknown");
assert.strictEqual(timedOut.retry_policy, "verify_before_retry");
assert(presenter.display(timedOut).includes("Автоматический повтор запрещён"));
assert(!presenter.debugLine(timedOut).includes("SUPER_SECRET"));
const readFailed = presenter.fromNetwork({ code: "ETIMEDOUT" }, "get_element", true);
assert.strictEqual(readFailed.outcome, "not_applied");
const readHttp = presenter.fromHttp(
  { code: "INTERNAL_ERROR", error: "Internal Server Error" },
  500, "get_element", true);
assert.strictEqual(readHttp.outcome, "not_applied");
assert.strictEqual(readHttp.retry_policy, "after_admin_check");
const config = presenter.fromLocal(
  { message: "CLIENT_CONFIG_INVALID: missing_token" }, "update_element", false);
assert.strictEqual(config.code, "CLIENT_CONFIG_INVALID");
assert.strictEqual(config.outcome, "not_applied");
const backup = presenter.fromLocal(
  { message: "Safety backup failed: password=SUPER_SECRET" }, "update_element", false);
assert.strictEqual(backup.code, "BACKUP_FAILED");
assert(!presenter.display(backup).includes("SUPER_SECRET"));
// All actual local gate branches use explicit, fixed, non-secret guidance.
const fs = require("fs");
const source = fs.readFileSync(require.resolve("../client/index.js"), "utf8");
const policySource = fs.readFileSync(require.resolve("../client/safety-policy.js"), "utf8");
const refs = [...policySource.matchAll(/id: "([a-z_]+)"/g)].map(m=>m[1]);
assert.strictEqual(new Set(refs).size,20);
const toolNames = new Set([...source.matchAll(/name: "(modx_[a-z_]+)"/g)].map(m=>m[1]));
for (const gate of refs) {
  const result = presenter.safetyRefusal(gate,"update_element");
  assert.strictEqual(result.code,"SAFETY_CONFIRMATION_REQUIRED");
  assert.strictEqual(result.outcome,"not_applied");
  assert.strictEqual(result.retry_policy,"after_review");
  assert(result.reason_ru.includes("локальная защитная политика"));
  assert(result.next_action_ru.includes("администратор"));
  const names = result.next_action_ru.match(/modx_[a-z_]+/g) || [];
  assert(names.length > 0 && names.every(n=>toolNames.has(n)),gate);
  assert(!presenter.debugLine(result).includes("MODX_MCP_ALLOW"));
}
assert(!source.includes("Use the safe "));
const policy = require("../client/safety-policy");
assert.strictEqual(policy.deniedGate("modx_update_element",{type:"plugin",events:[]},{}),"plugin_events");
assert.strictEqual(policy.deniedGate("modx_update_element",{type:"plugin",content:"x"},{}),null);
assert.strictEqual(policy.deniedGate("modx_delete_element",{type:"tv",dry_run:true},{}),null);
assert.strictEqual(policy.deniedGate("modx_delete_element",{type:"tv"},{}),"tv_delete");
assert.strictEqual(policy.deniedGate("modx_bulk_resources",{operation:"delete",dry_run:true},{}),null);
assert.strictEqual(policy.deniedGate("modx_bulk_resources",{operation:"delete"},{MODX_MCP_ALLOW_STAGED_BULK_RESOURCE_DELETE:"1"}),null);
assert.strictEqual(policy.deniedGate("modx_empty_recycle_bin",{},{MODX_MCP_ALLOW_RECYCLE_PURGE:"1"}),null);
assert.strictEqual(policy.deniedGate("modx_create_property_set",{},{MODX_MCP_ALLOW_SAFE_PROPERTY_SET:"0"}),"property_set");
assert.strictEqual(policy.deniedGate("modx_create_property_set",{},{MODX_MCP_ALLOW_SAFE_PROPERTY_SET:"1"}),null);
assert.strictEqual(policy.deniedGate("modx_create_property_set",{},{MODX_MCP_SKIP_AUTO_BACKUP:"1"}),null);
const t={name:"modx_delete_element",description:"Delete element.",inputSchema:{}};
assert(policy.describeTool(t,{}).description.includes("TV deletion (not preview)"));
assert(!policy.describeTool(t,{}).description.includes("MODX_MCP_ALLOW"));
assert.strictEqual(t.description,"Delete element.");
assert.strictEqual(policy.describeTool({name:"modx_get_element",description:"Read"},{}).description,"Read");
assert(source.includes("SafetyPolicy.describeTool(t)"));
assert(source.indexOf("SafetyPolicy.deniedGate(name, args)") < source.indexOf("return [await autoBackupElement(args.type, args, name)]"));
for (const m of policySource.matchAll(/id: "([a-z_]+)", flag: "([A-Z_]+)", names: (\[[^\]]+\])/g)) {
  const names=JSON.parse(m[3]);
  for(const name of names) {
    assert(toolNames.has(name),name);
    const tool={name,description:"test",inputSchema:{}};
    assert(policy.describeTool(tool,{}).description.includes("Local client policy"));
  }
}

const invalid = presenter.fromHttp({error:"VALIDATION_ERROR: secret=SUPER_SECRET"},400,"create_property_set");
assert.strictEqual(invalid.code,"VALIDATION_ERROR");
assert.strictEqual(invalid.outcome,"not_applied");
assert(!presenter.display(invalid).includes("SUPER_SECRET"));
const tpl=presenter.fromHttp({error:"MUTATION_OUTCOME_UNCERTAIN: MEDIA_TPL_WRITE_REFUSED: password=SUPER_SECRET"},400,"create_media_file");
assert.strictEqual(tpl.code,"MUTATION_OUTCOME_UNCERTAIN");
assert.strictEqual(tpl.detail_code,"MEDIA_TPL_WRITE_REFUSED");
assert.strictEqual(tpl.outcome,"unknown");
assert.strictEqual(tpl.retry_policy,"verify_before_retry");
assert(tpl.reason_ru.includes("upload_files"));
assert(!presenter.display(tpl).includes("SUPER_SECRET"));
const unknownGate=presenter.safetyRefusal("password=SUPER_SECRET","update_element");
assert(!presenter.display(unknownGate).includes("SUPER_SECRET"));
console.log("MCP_ERROR_PRESENTER_RU_OK gates="+refs.length+" cases="+(cases.length+5));
