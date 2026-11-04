<?php
/**
 * Unconnected strict v2 CAS permission and signed baseline verification.
 * Does NOT execute MODX writes and is NOT accepted by the live HTTP endpoint.
 */
require_once __DIR__ . '/permit-wire-v2.php';
require_once __DIR__ . '/permit-nonce-ledger.php';

class ModxMcpCasPermitVerifierV2
{
    private static function canonical($value)
    {
        if (!is_array($value)) { return $value; }
        $keys = array_keys($value);
        $list = ($keys === range(0, count($value) - 1));
        if (!$list) { ksort($value, SORT_STRING); }
        foreach ($value as $key => $part) { $value[$key] = self::canonical($part); }
        return $value;
    }

    public static function observationDigest($site, $task, array $baseline)
    {
        $json = json_encode(self::canonical(array(
            'site_id'=>$site,'task_id'=>$task,'baseline'=>$baseline,
        )), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new RuntimeException('CAS observation cannot be encoded');
        }
        return hash('sha256', "SITEAGENT-EXECUTOR-OBSERVATION-V1\n" . $json);
    }

    private static function exactKeys($value, array $fields)
    {
        return is_array($value) && count($value) === count($fields)
            && count(array_diff(array_keys($value), $fields)) === 0;
    }

    public static function authorize(
        $permit, $rawBody, array $trusted, $key,
        ModxMcpPermitNonceLedger $ledger, $clock = null
    ) {
        if (!self::exactKeys($permit, array('claims','mac','baseline')) ||
            !is_array($permit['claims']) || !is_array($permit['baseline']) ||
            !is_string($rawBody) || strlen($rawBody) > 12000) {
            throw new RuntimeException('invalid CAS permission envelope');
        }
        $claims = $permit['claims'];
        foreach (array('audience','site_id','contour') as $field) {
            if (!isset($trusted[$field]) || !is_string($trusted[$field]) ||
                !isset($claims[$field]) || !is_string($claims[$field]) ||
                !hash_equals($trusted[$field], $claims[$field])) {
                throw new RuntimeException('CAS permit trusted site mismatch');
            }
        }
        if (!ModxMcpCasPermitWireV2::verify($claims, $permit['mac'], $key)) {
            throw new RuntimeException('invalid v2 CAS permission signature');
        }
        $now = $clock === null ? time() : $clock;
        if (!is_int($now) || $now < 1 ||
            (int)$claims['expires_at'] <= $now ||
            (int)$claims['expires_at'] > $now + 60) {
            throw new RuntimeException('CAS permission expired or unbounded');
        }
        if (!hash_equals($claims['request_digest'], hash('sha256', $rawBody))) {
            throw new RuntimeException('CAS request body digest mismatch');
        }
        $body = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE ||
            !self::exactKeys($body, array('action','type','data')) ||
            $body['action'] !== 'update_element' || $body['type'] !== 'chunk' ||
            !self::exactKeys($body['data'], array('id','content')) ||
            !is_int($body['data']['id']) || $body['data']['id'] < 1 ||
            !is_string($body['data']['content']) ||
            (string)$body['data']['id'] !== $claims['chunk_id']) {
            throw new RuntimeException('CAS request is not an exact chunk content update');
        }
        $baseline = $permit['baseline'];
        if (!self::exactKeys($baseline, array('element_type','id','storage_field',
                                             'static','before_content',
                                             'before_sha256','after_sha256')) ||
            $baseline['element_type'] !== 'chunk' ||
            $baseline['storage_field'] !== 'snippet' ||
            $baseline['static'] !== false ||
            !is_int($baseline['id']) ||
            $baseline['id'] !== $body['data']['id'] ||
            !is_string($baseline['before_content']) ||
            strlen($baseline['before_content']) > 12000 ||
            !is_string($baseline['before_sha256']) ||
            !is_string($baseline['after_sha256'])) {
            throw new RuntimeException('CAS baseline shape mismatch');
        }
        $actualBefore = hash('sha256', $baseline['before_content']);
        $actualAfter = hash('sha256', $body['data']['content']);
        if (!hash_equals($claims['before_sha256'], $actualBefore) ||
            !hash_equals($claims['after_sha256'], $actualAfter) ||
            !hash_equals($baseline['before_sha256'], $actualBefore) ||
            !hash_equals($baseline['after_sha256'], $actualAfter) ||
            hash_equals($baseline['before_content'], $body['data']['content']) ||
            !hash_equals($claims['snapshot_digest'], self::observationDigest(
                $claims['site_id'], $claims['task_id'], $baseline
            ))) {
            throw new RuntimeException('CAS signed baseline does not match bytes');
        }
        // An authorized envelope is still NOT permission to run a processor.
        // Atomic row CAS remains a separate, mandatory write-point operation.
        $ledger->reserve($claims['nonce']);
        return array('chunk_id'=>$baseline['id'],
                     'before_content'=>$baseline['before_content'],
                     'after_content'=>$body['data']['content']);
    }
}
