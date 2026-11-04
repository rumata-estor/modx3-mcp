<?php
/**
 * UNCONNECTED source-only assembly of v2 permit verifier + single SQL CAS.
 * No MODX hooks, revision increment, cache invalidation or live API route.
 * Not safe for enabling on a real site until those semantics are implemented.
 */
require_once __DIR__ . '/permit-verifier-v2.php';
require_once __DIR__ . '/chunk-cas.php';

class ModxMcpChunkCasPilot
{
    public static function executeUnconnected(
        $permit, $rawBody, array $trusted, $key,
        ModxMcpPermitNonceLedger $ledger, $connection, $trustedTable, $clock = null
    ) {
        // The verifier consumes one nonce BEFORE any SQL is attempted. A CAS
        // conflict or DB error must NOT release it for blind retries.
        $authorized = ModxMcpCasPermitVerifierV2::authorize(
            $permit, $rawBody, $trusted, $key, $ledger, $clock
        );
        return ModxMcpChunkCas::compareAndSwap(
            $connection, $trustedTable,
            $authorized['chunk_id'],
            $authorized['before_content'],
            $authorized['after_content']
        );
    }
}
