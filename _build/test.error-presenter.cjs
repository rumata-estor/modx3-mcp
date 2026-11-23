// MODX MCP SOURCE / ИСХОДНИК MODX MCP
// RU: Проверка понятных ошибок, сохранности секретов и запрета слепого повтора.
// EN: Human readable errors, secret isolation and no blind mutation retry.
"use strict";
const assert = require("assert");
const presenter = require("../client/error-presenter");
const cases = [
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
console.log("MCP_ERROR_PRESENTER_RU_OK cases=" + (cases.length + 5));
