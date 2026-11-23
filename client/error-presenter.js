// MODX MCP SOURCE / ИСХОДНИК MODX MCP
// RU: Понятное сообщение и консервативный результат операции во всех MCP-клиентах.
// EN: Client-side diagnostics for old/new PHP servers and transport failures.
"use strict";
const crypto = require("crypto");

const SAFE_CODES = new Set([
  "AUTH_DENIED", "CAS_REQUIRED", "STALE_STATE", "MUTATION_OUTCOME_UNCERTAIN",
  "SECURE_STATIC_IO_UNAVAILABLE", "STATIC_READ_FAILED", "ACL_INDEX_REQUIRED",
  "PROJECT_BUSY", "BACKUP_FAILED", "HTTPS_REQUIRED", "METHOD_NOT_ALLOWED",
  "MCP_DISABLED", "IP_NOT_ALLOWED", "UNAUTHORIZED", "PAYLOAD_TOO_LARGE",
  "INVALID_REQUEST", "INTERNAL_ERROR", "OPERATION_REFUSED", "CONNECTION_FAILED",
  "CLIENT_OPERATION_FAILED", "CLIENT_CONFIG_INVALID", "SAFETY_CONFIRMATION_REQUIRED",
]);
const ALLOWED_OUTCOMES = new Set([
  "not_applied", "rolled_back", "partial", "unknown", "completed",
]);
const ALLOWED_RETRY = new Set([
  "none", "after_authorization", "after_refresh", "after_review",
  "after_admin_check", "after_client_fix", "verify_before_retry",
]);
const FALLBACK = {
  AUTH_DENIED: ["Операция отклонена проверкой доступа.",
    "Не удалось подтвердить разрешение на действие.",
    "Обратитесь к администратору для проверки прав.", "not_applied", "after_authorization"],
  CAS_REQUIRED: ["Для записи требуется актуальная проверка состояния.",
    "Отсутствуют обязательные CAS-предусловия.",
    "Прочитайте сайт и объект заново и передайте актуальные CAS-предусловия.",
    "not_applied", "after_refresh"],
  STALE_STATE: ["Данные изменились после предыдущего чтения.",
    "Сохранённый снимок устарел.",
    "Сравните актуальные данные и согласуйте новую запись; не затирайте чужие изменения.",
    "not_applied", "after_review"],
  MUTATION_OUTCOME_UNCERTAIN: ["Результат изменения не подтверждён.",
    "В процессе записи мог произойти побочный эффект.",
    "Не повторяйте запись. Сначала прочитайте объект и проверьте сохранённое состояние.",
    "unknown", "verify_before_retry"],
  SECURE_STATIC_IO_UNAVAILABLE: ["Безопасная файловая операция недоступна.",
    "Не выполнены требования файлового режима или разрешений.",
    "Попросите администратора проверить права и режим static I/O. Не отключайте защиту.",
    "unknown", "after_admin_check"],
  STATIC_READ_FAILED: ["Не удалось прочитать файл.",
    "Операция чтения остановлена.",
    "Проверьте существование файла и права доступа.", "not_applied", "after_admin_check"],
  PROJECT_BUSY: ["Проект занят другой операцией.",
    "Невозможно получить защитную блокировку.",
    "Завершите текущую операцию, затем получите актуальное состояние.", "not_applied", "after_refresh"],
  BACKUP_FAILED: ["Не удалось создать защитную копию.",
    "Обязательная подготовка к изменению не выполнена.",
    "Проверьте права каталога резервных копий, затем подготовьте копию заново.",
    "not_applied", "after_admin_check"],
  UNAUTHORIZED: ["Подключение не авторизовано.",
    "Сервер не принял данные доступа.",
    "Проверьте токен и настройки подключения у администратора.",
    "not_applied", "after_admin_check"],
  CONNECTION_FAILED: ["Не удалось получить ответ от MODX MCP.",
    "Соединение прервано или сервер недоступен. Результат записи не подтверждён.",
    "Проверьте доступность сервера. Если выполнялась запись, проверьте объект перед повтором.",
    "unknown", "verify_before_retry"],
  INTERNAL_ERROR: ["На сервере произошла внутренняя ошибка.",
    "Техническая причина доступна по номеру диагностики, если сервер успел её записать.",
    "Передайте номер ошибки администратору. Перед повторной записью проверьте состояние объекта.",
    "unknown", "verify_before_retry"],
  INVALID_REQUEST: ["Сервер отклонил некорректный запрос.",
    "Не удалось проверить параметры запроса.",
    "Проверьте формат и переданные поля; затем отправьте исправленный запрос.",
    "not_applied", "after_client_fix"],
  CLIENT_CONFIG_INVALID: ["Подключение к MODX MCP не настроено.",
    "Не задан HTTPS-адрес сайта, токен подключения или адрес имеет неверный формат.",
    "Проверьте MODX_MCP_SITE_URL и MODX_MCP_TOKEN в настройках MCP-клиента. Не передавайте токен в чат.",
    "not_applied", "after_client_fix"],
  CLIENT_OPERATION_FAILED: ["Клиент MODX MCP не смог выполнить команду.",
    "На стороне клиента возникла ошибка подготовки или обработки.",
    "Передайте номер диагностики разработчику. Перед повтором записи проверьте объект.",
    "unknown", "verify_before_retry"],
  SAFETY_CONFIRMATION_REQUIRED: ["Операция требует безопасного подтверждения.",
    "Клиент остановил действие до обязательного подтверждения.",
    "Используйте предусмотренный безопасный сценарий после согласования.",
    "not_applied", "after_review"],
  OPERATION_REFUSED: ["MODX MCP не смог выполнить операцию.",
    "Для определения причины нужна запись по номеру диагностики.",
    "Передайте номер ошибки разработчику. Перед повторной записью проверьте объект.",
    "unknown", "verify_before_retry"],
};
const RESULT = {
  not_applied: "Изменения не внесены.",
  rolled_back: "Выполненные изменения отменены; откат подтверждён.",
  partial: "Часть изменений могла сохраниться. Требуется проверка.",
  unknown: "Не подтверждено, были ли внесены изменения.",
  completed: "Сервер подтвердил завершение операции.",
};
const RETRY = {
  none: "Не повторяйте без устранения причины.",
  after_authorization: "Повтор возможен после проверки разрешения.",
  after_refresh: "Повтор возможен после чтения актуального состояния.",
  after_review: "Повтор возможен после проверки и согласования изменений.",
  after_admin_check: "Повтор возможен после проверки администратором.",
  after_client_fix: "Повтор возможен после исправления параметров.",
  verify_before_retry: "Автоматический повтор запрещён до проверки результата.",
};

function newId() {
  return "mcpnode_" + crypto.randomBytes(12).toString("hex");
}
function safeCode(raw) {
  return typeof raw === "string" && SAFE_CODES.has(raw) ? raw : null;
}
function legacyCode(text) {
  if (typeof text !== "string") return null;
  const match = /^([A-Z][A-Z0-9_]{2,63}):/.exec(text);
  if (match) {
    if (match[1] === "CAS_CONFLICT" || match[1] === "STALE_REVISION") return "STALE_STATE";
    return safeCode(match[1]);
  }
  return null;
}
function safeId(id) {
  return typeof id === "string" && /^(?:modxmcp|mcpnode)_[a-f0-9]{16,64}$/.test(id)
    ? id : newId();
}
function safeOperation(operation) {
  return typeof operation === "string" && /^[a-z][a-z0-9_]{0,79}$/.test(operation)
    ? operation : "unknown";
}
function record(code, operation, options = {}) {
  code = safeCode(code) || "OPERATION_REFUSED";
  const base = FALLBACK[code] || FALLBACK.OPERATION_REFUSED;
  const trusted = options.structured === true;
  // Only accept these fields from the structured, trusted server-side catalog;
  // never echo a third-party PHP exception or raw HTTP response text.
  const text = (key, fallback) => trusted && typeof options[key] === "string"
    && options[key].length > 0 && options[key].length <= 800
      ? options[key] : fallback;
  const outcome = trusted && ALLOWED_OUTCOMES.has(options.outcome)
    ? options.outcome : base[3];
  const retry = trusted && ALLOWED_RETRY.has(options.retry_policy)
    ? options.retry_policy : base[4];
  return {
    code, error_id: safeId(options.error_id),
    operation: safeOperation(operation),
    message_ru: text("message_ru", base[0]),
    reason_ru: text("reason_ru", base[1]),
    next_action_ru: text("next_action_ru", base[2]),
    outcome, retry_policy: retry,
    stage: options.stage === "network" ? "network" :
      options.stage === "client" ? "client" : "http",
    http_status: Number.isInteger(options.http_status) && options.http_status >= 100
      && options.http_status <= 599 ? options.http_status : null,
    network_code: /^[A-Z0-9_]{2,32}$/.test(String(options.network_code || ""))
      ? options.network_code : null,
  };
}
function fromHttp(body, status, operation) {
  const payload = body && typeof body === "object" && !Array.isArray(body) ? body : {};
  const code = safeCode(payload.code) || legacyCode(payload.error)
    || (status >= 500 ? "INTERNAL_ERROR" : "OPERATION_REFUSED");
  return record(code, operation, {
    ...payload, structured: safeCode(payload.code) !== null,
    http_status: status, stage: "http",
  });
}
function fromNetwork(error, operation, isReadOnly) {
  const result = record("CONNECTION_FAILED", operation, {
    stage: "network", network_code: error && error.code,
  });
  if (isReadOnly) {
    result.outcome = "not_applied";
    result.reason_ru = "Не удалось получить ответ сервера. Запись не выполнялась, поскольку это операция чтения.";
    result.next_action_ru = "Проверьте доступность сайта и повторите чтение после восстановления соединения.";
    result.retry_policy = "after_admin_check";
  }
  return result;
}
function fromLocal(error, operation, isReadOnly) {
  const message = String(error && error.message || "");
  let code = legacyCode(message);
  if (!code && /^PROJECT BUSY:/.test(message)) code = "PROJECT_BUSY";
  if (!code && /^Safety backup failed:/.test(message)) code = "BACKUP_FAILED";
  if (!code && /^Safety:/.test(message)) code = "SAFETY_CONFIRMATION_REQUIRED";
  const result = record(code || "CLIENT_OPERATION_FAILED", operation, { stage: "client" });
  if (isReadOnly && result.outcome === "unknown") result.outcome = "not_applied";
  return result;
}
function display(result) {
  return [
    result.message_ru,
    "Операция: " + result.operation + ".",
    "Причина: " + result.reason_ru,
    "Результат: " + RESULT[result.outcome],
    "Что делать: " + result.next_action_ru,
    "Повтор: " + RETRY[result.retry_policy],
    "Код: " + result.code,
    "Номер диагностики: " + result.error_id,
  ].join("\n");
}
function debugLine(result) {
  // Do not log raw exception messages, request payload, credentials or URLs.
  return "[" + result.error_id + "] modxMCP error code=" + result.code
    + " operation=" + result.operation + " stage=" + result.stage
    + " http_status=" + (result.http_status || "-")
    + " network_code=" + (result.network_code || "-")
    + " outcome=" + result.outcome;
}

module.exports = { fromHttp, fromNetwork, fromLocal, display, debugLine, newId };
