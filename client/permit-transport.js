"use strict";
/**
 * Exact-byte signed request transport for a future isolated Executor process.
 * This is NOT wired into the ordinary agent MCP client: only a trusted
 * Executor may receive a one-use signed permit from Task Control.
 */
const crypto = require("crypto");

function prepareRequest(payload) {
  if (!payload || typeof payload !== "object" || Array.isArray(payload) ||
      typeof payload.action !== "string" || !payload.action) {
    throw new Error("invalid MODX action payload");
  }
  const body = JSON.stringify(payload);
  const digest = crypto.createHash("sha256").update(Buffer.from(body, "utf8")).digest("hex");
  return Object.freeze({ body, digest, action: payload.action });
}

function encodeTrustedPermit(prepared, signed) {
  if (!prepared || typeof prepared.body !== "string" ||
      crypto.createHash("sha256").update(prepared.body, "utf8").digest("hex") !== prepared.digest ||
      !signed || !signed.claims || typeof signed.mac !== "string" ||
      signed.claims.request_digest !== prepared.digest ||
      signed.claims.action !== prepared.action) {
    throw new Error("signed permit does not match the exact prepared request");
  }
  return Buffer.from(JSON.stringify(signed), "utf8").toString("base64");
}

async function sendPrepared(axiosClient, url, apiToken, prepared, signed) {
  if (typeof url !== "string" || !url.startsWith("https://") ||
      typeof apiToken !== "string" || !apiToken) {
    throw new Error("trusted endpoint and service credential required");
  }
  const permitHeader = encodeTrustedPermit(prepared, signed);
  // Axios must receive raw JSON bytes, not reserialize the object after
  // the controller signed request_digest. Never retry a mutation automatically.
  return axiosClient.post(url, prepared.body, {
    headers: {
      "X-MCP-Token": apiToken,
      "X-SiteAgent-Permit": permitHeader,
      "Content-Type": "application/json; charset=utf-8",
    },
    transformRequest: [(body) => body],
    maxRedirects: 0,
  });
}

module.exports = { prepareRequest, encodeTrustedPermit, sendPrepared };
