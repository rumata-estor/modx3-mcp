// MODX MCP SOURCE / ИСХОДНИК MODX MCP
// RU: Сырые ответы внешнего audit-hook никогда не попадают в stderr Node MCP.
// EN: Untrusted audit-hook output and exception text must never reach MCP stderr.
"use strict";
const assert = require("assert");
const fs = require("fs");
const vm = require("vm");
const path = require("path");
const source = fs.readFileSync(path.join(__dirname, "../client/index.js"), "utf8");
const start = source.indexOf("function runMutationAuditHook(");
const end = source.indexOf("\nfunction ", start + 10);
assert(start > 0 && end > start, "audit hook function source");
const captured = [];
let mode = "stdout";
const mock = {
  AUDIT_HOOK: "/disposable/mock",
  SITE_ID: "ci-disposable",
  console: { error: value => captured.push(String(value)) },
  process: { env: {} },
  shouldInvokeAuditHook: () => true,
  redactValue: v => v,
  spawnSync: () => {
    if (mode === "stdout") return { status: 0, stdout: "SILENT_RAW_HOOK_SECRET", stderr: "" };
    if (mode === "stderr") return { status: 1, stdout: "", stderr: "SILENT_RAW_HOOK_SECRET" };
    if (mode === "returned_error") return { error: new Error("SILENT_RAW_HOOK_SECRET") };
    throw new Error("SILENT_RAW_HOOK_SECRET");
  }
};
const fn = vm.runInNewContext(source.slice(start, end)
  + "\nrunMutationAuditHook", mock);
for (const testCase of ["stdout", "stderr", "returned_error", "thrown"]) {
  mode = testCase;
  fn("modx_update_element", {}, {}, []);
}
assert(captured.length >= 3, "expected safe warning messages");
assert(!captured.join("\n").includes("SILENT_RAW_HOOK_SECRET"),
  "untrusted child output leaked into MCP stderr");
const stderrLog = source.split("\n").filter(line => line.includes("console.error(")).join("\n");
assert(!stderrLog.includes("${file}"), "user-controlled backup file path logged");
assert(!stderrLog.includes("hook.stdout") && !stderrLog.includes("hook.stderr"),
  "untrusted hook output logged");
console.log("MCP_AUDIT_HOOK_OUTPUT_ISOLATION_OK");
