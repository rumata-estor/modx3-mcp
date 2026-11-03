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

function modxmcpVerifyProtectedHttpRequest($modx, $rawBody, $action)
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
    return ModxMcpPermitVerifier::authorize(
        $permit, $rawBody, $action, $trusted, $key, $ledger
    );
}
