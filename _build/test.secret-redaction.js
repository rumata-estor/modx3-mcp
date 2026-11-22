// MODX MCP SOURCE / ИСХОДНИК MODX MCP
// RU: Проверка защиты секретов в тестовом окружении.
// EN: Verifies secret protection in a test environment.
const fs = require("fs");
const vm = require("vm");
const assert = require("assert");
const source = fs.readFileSync(require("path").join(__dirname, "../client/index.js"), "utf8");
const start = source.indexOf("const SECRET_KEY =");
const end = source.indexOf("const PROJECT_LOCK_READ_ONLY_TOOLS", start);
assert(start > 0 && end > start);
const { redactValue, redactText } = vm.runInNewContext(source.slice(start, end) + "\n({redactValue,redactText})");
const value = redactValue({password: "fake-value-a", nested: {api_token: "fake-value-b", message: "password=fake-value-c"}, status:"failed"});
assert.strictEqual(value.password,"[REDACTED]");
assert.strictEqual(value.nested.api_token,"[REDACTED]");
assert(!value.nested.message.includes("fake-value-c"));
assert.strictEqual(value.status,"failed");
for (const item of ["Bearer fake-token-abc", "Basic ZmFrZQ==", "https://bob:fakepass@example.test/"]) {
 assert(!redactText(item).includes("fake"), item);
}
console.log("SECRET_REDACTION_NODE_OK");
