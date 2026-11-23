<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Независимое разрешение мутаций: подпись, политика сайта и одноразовый nonce.
 * EN: Independent mutation authorization: signature, site policy and one-use nonce.
 */
namespace ModxMcp\Security;

/**
 * Independent external authorization for MODX MCP mutations.
 *
 * The client provides a signed, exact HTTP request. The controller's private
 * Ed25519 signing key is NEVER part of MODX MCP. In protected mode, every
 * mutating dispatcher route must pass here under the shared mutation lock.
 * No direct SQL is used for MODX content changes.
 */
final class ProtectedAuthorization
{
    const MODE_SETTING = 'modxmcp.external_authorization_required';
    const LEDGER_SUFFIX = 'modxmcp_permit_nonces';

    private static function settingsTable($modx)
    {
        $version = method_exists($modx,'getVersionData') ? $modx->getVersionData() : array();
        $cls = isset($version['version']) && (int)$version['version'] >= 3
            ? 'MODX\\Revolution\\modSystemSetting' : 'modSystemSetting';
        $table = $modx->getTableName($cls);
        if (!is_string($table) || !preg_match('/\A`?[A-Za-z0-9_]+`?\z/D',$table)) {
            throw new \RuntimeException('Protected authorization: unsafe setting table.');
        }
        return $table;
    }

    private static function readSetting($modx, $key)
    {
        $stmt=$modx->prepare(
            'SELECT `value` FROM '.self::settingsTable($modx)
            .' WHERE `key`=? LIMIT 1'
        );
        if (!$stmt || !$stmt->execute(array($key))) {
            throw new \RuntimeException('Protected authorization: settings unavailable.');
        }
        $value=$stmt->fetchColumn();
        return $value===false ? null : (string)$value;
    }

    public static function required($modx)
    {
        // Never trust a process-local xPDO cached setting for a security gate.
        // A broken/missing DB query must fail closed (throw), not read "false".
        $mode=self::readSetting($modx,self::MODE_SETTING);
        if ($mode==='0') return false;
        if ($mode==='1') return true;
        // Missing/invalid configuration must not silently disable an
        // explicitly staged protected component. New installs ship mode=0.
        throw new \RuntimeException(
            'Protected authorization: missing or invalid policy mode.'
        );
    }

    /**
     * RU: Для защищённого запуска процессора читаем галочку непосредственно
     * RU: из БД, чтобы кеш PHP не переопределял решение администратoра.
     * EN: Read the protected processor switch from the database so a stale
     * EN: PHP option cache cannot override the administrator's decision.
     */
    public static function processorMasterEnabled($modx)
    {
        return self::readSetting($modx, 'modxmcp.allow_run_processor') === '1';
    }

    public static function ledgerTable($modx)
    {
        $settingsTable=trim(self::settingsTable($modx),'`');
        if (substr($settingsTable,-strlen('system_settings'))!=='system_settings') {
            throw new \RuntimeException('Protected authorization: unknown MODX table prefix.');
        }
        $prefix=substr($settingsTable,0,-strlen('system_settings'));
        $name=$prefix.self::LEDGER_SUFFIX;
        if (!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{0,63}\z/D',$name)) {
            throw new \RuntimeException('Protected authorization: unsafe nonce table.');
        }
        return $name;
    }

    private static function trustedConfiguration($modx)
    {
        $keys=array(
            'audience'=>'modxmcp.permit_audience',
            'site_id'=>'modxmcp.permit_site_id',
            'contour'=>'modxmcp.permit_contour',
        );
        $trusted=array();
        foreach ($keys as $field=>$setting) {
            $value=self::readSetting($modx,$setting);
            if (!is_string($value)
                || !preg_match('/\A[A-Za-z0-9_.:-]{1,120}\z/D',$value)) {
                throw new \RuntimeException('Protected authorization: unconfigured trusted site identity.');
            }
            $trusted[$field]=$value;
        }
        $pub=self::readSetting($modx,'modxmcp.permit_public_key');
        if (!is_string($pub) || !preg_match('/\A[a-f0-9]{64}\z/D',$pub)) {
            throw new \RuntimeException('Protected authorization: trusted public key unavailable.');
        }
        return array($trusted,$pub);
    }

    private static function dedicatedNonceConnection($modx)
    {
        // xPDO's connection is used for content transactions. Replay state
        // must commit on a separate PDO connection before any business write.
        $dsn=(string)$modx->getOption('dsn',null,'');
        if ($dsn==='') $dsn=(string)$modx->getOption('database_dsn',null,'');
        if ($dsn==='' || strpos($dsn,'mysql:')!==0) {
            throw new \RuntimeException('Protected authorization: dedicated nonce DSN unavailable.');
        }
        $user=(string)$modx->getOption('username',null,'');
        if ($user==='') $user=(string)$modx->getOption('database_user',null,'');
        $pass=(string)$modx->getOption('password',null,'');
        if ($pass==='') $pass=(string)$modx->getOption('database_password',null,'');
        if ($user==='') {
            throw new \RuntimeException('Protected authorization: dedicated nonce DB user unavailable.');
        }
        try {
            return new \PDO($dsn,$user,$pass,array(
                \PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_EMULATE_PREPARES=>false,
                \PDO::ATTR_PERSISTENT=>false,
            ));
        } catch (\Throwable $e) {
            throw new \RuntimeException('Protected authorization: nonce connection unavailable.',0,$e);
        }
    }

    /**
     * Called inside StateSupport::withMutationLock(), before any write.
     * $input is trusted server input from the existing API handler; never
     * use claims or public key values supplied in request JSON as policy.
     */
    public static function authorizeMutation($modx,$action,$elementType,array $data,$input)
    {
        if (!is_array($input) || !isset($input['raw_body'],$input['permit'])
            || !is_string($input['raw_body']) || !is_array($input['permit'])) {
            throw new \RuntimeException('AUTH_REQUIRED: signed request is missing.');
        }
        // Protected mode cannot remove or redirect its own trust anchors
        // through a signed MCP setting mutation. Administrative changes
        // must use the MODX Manager / controlled installation procedure.
        if (in_array($action,array(
            'create_system_setting','update_system_setting','delete_system_setting',
        ),true)) {
            $settingKey=isset($data['key']) ? (string)$data['key']
                : (isset($data['name']) ? (string)$data['name'] : '');
            if ($settingKey==='' || strpos($settingKey,'modxmcp.')===0) {
                throw new \RuntimeException(
                    'AUTH_DENIED: protected configuration is admin-only.'
                );
            }
        }
        $raw=$input['raw_body'];
        $permit=$input['permit'];
        if (strlen($raw)>1048576 || strlen($raw)<2
            || !isset($permit['claims'],$permit['signature'])
            || count($permit)!==2 || !is_array($permit['claims'])
            || !is_string($permit['signature'])) {
            throw new \RuntimeException('AUTH_REQUIRED: malformed authorization envelope.');
        }
        $parsed=json_decode($raw,true);
        if (!is_array($parsed) || json_last_error()!==JSON_ERROR_NONE
            || array_diff(array_keys($parsed),array('action','type','data'))
            || !isset($parsed['action'],$parsed['data'])
            || !is_array($parsed['data'])
            || !is_string($parsed['action'])) {
            throw new \RuntimeException('AUTH_REQUIRED: malformed signed request body.');
        }
        // Extra top-level keys (including legacy id/name/content) would be
        // promoted by api.common.php after signature checking. Reject them
        // rather than risk a signed/actual semantics mismatch.
        if ($parsed['action']!==$action || $parsed['data']!==$data
            || (isset($parsed['type']) && $parsed['type']!==$elementType)
            || (!isset($parsed['type']) && $elementType!=='')) {
            throw new \RuntimeException('AUTH_REQUIRED: signed request differs from actual mutation.');
        }
        // Explicit top-level type and data.type must agree. Otherwise an
        // attacker could obtain a permit scoped to a resource but execute
        // the same signed body as a different MODX element type.
        if ($elementType!=='' && isset($data['type'])
            && (!is_string($data['type']) || $data['type']!==$elementType)) {
            throw new \RuntimeException(
                'AUTH_DENIED: conflicting element type selectors.'
            );
        }
        $scopeType=$elementType!=='' ? $elementType
            : (isset($data['type']) && is_string($data['type']) ? $data['type'] : 'none');
        if (!isset($permit['claims']['action'],$permit['claims']['element_type'])
            || $permit['claims']['action']!==$action
            || $permit['claims']['element_type']!==$scopeType) {
            throw new \RuntimeException('AUTH_REQUIRED: signed action or object type mismatch.');
        }
        list($trusted,$publicKey)=self::trustedConfiguration($modx);
        IndependentPermitV1::verifyCryptographicClaims(
            $permit['claims'],$permit['signature'],$publicKey,$trusted,hash('sha256',$raw)
        );
        // Two independently enforced authorizations: a signed Controller
        // permit AND a site-owned action allowlist. Enabling protected mode
        // with an empty/unconfigured list grants NO mutation privileges.
        // The caller cannot supply or override this protected setting.
        $allowedCsv=self::readSetting($modx,'modxmcp.permit_allowed_actions');
        if (!is_string($allowedCsv) || $allowedCsv==='') {
            throw new \RuntimeException(
                'AUTH_DENIED: no mutating operations permitted by site policy.'
            );
        }
        $allowed=array();
        foreach (explode(',',$allowedCsv) as $item) {
            if (!preg_match('/\A[a-z][a-z0-9_]{0,79}\z/D',$item)
                || isset($allowed[$item])) {
                throw new \RuntimeException(
                    'AUTH_DENIED: malformed site permit action policy.'
                );
            }
            $allowed[$item]=true;
        }
        if (!isset($allowed[$action])) {
            throw new \RuntimeException(
                'AUTH_DENIED: action disallowed by independent site policy.'
            );
        }
        if ($action === 'run_processor') {
            // RU: Галочка в Manager — главный выключатель; читаем актуальное
            // RU: значение из БД, а не кеш PHP. Отказ — до nonce и записи.
            // EN: The Manager checkbox is the master switch. Read persisted
            // EN: state, not PHP's option cache; deny before nonce or writes.
            if (!self::processorMasterEnabled($modx)) {
                throw new \RuntimeException(
                    'AUTH_DENIED: processor master switch is disabled.'
                );
            }
            // RU: Проверяем точный процессор после подписи Ed25519 и политики
            // RU: сайта, но до резервирования одноразового nonce.
            // EN: Verify the exact processor AFTER Ed25519 signature and the
            // EN: site action policy, but BEFORE reserving the nonce.
            ProcessorAccessPolicy::authorize(
                $data,
                self::readSetting($modx, 'modxmcp.processor_access_mode'),
                self::readSetting($modx, 'modxmcp.allowed_processors'),
                self::readSetting($modx, 'modxmcp.privileged_processors')
            );
        }
        // RU: Неверные параметры пакета отклоняем ПОСЛЕ проверки
        // RU: подписи и политики сайта, но ДО одноразового nonce.
        // EN: A signed but malformed package request must not consume the
        // EN: controller's one-use permit before its deterministic rejection.
        if ($action === 'install_package') {
            \ModxMcp\Tools\PackageInstallTool::validateInput($data);
        } elseif ($action === 'uninstall_package') {
            \ModxMcp\Tools\PackageUninstallTool::validateInput($data);
        }
        $nonce=$permit['claims']['nonce'];
        $issued=(int)$permit['claims']['issued_at'];
        $expires=(int)$permit['claims']['expires_at'];
        $connection=self::dedicatedNonceConnection($modx);
        $ledger=new PermitNonceLedger($connection,self::ledgerTable($modx));
        $ledger->reserve($nonce,$issued,$expires);
        // The caller then enforces strict CAS under the SAME mutation lock.
        return true;
    }
}
