<?php
/**
 * Isolated verifier for the future protected API adapter.
 * No direct HTTP integration: callers must bind a trusted site configuration
 * and the exact raw HTTP payload. Never source keys/audience from request JSON.
 */
require_once __DIR__ . '/permit-wire.php';
require_once __DIR__ . '/permit-nonce-ledger.php';

class ModxMcpPermitVerifier
{
    private $action;
    private $requestDigest;

    private function __construct($action, $requestDigest)
    {
        $this->action = $action;
        $this->requestDigest = $requestDigest;
    }

    public function allowsAction($action)
    {
        return is_string($action) && hash_equals($this->action, $action);
    }

    public static function authorize(
        array $permit, $rawBody, $action, array $trusted, $key,
        ModxMcpPermitNonceLedger $ledger, $clock = null
    ) {
        if (!isset($permit['claims'], $permit['mac']) ||
            count($permit) !== 2 || !is_array($permit['claims']) ||
            !is_string($rawBody) || !is_string($action)) {
            throw new RuntimeException('invalid protected permit envelope');
        }
        $claims = $permit['claims'];
        // The trusted configuration comes from the site runtime, never POST.
        foreach (array('audience', 'site_id', 'contour') as $field) {
            if (!isset($trusted[$field]) || !is_string($trusted[$field]) ||
                !isset($claims[$field]) ||
                !hash_equals($trusted[$field], (string)$claims[$field])) {
                throw new RuntimeException('permit audience or site mismatch');
            }
        }
        if (!ModxMcpPermitWire::verify($claims, $permit['mac'], $key)) {
            throw new RuntimeException('invalid permit signature');
        }
        if (!hash_equals($action, $claims['action']) ||
            !hash_equals(hash('sha256', $rawBody), $claims['request_digest'])) {
            throw new RuntimeException('permit action or request body mismatch');
        }
        $now = $clock === null ? time() : $clock;
        if (!is_int($now) || $now < 1 || (int)$claims['expires_at'] <= $now ||
            (int)$claims['expires_at'] > $now + 60) {
            throw new RuntimeException('expired or unbounded permit');
        }
        // After every check, consume before invoking any MODX mutation.
        $ledger->reserve($claims['nonce']);
        return new self($action, $claims['request_digest']);
    }
}
