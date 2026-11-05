<?php
/**
 * Opt-in authenticated adapter for the MODX shared API endpoint.
 * Private key / nonce store must be provisioned outside the public web root.
 * This does not change the ordinary API mode.
 */
require_once __DIR__ . '/permit-verifier.php';

function modxmcpProtectedKey($filename)
{
    if (!is_string($filename) || $filename === '' || $filename[0] !== '/' ||
        is_link($filename) || !is_file($filename)) {
        throw new RuntimeException('trusted permit key file unavailable');
    }
    $real = realpath($filename);
    if ($real === false || $real !== $filename) {
        throw new RuntimeException('untrusted permit key path');
    }
    $docroot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    if ($docroot !== false &&
        ($real === $docroot || strpos($real, rtrim($docroot, '/') . '/') === 0)) {
        throw new RuntimeException('permit key must be outside document root');
    }
    $current = dirname($real);
    while (true) {
        $st = @lstat($current);
        if ($st === false || !is_dir($current) || is_link($current) ||
            ($st['mode'] & 0022)) {
            throw new RuntimeException('untrusted permit key ancestor');
        }
        $next = dirname($current);
        if ($next === $current) { break; }
        $current = $next;
    }
    $st = @lstat($real);
    if ($st === false || ($st['mode'] & 0077)) {
        throw new RuntimeException('permit key file permissions invalid');
    }
    $fp = @fopen($real, 'rb');
    if ($fp === false) { throw new RuntimeException('permit key unreadable'); }
    try {
        $opened = fstat($fp);
        if ($opened === false || $opened['ino'] !== $st['ino'] ||
            $opened['dev'] !== $st['dev'] ||
            ($opened['mode'] & 0077)) {
            throw new RuntimeException('permit key file changed');
        }
        $hex = trim(stream_get_contents($fp, 1024));
    } finally {
        fclose($fp);
    }
    if (!preg_match('/\\A[0-9a-f]{64}\\z/D', $hex)) {
        throw new RuntimeException('invalid permit key');
    }
    return hex2bin($hex);
}

function modxmcpProtectedPermitEnvelope()
{
    if (!isset($_SERVER['HTTP_X_SITEAGENT_PERMIT'])) {
        throw new RuntimeException('missing independent Site Agent permit');
    }
    $wire = $_SERVER['HTTP_X_SITEAGENT_PERMIT'];
    if (!is_string($wire) || strlen($wire) > 8192) {
        throw new RuntimeException('invalid permit header');
    }
    $decoded = base64_decode($wire, true);
    if ($decoded === false || strlen($decoded) > 6144) {
        throw new RuntimeException('invalid permit envelope');
    }
    $permit = json_decode($decoded, true);
    if (!is_array($permit)) {
        throw new RuntimeException('invalid permit JSON');
    }
    return $permit;
}

function modxmcpProtectedRuntimeConfig($modx)
{
    $trusted = array(
        'audience' => (string)$modx->getOption('modxmcp.permit_audience', null, ''),
        'site_id' => (string)$modx->getOption('modxmcp.permit_site_id', null, ''),
        'contour' => (string)$modx->getOption('modxmcp.permit_contour', null, ''),
    );
    foreach ($trusted as $value) {
        if ($value === '') { throw new RuntimeException('permit audience is not configured'); }
    }
    $key = modxmcpProtectedKey($modx->getOption('modxmcp.permit_key_file', null, ''));
    $ledger = new ModxMcpPermitNonceLedger(
        $modx->getOption('modxmcp.permit_nonce_directory', null, '')
    );
    return array($trusted, $key, $ledger);
}

function modxmcpVerifyProtectedHttpRequest($modx, $rawBody, $action)
{
    $permit = modxmcpProtectedPermitEnvelope();
    list($trusted,$key,$ledger) = modxmcpProtectedRuntimeConfig($modx);
    return ModxMcpPermitVerifier::authorize(
        $permit, $rawBody, $action, $trusted, $key, $ledger
    );
}

/**
 * Test-site-only signed CAS v2. Activation requires the existing protected
 * MODX setting and a valid private-key/nonce scope, not a PHP-FPM env var.
 */
function modxmcpExecuteSignedChunkCasV2($modx, $mcp, $rawBody)
{
    // A stale cache may delay activation, but must never permit a write.
    if ((string)$modx->getOption(
        'modxmcp.external_authorization_required', null, '0'
    ) !== '1') {
        throw new RuntimeException('protected CAS v2 HTTP mode not active');
    }
    $configuredSite=(string)$modx->getOption('modxmcp.permit_site_id',null,'');
    $configuredContour=(string)$modx->getOption('modxmcp.permit_contour',null,'');
    if ($configuredContour !== 'test' || !in_array($configuredSite,array(
            'modx2-alex-palochkin','modx3-alex-palochkin'
        ),true)) {
        throw new RuntimeException('protected CAS v2 restricted to pilot test sites');
    }
    if (!is_object($mcp) || !method_exists($mcp, 'processProtectedChunkCasV2')) {
        throw new RuntimeException('protected CAS processor bridge unavailable');
    }
    $permit = modxmcpProtectedPermitEnvelope();
    if (!isset($permit['claims']) || !is_array($permit['claims']) ||
        !isset($permit['claims']['version']) || $permit['claims']['version'] !== '2') {
        throw new RuntimeException('protected HTTP request requires CAS permit v2');
    }
    list($trusted,$key,$ledger) = modxmcpProtectedRuntimeConfig($modx);
    if ($trusted['contour'] !== 'test' ||
        !in_array($trusted['site_id'], array(
            'modx2-alex-palochkin','modx3-alex-palochkin'
        ), true)) {
        throw new RuntimeException('protected HTTP CAS restricted to approved test sites');
    }
    $trusted['auto_static'] = false;
    return $mcp->processProtectedChunkCasV2($permit,$rawBody,$trusted,$key,$ledger);
}
