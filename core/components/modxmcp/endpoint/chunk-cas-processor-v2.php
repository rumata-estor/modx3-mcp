<?php
/**
 * NOT AN HTTP ROUTE. Strict source-only MODX 2/3 v2 CAS processor pilot.
 *
 * Signed, one-use permission -> xPDO row lock -> actual MODX update processor
 * -> exact postcondition -> revision + InnoDB audit in the SAME transaction
 * -> commit -> cache invalidation. No ordinary API fallback is permissible.
 *
 * Prerequisites provisioned by trusted operator: private key, *separate*
 * autocommit InnoDB nonce connection, InnoDB audit table and revision setting.
 * No guarantee about arbitrary third-party plugin external side effects;
 * do not enable on live sites before plugin/rollback/cutover audit.
 */
require_once __DIR__.'/permit-verifier-v2.php';

class ModxMcpChunkCasProcessorV2
{
    private static function table($value)
    {
        $table=trim((string)$value, '`');
        if (!preg_match('/\A[A-Za-z_][A-Za-z0-9_]{0,127}\z/D', $table)) {
            throw new RuntimeException('untrusted MODX table identifier');
        }
        return '`'.$table.'`';
    }

    private static function persisted($modx, $class, $key)
    {
        $setting=$modx->getObject($class,array('key'=>$key));
        if (!$setting) {
            throw new RuntimeException('required protected MODX setting missing');
        }
        return trim((string)$setting->get('value'));
    }

    private static function one($modx, $sql, array $args)
    {
        $statement=$modx->prepare($sql);
        if (!$statement || $statement->execute($args)!==true) {
            throw new RuntimeException('protected MODX SQL precondition unavailable');
        }
        return $statement;
    }

    public static function executeUnconnected(
        $modx, $permit, $rawBody, array $trusted, $key,
        ModxMcpPermitNonceLedger $ledger, $auditTable, $clock=null
    ) {
        $version=$modx->getVersionData();
        $major=isset($version['version']) ? (int)$version['version'] : 0;
        if (!in_array($major,array(2,3),true) ||
            !is_object($modx->getConnection()) || !method_exists($modx,'prepare') ||
            !is_string($rawBody) ||
            !isset($trusted['site_id'],$trusted['contour'],$trusted['audience']) ||
            !in_array($trusted['site_id'],
                array('modx2-alex-palochkin','modx3-alex-palochkin'),true) ||
            $trusted['contour']!=='test') {
            throw new RuntimeException('CAS processor pilot restricted to isolated test MODX');
        }
        $chunkClass=$major===3 ? 'MODX\\Revolution\\modChunk' : 'modChunk';
        $settingClass=$major===3 ? 'MODX\\Revolution\\modSystemSetting' : 'modSystemSetting';
        $chunkTable=self::table($modx->getTableName($chunkClass));
        $settingTable=self::table($modx->getTableName($settingClass));
        $auditTable=self::table($auditTable);

        // Read persisted options, not only potentially stale MODX cache.
        // Even this unconnected pilot fails closed if any option is ambiguous.
        if (self::persisted($modx,$settingClass,
                'modxmcp.external_authorization_required')!=='1' ||
            self::persisted($modx,$settingClass,'modxmcp.auto_static')!=='0' ||
            (bool)$modx->getOption('modxmcp.auto_static',null,false) ||
            !is_array($trusted) || !array_key_exists('auto_static',$trusted) ||
            $trusted['auto_static']!==false) {
            throw new RuntimeException('CAS processor requires protected nonstatic mode');
        }
        $processor=$major===3
            ? 'MODX\\Revolution\\Processors\\Element\\Chunk\\Update'
            : 'element/chunk/update';

        // This consumes nonce in independently committed store BEFORE MODX
        // starts its transaction. No rollback/retry may unspend a permission.
        $authorized=ModxMcpCasPermitVerifierV2::authorize(
            $permit,$rawBody,$trusted,$key,$ledger,$clock
        );
        $id=$authorized['chunk_id'];
        $before=$authorized['before_content'];
        $after=$authorized['after_content'];

        if ($modx->beginTransaction()===false) {
            throw new RuntimeException('protected transaction could not begin');
        }
        $committed=false;
        try {
            $row=self::one($modx,
                'SELECT name, snippet, static FROM '.$chunkTable.
                ' WHERE id=? FOR UPDATE',array($id))->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row) || (int)$row['static']!==0 ||
                !is_string($row['snippet']) || !hash_equals($before,$row['snippet'])) {
                throw new RuntimeException('stale or static chunk CAS baseline');
            }
            $name=(string)$row['name'];
            $response=$modx->runProcessor($processor,array(
                'id'=>$id, 'name'=>$name, 'snippet'=>$after, 'static'=>0
            ));
            if (!$response || $response->isError()) {
                throw new RuntimeException('MODX processor refused protected CAS');
            }
            $current=self::one($modx,
                'SELECT name, snippet, static FROM '.$chunkTable.
                ' WHERE id=?',array($id))->fetch(PDO::FETCH_ASSOC);
            if (!is_array($current) || (int)$current['static']!==0 ||
                (string)$current['name']!==$name ||
                !is_string($current['snippet']) ||
                !hash_equals($after,$current['snippet'])) {
                throw new RuntimeException('processor changed protected CAS target');
            }

            $revisionRow=self::one($modx,
                'SELECT value FROM '.$settingTable.
                ' WHERE `key`=? FOR UPDATE',array('modxmcp.site_revision'))
                ->fetch(PDO::FETCH_ASSOC);
            if (!is_array($revisionRow) ||
                !preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/D',
                    (string)$revisionRow['value']) ||
                (float)$revisionRow['value']>=2147483647) {
                throw new RuntimeException('missing or invalid protected revision');
            }
            $revision=(int)$revisionRow['value']+1;
            $update=self::one($modx,
                'UPDATE '.$settingTable.' SET value=? WHERE `key`=?',
                array((string)$revision,'modxmcp.site_revision'));
            if ($update->rowCount()!==1) {
                throw new RuntimeException('protected revision was not updated');
            }

            $audit=self::one($modx,
                'INSERT INTO '.$auditTable.
                ' (nonce_sha256,chunk_id,before_sha256,after_sha256,site_revision)'.
                ' VALUES (?,?,?,?,?)',array(
                    hash('sha256',$permit['claims']['nonce']),$id,
                    hash('sha256',$before),hash('sha256',$after),$revision
                ));
            if ($audit->rowCount()!==1) {
                throw new RuntimeException('transactional CAS audit missing');
            }
            if ($modx->commit()===false) {
                throw new RuntimeException('protected transaction commit failed');
            }
            $committed=true;
        } finally {
            if (!$committed) {
                $modx->rollback();
            }
        }

        // A cache failure after commit is UNKNOWN, never a reason to resend.
        $cache=$modx->getCacheManager();
        if (!$cache || $cache->refresh()===false) {
            throw new RuntimeException('CAS persisted but cache refresh unverified');
        }
        return array('chunk_id'=>$id,'site_revision'=>$revision,
                     'after_sha256'=>hash('sha256',$after));
    }
}
