<?php
/**
 * CAS permit v2 fixed-field wire contract. NOT attached to the live endpoint.
 * Version-separated from permit v1; no downgrade to v1 allowed.
 */
class ModxMcpCasPermitWireV2
{
    private static $fields = array(
        'version','audience','site_id','contour','task_id','plan_digest',
        'snapshot_digest','operation_id','action','element_type','chunk_id',
        'before_sha256','after_sha256','request_digest','nonce','expires_at',
    );

    public static function encode(array $claims)
    {
        if (count($claims) !== count(self::$fields) ||
            count(array_diff(array_keys($claims), self::$fields)) !== 0 ||
            !isset($claims['version'],$claims['action'],$claims['element_type']) ||
            $claims['version'] !== '2' ||
            $claims['action'] !== 'update_element' ||
            $claims['element_type'] !== 'chunk') {
            throw new InvalidArgumentException('CAS permit field set or version mismatch');
        }
        $wire = "SITEAGENT-MODX-CAS-PERMIT-V2\n";
        foreach (self::$fields as $field) {
            if (!isset($claims[$field]) || !is_string($claims[$field]) ||
                !preg_match('/\A[A-Za-z0-9_.:-]{1,160}\z/D', $claims[$field])) {
                throw new InvalidArgumentException('invalid CAS claim: '.$field);
            }
            $wire .= $field . '=' . $claims[$field] . "\n";
        }
        if (!preg_match('/\ATASK-[0-9]{8}-[0-9]{6}-[0-9a-f]{4}\z/D', $claims['task_id'])) {
            throw new InvalidArgumentException('invalid CAS task id');
        }
        foreach (array('plan_digest','snapshot_digest','before_sha256',
                       'after_sha256','request_digest') as $field) {
            if (!preg_match('/\A[0-9a-f]{64}\z/D', $claims[$field])) {
                throw new InvalidArgumentException('invalid CAS digest: '.$field);
            }
        }
        if (!preg_match('/\A[0-9a-f]{32}\z/D', $claims['nonce']) ||
            !preg_match('/\A[1-9][0-9]*\z/D', $claims['chunk_id']) ||
            strlen($claims['chunk_id']) > 10 ||
            (float)$claims['chunk_id'] > 2147483647 ||
            !preg_match('/\A[1-9][0-9]*\z/D', $claims['expires_at'])) {
            throw new InvalidArgumentException('invalid CAS nonce, ID or expiry');
        }
        return $wire;
    }

    public static function sign(array $claims, $key)
    {
        if (!is_string($key) || strlen($key) < 32) {
            throw new InvalidArgumentException('CAS signer key too short');
        }
        return hash_hmac('sha256', self::encode($claims), $key);
    }

    public static function verify(array $claims, $mac, $key)
    {
        if (!is_string($mac) || !preg_match('/\A[0-9a-f]{64}\z/D', $mac)) {
            return false;
        }
        return hash_equals(self::sign($claims, $key), $mac);
    }
}
