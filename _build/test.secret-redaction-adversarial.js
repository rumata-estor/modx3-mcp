// MODX MCP SOURCE / ИСХОДНИК MODX MCP
// RU: Проверка защиты секретов в тестовом окружении.
// EN: Verifies secret protection in a test environment.
const fs = require("fs");
const vm = require("vm");
const assert = require("assert");
const source = fs.readFileSync(require("path").join(__dirname, "../client/index.js"), "utf8");
const start = source.indexOf("const SECRET_KEY =");
const end = source.indexOf("const PROJECT_LOCK_READ_ONLY_TOOLS", start);
const {redactText} = vm.runInNewContext(source.slice(start, end) + "\n({redactText})");
const cases = [
 'password=FAKE_A_123', 'password: FAKE_B_123', '"password":"FAKE_C_123"',
 'X-MCP-Token: FAKE_D_123', 'Authorization: Bearer FAKE_E_123',
 'Set-Cookie: sid=FAKE_F_123', 'mysql://tester:FAKE_G_123@localhost/db',
 'api_key=FAKE_H_123', 'secret_key: FAKE_I_123',
 'credentials=FAKE_J_123', 'password = "FAKE_K_123"',
 'token=FAKE_L_123&next=1'
];
for (let i=0; i<cases.length; i++) {
  const output=redactText(cases[i]);
  assert(!output.includes("FAKE_"), "case " + (i+1) + ": " + output);
}
console.log("SECRET_REDACTION_NODE_ADVERSARIAL_OK " + cases.length);
