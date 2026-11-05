<?php
/**
 * TEST-only general MODX reviewed CAS, shared by the existing Executor.
 * There is ONE HTTP write entrypoint, never a fallback to ordinary API writes.
 * Must be installed with private key/nonce ledger and protected setting=1.
 * Out-of-transaction plugin/file side-effects require a separate deployment audit.
 */
require_once __DIR__ . '/permit-verifier.php';

final class ModxMcpReviewedCasV1
{
    private static function table($value)
    {
        $name=trim((string)$value, '`');
        if (!preg_match('/\A[A-Za-z_][A-Za-z0-9_]{0,127}\z/D',$name)) {
            throw new RuntimeException('Untrusted MODX table identifier');
        }
        return '`'.$name.'`';
    }

    private static function query($modx,$sql,array $args)
    {
        $stmt=$modx->prepare($sql);
        if (!$stmt || $stmt->execute($args)!==true) {
            throw new RuntimeException('Protected SQL precondition failed');
        }
        return $stmt;
    }

    private static function innoDb($modx,$table)
    {
        $engine=self::query($modx,
            'SELECT ENGINE FROM information_schema.TABLES '.
            'WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',
            array(trim($table,'`')))->fetchColumn();
        if (strcasecmp((string)$engine,'InnoDB')!==0) {
            throw new RuntimeException('CAS requires transactional InnoDB tables');
        }
    }

    private static function equality($actual,$wanted)
    {
        if (is_bool($wanted)) {
            return (string)$actual === ($wanted ? '1':'0');
        }
        return is_scalar($wanted) && (string)$actual === (string)$wanted;
    }

    private static function exactBody($raw)
    {
        if (!is_string($raw) || strlen($raw)>12000 ||
            !defined('JSON_UNESCAPED_LINE_TERMINATORS')) {
            throw new RuntimeException('Invalid reviewed CAS JSON');
        }
        $body=json_decode($raw,true);
        if (json_last_error()!==JSON_ERROR_NONE ||
            !is_array($body) ||
            array_keys($body)!==array('action','type','data') ||
            !is_array($body['data'])) {
            throw new RuntimeException('Noncanonical reviewed body');
        }
        $encoded=json_encode($body,JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS);
        if (!is_string($encoded) || !hash_equals($raw,$encoded)) {
            throw new RuntimeException('Duplicate or reordered reviewed JSON');
        }
        return $body;
    }

    private static function parsed($raw)
    {
        $body=self::exactBody($raw);
        $action=$body['action']; $kind=$body['type'];
        $data=$body['data'];
        $names=array('resource'=>'pagetitle','template'=>'templatename',
            'chunk'=>'name','snippet'=>'name','tv'=>'name');
        if (!isset($names[$kind]) ||
            !in_array($action,array('create_element','update_element',
                                   'update_resource_tvs'),true) ||
            !isset($data['_cas_expected']) ||
            !is_array($data['_cas_expected'])) {
            throw new RuntimeException('Unsupported CAS operation or missing baseline');
        }
        $fields=array(
            'resource'=>array('content','pagetitle','longtitle','description',
              'introtext','menutitle','alias','published','hidemenu',
              'parent','template','context_key','isfolder','menuindex'),
            'template'=>array('content','templatename','description','category'),
            'chunk'=>array('snippet','name','description','category'),
            'snippet'=>array('snippet','name','description','category'),
            'tv'=>array('name','caption','description','default_text',
                        'type','category','templates')
        );
        $expected=$data['_cas_expected'];
        unset($data['_cas_expected']);
        if ($action==='update_resource_tvs') {
            if ($kind!=='resource' ||
                array_keys($data)!==array('resource_id','tvs') ||
                !is_int($data['resource_id']) || $data['resource_id']<1 ||
                !is_array($data['tvs']) || count($data['tvs'])<1 ||
                count($data['tvs'])>20 ||
                array_keys($expected)!==array('tvs') ||
                !is_array($expected['tvs']) ||
                array_keys($data['tvs'])!==array_keys($expected['tvs'])) {
                throw new RuntimeException('Invalid signed TV CAS scope');
            }
            foreach ($data['tvs'] as $tv=>$value) {
                if (!is_string($tv) ||
                    !preg_match('/\A[A-Za-z][A-Za-z0-9_-]{0,99}\z/D',$tv) ||
                    !is_string($value) || !is_string($expected['tvs'][$tv]) ||
                    strlen($value)>50000) {
                    throw new RuntimeException('Invalid signed TV value');
                }
            }
        } elseif ($action==='create_element') {
            if ($expected!==array('absent'=>true) ||
                !isset($data[$names[$kind]]) ||
                !is_string($data[$names[$kind]]) ||
                trim($data[$names[$kind]])==='' ||
                array_key_exists('id',$data)) {
                throw new RuntimeException('CAS create target must be absent');
            }
            if ($kind==='resource' &&
                (!array_key_exists('published',$data) ||
                 !in_array($data['published'],array(0,1,false,true),true))) {
                throw new RuntimeException('Page published state must be explicit');
            }
        } else {
            if (!isset($data['id']) || !is_int($data['id']) || $data['id']<1 ||
                array_keys($expected)!==array('fields') ||
                !is_array($expected['fields']) || !$expected['fields']) {
                throw new RuntimeException('CAS update needs exact before-state');
            }
            foreach ($expected['fields'] as $name=>$old) {
                $key=preg_replace('/_sha256$/','',$name);
                if (!array_key_exists($key,$data) ||
                    ($key!==$name && (!is_string($old) ||
                     !preg_match('/\A[0-9a-f]{64}\z/D',$old)))) {
                    throw new RuntimeException('CAS before-state differs from approved fields');
                }
            }
            foreach ($data as $field=>$value) {
                if ($field==='id') { continue; }
                if (!array_key_exists($field,$expected['fields']) &&
                    !array_key_exists($field.'_sha256',$expected['fields'])) {
                    throw new RuntimeException('CAS update has unguarded fields');
                }
            }
        }
        foreach ($data as $field=>$value) {
            if (($field==='id' && $action==='update_element') ||
                ($action==='update_resource_tvs' &&
                 ($field==='resource_id' || $field==='tvs'))) { continue; }
            if (!in_array($field,$fields[$kind],true) || is_array($value)
                && !($kind==='tv' && $field==='templates')) {
                throw new RuntimeException('Unclassified or nested MODX mutation field');
            }
        }
        if ($kind==='snippet') {
            // Executable PHP side-effects cannot be transactionally rolled back.
            throw new RuntimeException('Executable snippet CAS requires separate policy');
        }
        if ($kind==='tv' && $action==='create_element' && isset($data['templates'])) {
            if (!is_array($data['templates']) || count($data['templates'])>20 ||
                count($data['templates'])!==count(array_unique($data['templates']))) {
                throw new RuntimeException('Invalid TV template assignment');
            }
            foreach ($data['templates'] as $id) {
                if (!is_int($id) || $id<1) {
                    throw new RuntimeException('TV template IDs must be explicit');
                }
            }
        }
        return array($action,$kind,$data,$expected,$names[$kind]);
    }

    private static function classFor($major,$kind)
    {
        $classes=array('resource'=>'modResource','template'=>'modTemplate',
            'chunk'=>'modChunk','tv'=>'modTemplateVar');
        return $major===3 ? 'MODX\\Revolution\\'.$classes[$kind] : $classes[$kind];
    }

    private static function row($modx,$table,$column,$value,$lock)
    {
        $sql='SELECT * FROM '.$table.' WHERE '.self::table($column).'=?'
            .($lock ? ' FOR UPDATE' : '');
        return self::query($modx,$sql,array($value))->fetch(PDO::FETCH_ASSOC);
    }

    private static function validateBefore(array $row,array $expected)
    {
        foreach ($expected as $field=>$value) {
            if (substr($field,-7)==='_sha256') {
                $key=substr($field,0,-7);
                if (!isset($row[$key]) || !is_string($value) ||
                    !hash_equals($value,hash('sha256',(string)$row[$key]))) {
                    throw new RuntimeException('Stale CAS content hash');
                }
            } elseif (!array_key_exists($field,$row) ||
                      !self::equality($row[$field],$value)) {
                throw new RuntimeException('STALE_STATE: approved field changed');
            }
        }
    }

    private static function checkAfter(array $row,array $desired)
    {
        foreach ($desired as $field=>$value) {
            if ($field==='templates') { continue; }
            if (!array_key_exists($field,$row) ||
                !self::equality($row[$field],$value)) {
                // Field NAME only: never put page content or secrets in logs.
                throw new RuntimeException('MODX postcondition mismatch in: '.$field);
            }
        }
    }

    public static function executeUnconnected($modx,$permit,$rawBody,
        array $trusted,$key,ModxMcpPermitNonceLedger $ledger,$clock=null)
    {
        $version=$modx->getVersionData();
        $major=isset($version['version']) ? (int)$version['version'] : 0;
        if (!in_array($major,array(2,3),true) ||
            !isset($trusted['site_id'],$trusted['contour'],$trusted['audience']) ||
            $trusted['site_id']!==($major===2
                ? 'modx2-alex-palochkin' : 'modx3-alex-palochkin') ||
            $trusted['contour']!=='test') {
            throw new RuntimeException('Reviewed CAS restricted to matching TEST MODX 2/3');
        }
        list($action,$kind,$data,$expected,$nameField)=self::parsed($rawBody);
        $entity=self::classFor($major,$kind);
        $settings=$major===3 ?
            'MODX\\Revolution\\modSystemSetting' : 'modSystemSetting';
        $table=self::table($modx->getTableName($entity));
        $settingsTable=self::table($modx->getTableName($settings));
        $flag=$modx->getObject($settings,array(
            'key'=>'modxmcp.external_authorization_required'));
        $auto=$modx->getObject($settings,array('key'=>'modxmcp.auto_static'));
        if (!$flag || trim((string)$flag->get('value'))!=='1' ||
            !$auto || trim((string)$auto->get('value'))!=='0' ||
            (bool)$modx->getOption('modxmcp.auto_static',null,false)) {
            throw new RuntimeException('Protected nonstatic CAS must be enabled');
        }
        self::innoDb($modx,$table);
        self::innoDb($modx,$settingsTable);
        // Count the CMS resources even when a template/TV operation runs.
        // A plugin that silently creates extra pages must not turn a single
        // approved operation into a hundred resources inside the DB txn.
        $resourceTable=self::table($modx->getTableName(
            self::classFor($major,'resource')));
        self::innoDb($modx,$resourceTable);
        $tvTable=null;
        if ($kind==='tv' || $action==='update_resource_tvs') {
            $link=$major===3 ? 'MODX\\Revolution\\modTemplateVarTemplate'
                             : 'modTemplateVarTemplate';
            $linkTable=self::table($modx->getTableName($link));
            self::innoDb($modx,$linkTable);
            if ($action==='update_resource_tvs') {
                $valuesClass=$major===3 ? 'MODX\\Revolution\\modTemplateVarResource'
                                        : 'modTemplateVarResource';
                self::innoDb($modx,self::table($modx->getTableName($valuesClass)));
            }
        }
        // Consume one-use nonce BEFORE the transaction. There is no retry.
        ModxMcpPermitVerifier::authorize($permit,$rawBody,$action,
            $trusted,$key,$ledger,$clock);
        if ($modx->beginTransaction()===false) {
            throw new RuntimeException('Protected transaction could not begin');
        }
        $committed=false; $newId=null;
        try {
            $revRow=self::query($modx,'SELECT value FROM '.$settingsTable.
                ' WHERE `key`=? FOR UPDATE',array('modxmcp.site_revision'))
                ->fetch(PDO::FETCH_ASSOC);
            if (!is_array($revRow) ||
                !preg_match('/\A(?:0|[1-9][0-9]{0,9})\z/D',(string)$revRow['value']) ||
                (int)$revRow['value']>=2147483647) {
                throw new RuntimeException('Protected site revision absent or invalid');
            }
            self::innoDb($modx,$table);
            self::innoDb($modx,$settingsTable);
            self::innoDb($modx,$resourceTable);
            $pagesBefore=(int)self::query($modx,
                'SELECT COUNT(*) FROM '.$resourceTable,array())->fetchColumn();
            $kindBefore=(int)self::query($modx,
                'SELECT COUNT(*) FROM '.$table,array())->fetchColumn();
            if ($action==='update_resource_tvs') {
                $resource=self::row($modx,$table,'id',$data['resource_id'],true);
                if (!is_array($resource)) {
                    throw new RuntimeException('Target resource missing');
                }
                $resourceObj=$modx->getObject($entity,$data['resource_id']);
                if (!$resourceObj) { throw new RuntimeException('Resource object unavailable'); }
                $tvClass=self::classFor($major,'tv');
                foreach ($data['tvs'] as $tvName=>$value) {
                    $tvObj=$modx->getObject($tvClass,array('name'=>$tvName));
                    if (!$tvObj) { throw new RuntimeException('Unknown TV in signed package'); }
                    $linkClass=$major===3
                        ? 'MODX\\Revolution\\modTemplateVarTemplate'
                        : 'modTemplateVarTemplate';
                    if (!$modx->getObject($linkClass,array(
                        'tmplvarid'=>(int)$tvObj->get('id'),
                        'templateid'=>(int)$resource['template']))) {
                        throw new RuntimeException('TV not assigned to resource template');
                    }
                    if (!hash_equals($expected['tvs'][$tvName],
                        (string)$resourceObj->getTVValue($tvName))) {
                        throw new RuntimeException('STALE_STATE: TV value changed');
                    }
                }
                foreach ($data['tvs'] as $tvName=>$value) {
                    if ($resourceObj->setTVValue($tvName,$value)===false ||
                        (string)$resourceObj->getTVValue($tvName)!==$value) {
                        throw new RuntimeException('Protected TV save failed');
                    }
                }
                $newId=$data['resource_id'];
            } elseif ($action==='create_element') {
                $name=$data[$nameField];
                $previous=self::row($modx,$table,$nameField,$name,true);
                if (is_array($previous)) {
                    throw new RuntimeException('CAS requested created object already exists');
                }
                $args=$data;
                if ($kind==='tv') { unset($args['templates']); }
                if ($kind==='resource') {
                    $args+=array('context_key'=>'web','parent'=>0);
                }
                $processor=$major===3
                    ? 'MODX\\Revolution\\Processors\\'
                      .($kind==='resource'?'Resource':
                        'Element\\'.($kind==='tv'?'TemplateVar':ucfirst($kind))).'\\Create'
                    : ($kind==='resource'?'resource':'element/'.$kind).'/create';
                $response=$modx->runProcessor($processor,$args);
                if (!$response || $response->isError()) {
                    throw new RuntimeException('MODX create processor refused CAS');
                }
                $object=$response->getObject();
                $newId=isset($object['id']) ? (int)$object['id'] : 0;
                if ($newId<1) { throw new RuntimeException('Created ID not returned'); }
                $after=self::row($modx,$table,'id',$newId,true);
                if (!is_array($after)) { throw new RuntimeException('Created row not visible'); }
                self::checkAfter($after,$args);
                if ($kind==='tv' && isset($data['templates'])) {
                    $linkClass=$major===3
                        ? 'MODX\\Revolution\\modTemplateVarTemplate'
                        : 'modTemplateVarTemplate';
                    foreach ($data['templates'] as $templateId) {
                        $link=$modx->newObject($linkClass);
                        $link->fromArray(array('tmplvarid'=>$newId,
                                              'templateid'=>$templateId),'',true,true);
                        if (!$link->save()) {
                            throw new RuntimeException('CAS TV template assignment failed');
                        }
                    }
                    $links=$modx->getCollection($linkClass,array('tmplvarid'=>$newId));
                    $actualIds=array();
                    foreach ($links as $link) {
                        $actualIds[]=(int)$link->get('templateid');
                    }
                    sort($actualIds,SORT_NUMERIC);
                    $requestedIds=$data['templates'];
                    sort($requestedIds,SORT_NUMERIC);
                    if ($actualIds!==$requestedIds) {
                        throw new RuntimeException('TV relation postcondition mismatch');
                    }
                }
                $count=(int)self::query($modx,'SELECT COUNT(*) FROM '.$table.
                    ' WHERE '.self::table($nameField).'=?',array($name))
                    ->fetchColumn();
                if ($count!==1) {
                    throw new RuntimeException('Created object name is not unique');
                }
                $still=self::row($modx,$table,$nameField,$name,true);
                if (!is_array($still) || (int)$still['id']!==$newId) {
                    throw new RuntimeException('Created object identity changed');
                }
            } else {
                $id=$data['id']; unset($data['id']);
                $original=self::row($modx,$table,'id',$id,true);
                if (!is_array($original)) { throw new RuntimeException('CAS target vanished'); }
                if (isset($original['static']) && (int)$original['static']!==0) {
                    throw new RuntimeException('Static element cannot be protected by DB CAS');
                }
                self::validateBefore($original,$expected['fields']);
                $args=array_merge(array('id'=>$id),$data);
                if (($kind==='chunk' || $kind==='snippet') &&
                    !isset($args['name'])) {
                    $args['name']=$original['name'];
                }
                $processor=$major===3
                    ? 'MODX\\Revolution\\Processors\\'
                      .($kind==='resource'?'Resource':
                        'Element\\'.($kind==='tv'?'TemplateVar':ucfirst($kind))).'\\Update'
                    : ($kind==='resource'?'resource':'element/'.$kind).'/update';
                $response=$modx->runProcessor($processor,$args);
                if (!$response || $response->isError()) {
                    throw new RuntimeException('MODX update processor refused CAS');
                }
                $after=self::row($modx,$table,'id',$id,true);
                if (!is_array($after)) { throw new RuntimeException('Updated CAS row missing'); }
                self::checkAfter($after,$data);
                $systemFields=array('editedon','editedby','publishedon');
                foreach ($original as $field=>$value) {
                    if ($field==='id' || in_array($field,$systemFields,true) ||
                        array_key_exists($field,$data)) { continue; }
                    if (!array_key_exists($field,$after) || $after[$field]!==$value) {
                        throw new RuntimeException('Processor changed an unapproved field');
                    }
                }
                $newId=$id;
            }
            $pagesAfter=(int)self::query($modx,
                'SELECT COUNT(*) FROM '.$resourceTable,array())->fetchColumn();
            $kindAfter=(int)self::query($modx,
                'SELECT COUNT(*) FROM '.$table,array())->fetchColumn();
            $expectedPageDelta=($action==='create_element' &&
                $kind==='resource') ? 1 : 0;
            $expectedKindDelta=($action==='create_element') ? 1 : 0;
            if ($pagesAfter!==$pagesBefore+$expectedPageDelta ||
                $kindAfter!==$kindBefore+$expectedKindDelta) {
                throw new RuntimeException(
                    'Processor created or removed CMS objects outside approved scope');
            }
            $next=(int)$revRow['value']+1;
            $update=self::query($modx,'UPDATE '.$settingsTable.
                ' SET value=? WHERE `key`=?',
                array((string)$next,'modxmcp.site_revision'));
            if ($update->rowCount()!==1) {
                throw new RuntimeException('Protected site revision not incremented');
            }
            if ($modx->commit()===false) {
                throw new RuntimeException('Protected COMMIT failed');
            }
            $committed=true;
        } finally {
            if (!$committed) { $modx->rollback(); }
        }
        $cache=$modx->getCacheManager();
        if (!$cache || $cache->refresh()===false) {
            throw new RuntimeException('CAS committed but cache outcome unknown');
        }
        return array('id'=>$newId,'site_revision'=>$next,
                     'action'=>$action,'type'=>$kind);
    }
}
