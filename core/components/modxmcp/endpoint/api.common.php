<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Общий HTTP endpoint: проверка HTTPS, IP, токена и входного JSON.
 * EN: Shared HTTP endpoint: HTTPS, IP, token and input JSON validation.
 */
/**
 * Platform-neutral HTTP endpoint body.
 *
 * The platform wrapper must initialize $modx and set $modxmcpVariant before
 * including this file. Security semantics are deliberately identical on MODX 2/3.
 */
if (!isset($modx) || !is_object($modx)) {
    http_response_code(500);
    echo json_encode(array('success' => false, 'error' => 'MODX bootstrap missing'));
    exit;
}
if (!isset($modxmcpVariant) || $modxmcpVariant === '') {
    $modxmcpVariant = 'unknown';
}
if (!class_exists('ModxMCPClientException')) {
    class ModxMCPClientException extends Exception {}
}
$modxmcpClass = get_class($modx);
$modxmcpLogConstant = $modxmcpClass . '::LOG_LEVEL_ERROR';
$modxmcpLogLevelError = defined($modxmcpLogConstant) ? constant($modxmcpLogConstant) : 3;
$modx->setLogLevel($modxmcpLogLevelError);
require_once dirname(__DIR__) . '/src/Security/SecretRedactor.php';
require_once dirname(__DIR__) . '/src/Diagnostics/ErrorCatalog.php';

header('Content-Type: application/json; charset=utf-8');

// HTTPS enforcement applies to the entire endpoint, including the unauthenticated health GET.
// X-Forwarded-Proto is NOT trusted unless modxmcp.trust_proxy_https is explicitly enabled;
// otherwise a direct client could spoof that header.
if ((bool) $modx->getOption('modxmcp.require_https', null, true)) {
    $directHttps = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    $trustProxyHttps = (bool) $modx->getOption('modxmcp.trust_proxy_https', null, false);
    // A bare X-Forwarded-Proto is client-controlled. Accept it ONLY from an
    // explicitly configured socket peer; empty proxy allowlist denies all.
    // Use exact numeric IPv4/IPv6 addresses, not Host/DNS/CIDR guesses.
    $peer = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    $trustedProxyCsv = (string) $modx->getOption('modxmcp.trusted_proxy_ips', null, '');
    $trustedPeer = false;
    if ($trustProxyHttps && $peer !== '' && filter_var($peer, FILTER_VALIDATE_IP)) {
        foreach (explode(',', $trustedProxyCsv) as $rule) {
            $rule = trim($rule);
            if ($rule !== '' && filter_var($rule, FILTER_VALIDATE_IP)
                && hash_equals($rule, $peer)) {
                $trustedPeer = true;
                break;
            }
        }
    }
    $forwardedHttps = $trustProxyHttps && $trustedPeer
        && isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
        && strtolower(trim((string) $_SERVER['HTTP_X_FORWARDED_PROTO'])) === 'https';
    $isHttps = $directHttps || $forwardedHttps;
    if (!$isHttps) {
        \ModxMcp\Diagnostics\ErrorCatalog::send($modx, 403, 'HTTPS required (modxmcp.require_https).', 'HTTPS_REQUIRED');
    }
}

// Lightweight unauthenticated health/version probe (GET) for client/server skew detection.
// Returns only non-sensitive info: component name, server build version, enabled flag.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $version = 'unknown';
    $corePath = $modx->getOption('modxmcp.core_path', null, $modx->getOption('core_path') . 'components/modxmcp/');
    $corePath = str_replace(
        array('{core_path}', '[[++core_path]]'),
        rtrim((string) $modx->getOption('core_path'), '/\\') . DIRECTORY_SEPARATOR,
        (string) $corePath
    );
    $modelFile = $corePath . 'model/modxmcp.class.php';
    if (file_exists($modelFile)) {
        require_once $modelFile;
        if (defined('modxMCP::VERSION')) { $version = modxMCP::VERSION; }
    }
    echo json_encode([
        'component' => 'modxMCP',
        'version'   => $version,
        'variant'   => (defined('modxMCP::VARIANT') ? modxMCP::VARIANT : $modxmcpVariant),
        'enabled'   => (bool) $modx->getOption('modxmcp.enabled', null, false),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    \ModxMcp\Diagnostics\ErrorCatalog::send($modx, 405, 'Method Not Allowed', 'METHOD_NOT_ALLOWED');
}

$isEnabled = $modx->getOption('modxmcp.enabled', null, false);
if (!$isEnabled) {
    \ModxMcp\Diagnostics\ErrorCatalog::send($modx, 403, 'modxMCP is disabled.', 'MCP_DISABLED');
}

// Optional client-IP allowlist (modxmcp.allowed_ips). Empty = allow all. CSV of exact
// IPs and/or IPv4 CIDR ranges (e.g. "203.0.113.4, 10.0.0.0/8"). Matched against REMOTE_ADDR
// (the real socket peer) — X-Forwarded-For is intentionally NOT trusted (spoofable).
$allowedIps = trim((string) $modx->getOption('modxmcp.allowed_ips', null, ''));
if ($allowedIps !== '') {
    $clientIp = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    $ipAllowed = false;
    foreach (explode(',', $allowedIps) as $rule) {
        $rule = trim($rule);
        if ($rule === '') { continue; }
        if (strpos($rule, '/') === false) {
            if ($clientIp !== '' && $clientIp === $rule) { $ipAllowed = true; break; }
            continue;
        }
        list($subnet, $bitsText) = array_pad(explode('/', $rule, 2), 2, '');
        // Never coerce malformed masks to zero (which would match all IPv4 peers).
        if (!preg_match('/\\A(?:0|[1-9][0-9]?)\\z/D', $bitsText)) { continue; }
        $bits = (int) $bitsText;
        if ($bits > 32) { continue; }
        $ipLong = ip2long($clientIp);
        $subLong = ip2long($subnet);
        if ($ipLong === false || $subLong === false) { continue; }
        $mask = ($bits === 0) ? 0 : (~0 << (32 - $bits));
        if (($ipLong & $mask) === ($subLong & $mask)) { $ipAllowed = true; break; }
    }
    if (!$ipAllowed) {
        \ModxMcp\Diagnostics\ErrorCatalog::send($modx, 403, 'Forbidden: client IP is not allowed (modxmcp.allowed_ips).', 'IP_NOT_ALLOWED');
    }
}

$expectedToken = $modx->getOption('modxmcp.api_token', null, '');
$headers =[];
if (function_exists('getallheaders')) {
    $headers = array_change_key_case(getallheaders(), CASE_LOWER);
}

$receivedToken = '';
if (isset($headers['x-mcp-token'])) $receivedToken = trim($headers['x-mcp-token']);
elseif (isset($_SERVER['HTTP_X_MCP_TOKEN'])) $receivedToken = trim($_SERVER['HTTP_X_MCP_TOKEN']);

if (empty($expectedToken) || !hash_equals($expectedToken, $receivedToken)) {
    \ModxMcp\Diagnostics\ErrorCatalog::send($modx, 401, 'Unauthorized', 'UNAUTHORIZED');
}

$maxPayloadBytes = (int)$modx->getOption('modxmcp.max_payload_bytes', null, 1024 * 1024);
$contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
if ($maxPayloadBytes > 0 && $contentLength > $maxPayloadBytes) {
    \ModxMcp\Diagnostics\ErrorCatalog::send($modx, 413, 'Payload Too Large', 'PAYLOAD_TOO_LARGE');
}

$rawInput = file_get_contents('php://input');
if ($rawInput === false) {
    \ModxMcp\Diagnostics\ErrorCatalog::send($modx, 400, 'Could not read request body', 'INVALID_REQUEST');
}
if ($maxPayloadBytes > 0 && strlen($rawInput) > $maxPayloadBytes) {
    http_response_code(413);
    echo json_encode([
        'success' => false,
        'error' => 'Payload Too Large',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    \ModxMcp\Diagnostics\ErrorCatalog::send($modx, 400, 'Invalid JSON encoding. Ensure your payload is strictly UTF-8 encoded.', 'INVALID_REQUEST');
}

if (!$input || !isset($input['action'])) {
    \ModxMcp\Diagnostics\ErrorCatalog::send($modx, 400, 'Bad Request: Missing action.', 'INVALID_REQUEST');
}

// RU: Неизвестные ключи верхнего уровня опасно игнорировать: клиент может
// RU: ошибочно передать params вместо data и получить ложный успех записи.
// EN: Reject unknown top-level keys: silently ignoring `params` instead of
// EN: `data` can make a successful HTTP response conceal a no-op mutation.
$allowedRootKeys = array('action', 'type', 'data', 'name', 'content', 'id');
if (!is_array($input) || array_keys($input) === range(0, count($input) - 1)) {
    \ModxMcp\Diagnostics\ErrorCatalog::send($modx, 400, 'Bad Request: expected JSON object.', 'INVALID_REQUEST');
}
$unknownRootKeys = array_diff(array_keys($input), $allowedRootKeys);
if (!empty($unknownRootKeys)) {
    \ModxMcp\Diagnostics\ErrorCatalog::send($modx, 400,
        'Bad Request: unknown top-level field(s): '
            . implode(', ', array_slice($unknownRootKeys, 0, 5))
            . '. Send action parameters in data.', 'INVALID_REQUEST');
}

$action = $input['action'];
$type   = isset($input['type']) ? $input['type'] : '';
$data   = isset($input['data']) ? $input['data'] : [];

if (isset($input['name'])) $data['name'] = $input['name'];
if (isset($input['content'])) $data['content'] = $input['content'];
if (isset($input['id'])) $data['id'] = $input['id'];

try {
    $corePath = $modx->getOption('modxmcp.core_path', null, $modx->getOption('core_path') . 'components/modxmcp/');
    $corePath = str_replace(
        array('{core_path}', '[[++core_path]]'),
        rtrim((string) $modx->getOption('core_path'), '/\\') . DIRECTORY_SEPARATOR,
        (string) $corePath
    );
    require_once $corePath . 'model/modxmcp.class.php';

    $mcp = new modxMCP($modx);
    // The signed envelope is delivered in a separate HTTP header. Never
    // accept a user-controlled public key, audience or signing secret in
    // JSON. All writes are independently validated in processRequest().
    $authorizationInput = null;
    $encodedPermit = isset($headers['x-modxmcp-permit'])
        ? $headers['x-modxmcp-permit']
        : (isset($_SERVER['HTTP_X_MODXMCP_PERMIT'])
            ? $_SERVER['HTTP_X_MODXMCP_PERMIT'] : null);
    if (is_string($encodedPermit) && $encodedPermit !== '') {
        if (strlen($encodedPermit) > 4096) {
            throw new ModxMCPClientException('AUTH_DENIED: invalid permit header.');
        }
        $decodedPermit = base64_decode($encodedPermit, true);
        $permit = $decodedPermit !== false ? json_decode($decodedPermit,true) : null;
        if (!is_array($permit)) {
            throw new ModxMCPClientException('AUTH_DENIED: invalid permit envelope.');
        }
        $authorizationInput = array(
            'raw_body' => $rawInput,
            'permit' => $permit,
        );
    }
    $result = $mcp->processRequest($action, $type, $data, $authorizationInput);

    // 'caps' = capability fingerprint; the client watches it to live-refresh its tool list.
    $caps = (string) $modx->getOption('modxmcp.disabled_groups', null, '');
    // RU: Успех = ответ обработчика; повторное чтение не подразумевается.
    // EN: API acknowledgment does not imply independent readback verification.
    $encoded = json_encode(['success' => true, 'data' => $result, 'caps' => $caps,
        'outcome' => 'completed',
        'operation_code' => 'OPERATION_COMPLETED',
        'message_ru' => 'MODX подтвердил выполнение запроса. Повторная проверка объекта не выполнялась.',
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if (!is_string($encoded)) {
        // RU: Успешная мутация могла выполниться до сбоя сериализации.
        // EN: A mutation may already be applied before serialization fails.
        throw new \RuntimeException('MCP response serialization failed');
    }
    echo $encoded;
} catch (ModxMCPClientException $e) {
    // Authorization failures are 403, never confused with routine input errors.
    // Do not reveal whether a signature, site, key or nonce was wrong.
    $message = $e->getMessage();
    $authDenied = strpos($message, 'AUTH_DENIED:') === 0;
    http_response_code($authDenied ? 403 : 400);
    $caps = (string) $modx->getOption('modxmcp.disabled_groups', null, '');
    $fields = \ModxMcp\Diagnostics\ErrorCatalog::fields($message, null, null, $e);
    // RU: Корреляция без исходных параметров, исключений и секретов.
    // EN: Correlation without raw parameters, exception text or credentials.
    $safeAction = is_string($action) ? preg_replace('/[^a-zA-Z0-9_-]/', '_', substr($action, 0, 80)) : 'invalid';
    \ModxMcp\Diagnostics\ErrorCatalog::safeLog($modx, $modxmcpLogLevelError,
        sprintf('[%s] MCP request rejected. code=%s action=%s',
            $fields['error_id'], $fields['code'], $safeAction));
    echo json_encode(array_merge(['success' => false,
        'error' => \ModxMcp\Security\SecretRedactor::text($message),
        'caps' => $caps], $fields), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Exception $e) {
    $errorId = \ModxMcp\Diagnostics\ErrorCatalog::newId();
    \ModxMcp\Diagnostics\ErrorCatalog::safeLog(
        $modx, $modxmcpLogLevelError,
        sprintf(
            '[%s] MCP request failed. action=%s type=%s exception_class=%s',
            $errorId,
            is_string($action) ? preg_replace('/[^a-zA-Z0-9_-]/', '_', substr($action, 0, 80)) : 'invalid',
            is_string($type) ? preg_replace('/[^a-zA-Z0-9_-]/', '_', substr($type, 0, 80)) : 'invalid',
            get_class($e)
        )
    );
    http_response_code(500);
    // RU: Даже при debug=1 нельзя отдавать исходный текст исключения:
    // RU: сторонние процессоры могут включать в него временные пароли.
    // EN: Even in debug mode, never expose raw exception messages that
    // EN: may contain paths, credentials or third-party processor output.
    $response = [
        'success' => false,
        'error' => 'Internal Server Error',
        'error_id' => $errorId,
    ];
    $response = array_merge($response,
        \ModxMcp\Diagnostics\ErrorCatalog::fields('Internal Server Error',
            'INTERNAL_ERROR', $errorId));
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    $errorId = \ModxMcp\Diagnostics\ErrorCatalog::newId();
    \ModxMcp\Diagnostics\ErrorCatalog::safeLog(
        $modx, $modxmcpLogLevelError,
        sprintf(
            '[%s] MCP request failed. action=%s type=%s exception_class=%s',
            $errorId,
            is_string($action) ? preg_replace('/[^a-zA-Z0-9_-]/', '_', substr($action, 0, 80)) : 'invalid',
            is_string($type) ? preg_replace('/[^a-zA-Z0-9_-]/', '_', substr($type, 0, 80)) : 'invalid',
            get_class($e)
        )
    );
    http_response_code(500);
    // RU: Даже при debug=1 нельзя отдавать исходный текст исключения:
    // RU: сторонние процессоры могут включать в него временные пароли.
    // EN: Even in debug mode, never expose raw exception messages that
    // EN: may contain paths, credentials or third-party processor output.
    $response = [
        'success' => false,
        'error' => 'Internal Server Error',
        'error_id' => $errorId,
    ];
    $response = array_merge($response,
        \ModxMcp\Diagnostics\ErrorCatalog::fields('Internal Server Error',
            'INTERNAL_ERROR', $errorId));
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}
