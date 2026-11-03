<?php
/**
 * Strict signed permit wire verification. Development-only transport primitive.
 *
 * A valid signature is NOT sufficient authorization to write: caller must also
 * verify configured audience/site/contour and exact request digest, atomically
 * consume nonce on the MODX host, and enforce protected mutation dispatch.
 * Never expose the shared key to the Agent or an API caller.
 */
class ModxMcpPermitWire
{
    private static $fields = array(
        'version', 'audience', 'site_id', 'contour', 'task_id', 'plan_digest',
        'operation_id', 'action', 'target_digest', 'parameters_digest',
        'request_digest', 'nonce', 'expires_at',
    );

    public static function encode(array $claims)
    {
        if (count($claims) !== count(self::$fields)) {
            throw new InvalidArgumentException('permit fields mismatch');
        }
        $extra = array_diff(array_keys($claims), self::$fields);
        if (count($extra) !== 0 || !isset($claims['version']) || $claims['version'] !== '1') {
            throw new InvalidArgumentException('unknown field or version');
        }
        $wire = "SITEAGENT-MODX-PERMIT-V1\n";
        foreach (self::$fields as $field) {
            if (!array_key_exists($field, $claims) ||
                !is_string($claims[$field]) ||
                !preg_match('/\\A[A-Za-z0-9_.:-]{1,160}\\z/D', $claims[$field])) {
                throw new InvalidArgumentException('invalid permit field: ' . $field);
            }
            $wire .= $field . '=' . $claims[$field] . "\n";
        }
        if (!preg_match('/\\ATASK-[0-9]{8}-[0-9]{6}-[0-9a-f]{4}\\z/D', $claims['task_id'])) {
            throw new InvalidArgumentException('invalid task id');
        }
        foreach (array('plan_digest', 'target_digest', 'parameters_digest', 'request_digest') as $field) {
            if (!preg_match('/\\A[0-9a-f]{64}\\z/D', $claims[$field])) {
                throw new InvalidArgumentException('invalid digest: ' . $field);
            }
        }
        if (!preg_match('/\\A[0-9a-f]{32}\\z/D', $claims['nonce'])) {
            throw new InvalidArgumentException('invalid nonce');
        }
        if (!preg_match('/\\A[1-9][0-9]*\\z/D', $claims['expires_at'])) {
            throw new InvalidArgumentException('invalid expiry');
        }
        return $wire;
    }

    public static function sign(array $claims, $key)
    {
        if (!is_string($key) || strlen($key) < 32) {
            throw new InvalidArgumentException('issuer key must be at least 32 bytes');
        }
        return hash_hmac('sha256', self::encode($claims), $key);
    }

    public static function verify(array $claims, $mac, $key)
    {
        if (!is_string($mac) || !preg_match('/\\A[0-9a-f]{64}\\z/D', $mac)) {
            return false;
        }
        return hash_equals(self::sign($claims, $key), $mac);
    }
}
