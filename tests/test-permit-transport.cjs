"use strict";
const assert = require("node:assert/strict");
const { prepareRequest, encodeTrustedPermit, sendPrepared } = require("../client/permit-transport");
(async () => {
  const raw = { action: "update_element", type: "chunk", data: { id: 7, content: "new" } };
  const prepared = prepareRequest(raw);
  const signed = { claims: {
    action: "update_element", request_digest: prepared.digest,
    site_id: "site-one", nonce: "0".repeat(32),
  }, mac: "dummy-fixture" };
  const header = encodeTrustedPermit(prepared, signed);
  assert.equal(JSON.parse(Buffer.from(header, "base64").toString()).claims.request_digest, prepared.digest);
  assert.throws(() => encodeTrustedPermit({...prepared, body: prepared.body + " "}, signed));
  assert.throws(() => encodeTrustedPermit(prepared, {...signed, claims: {...signed.claims, action: "delete_element"}}));
  let sent = null;
  const mock = { post: async (url, body, opts) => {
    sent = {url,body,opts}; return { data: { success: true } };
  }};
  await sendPrepared(mock, "https://example.test/api", "test-only", prepared, signed);
  assert.equal(sent.body, JSON.stringify(raw));
  assert.equal(sent.opts.headers["X-SiteAgent-Permit"], header);
  assert.equal(sent.opts.maxRedirects, 0);
  assert.equal(sent.opts.transformRequest[0](sent.body), sent.body);
  await assert.rejects(sendPrepared(mock, "http://example.test/api", "test-only", prepared, signed));
  console.log("MODX_SIGNED_TRANSPORT_OK");
})().catch(e => {console.error(e);process.exitCode=1});
