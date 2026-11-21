<?php
namespace ModxMcp\Security;

/**
 * OFFLINE PRIMITIVE ONLY. NOT an authorization decision.
 *
 * The external Controller holds the private Ed25519 key. MODX MCP keeps
 * only the public verification key and NEVER signs permits.
 *
 * WARNING: valid signature alone does not authorize a mutation! The caller
 * still must bind the digest to the exact request and CAS, consume a nonce
 * durably, enforce site policy, and check all scopes under the mutation lock.
 * Until those components are present, this class is NOT wired to the API.
 */
final class IndependentPermitV1
{
    private static $fields = array(
        'version','audience','site_id','contour','action','element_type',
        'request_digest','nonce','issued_at','expires_at'
    );

    public static function canonicalClaims(array $claims)
    {
        if (count($claims) !== count(self::$fields)
            || array_diff(array_keys($claims), self::$fields)
            || array_diff(self::$fields, array_keys($claims))) {
            throw new \InvalidArgumentException('Unknown or missing permit claim.');
        }
        $wire = "MODXMCP-INDEPENDENT-ED25519-PERMIT-V1\n";
        foreach (self::$fields as $field) {
            if (!isset($claims[$field]) || !is_string($claims[$field])) {
                throw new \InvalidArgumentException('Permit claim must be a string: '.$field);
            }
            $value = $claims[$field];
            if (!preg_match('/\A[A-Za-z0-9_.:-]{1,120}\z/D', $value)) {
                throw new \InvalidArgumentException('Invalid permit claim: '.$field);
            }
            $wire .= $field.'='.$value."\n";
        }
        if ($claims['version'] !== '1') {
            throw new \InvalidArgumentException('Unsupported permit version.');
        }
        foreach (array('request_digest'=>64,'nonce'=>32) as $field=>$length) {
            if (!preg_match('/\A[0-9a-f]{'.$length.'}\z/D',$claims[$field])) {
                throw new \InvalidArgumentException('Invalid digest or nonce: '.$field);
            }
        }
        foreach (array('issued_at','expires_at') as $field) {
            if (!preg_match('/\A[1-9][0-9]{0,10}\z/D',$claims[$field])) {
                throw new \InvalidArgumentException('Invalid permit timestamp.');
            }
        }
        return $wire;
    }

    public static function verifyCryptographicClaims(
        array $claims, $signatureHex, $publicKeyHex,
        array $trusted, $observedRequestDigest, $now = null
    ) {
        $wire = self::canonicalClaims($claims);
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            throw new \RuntimeException('Ed25519 verification unavailable: deny.');
        }
        if (!is_string($signatureHex)
            || !preg_match('/\A[0-9a-f]{128}\z/D',$signatureHex)
            || !is_string($publicKeyHex)
            || !preg_match('/\A[0-9a-f]{64}\z/D',$publicKeyHex)) {
            throw new \RuntimeException('Malformed Ed25519 signature or public key.');
        }
        foreach (array('audience','site_id','contour') as $field) {
            if (!isset($trusted[$field]) || !is_string($trusted[$field])
                || !hash_equals($trusted[$field], $claims[$field])) {
                throw new \RuntimeException('Permit audience/site/contour mismatch.');
            }
        }
        if (!is_string($observedRequestDigest)
            || !preg_match('/\A[0-9a-f]{64}\z/D',$observedRequestDigest)
            || !hash_equals($claims['request_digest'],$observedRequestDigest)) {
            throw new \RuntimeException('Permit request digest mismatch.');
        }
        $clock = $now === null ? time() : $now;
        if (!is_int($clock) || $clock <= 0) {
            throw new \RuntimeException('Untrusted permit clock.');
        }
        $issued = (int)$claims['issued_at'];
        $expires = (int)$claims['expires_at'];
        if ($issued > $clock + 5 || $issued < $clock - 120
            || $expires <= $clock || $expires > $clock + 120
            || $expires <= $issued || $expires - $issued > 120) {
            throw new \RuntimeException('Expired or unbounded permit.');
        }
        if (!sodium_crypto_sign_verify_detached(
            hex2bin($signatureHex),$wire,hex2bin($publicKeyHex)
        )) {
            throw new \RuntimeException('Invalid Ed25519 permit signature.');
        }
        return true;
    }
}
