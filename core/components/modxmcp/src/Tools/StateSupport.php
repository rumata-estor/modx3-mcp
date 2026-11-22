<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: CAS: ревизии, блокировки, транзакции и проверка состояния перед записью.
 * EN: CAS revisions, locks, transactions and pre-mutation state checks.
 */
namespace ModxMcp\Tools;

class StateSupport
{
    const REVISION_KEY = 'modxmcp.site_revision';

    // PHP-request-local. Never stored in MODX system settings or client input.
    // Callbacks do not fire when the database transaction is rolled back.
    private static $transactionCallbacks = array();
    private static $lockedGroupSnapshots = array();

    public static function inRevisionTransaction($context)
    {
        return isset(self::$transactionCallbacks[spl_object_hash($context->modx())]);
    }

    public static function afterRevisionCommit($context, $callback)
    {
        $key = spl_object_hash($context->modx());
        if (isset(self::$transactionCallbacks[$key])) {
            self::$transactionCallbacks[$key][] = $callback;
        } else {
            call_user_func($callback);
        }
    }

    /**
     * Database-backed mutations only. No SQL transaction can roll back a
     * media file, package install, emitted event, or external I/O.
     */
    public static function supportsAtomicRevision($context, $action, array $data)
    {
        if (in_array($action, array(
            'update_resource_tvs',
            'create_category', 'update_category', 'delete_category',
            'assign_element_category',
            'create_system_setting', 'update_system_setting',
            'delete_system_setting', 'update_element_property',
            'update_user_group',
        ), true)) {
            return true;
        }
        // Some element processors can write static files or fire plugins.
        // Scope the first-stage atomic guarantee to regular resources only.
        if (in_array($action, array('create_element', 'update_element', 'delete_element'), true)) {
            return isset($data['type']) && $data['type'] === 'resource'
                && empty($data['static']) && empty($data['static_file']);
        }
        return false;
    }

    public static function siteState($context)
    {
        return array(
            'site_revision' => self::revision($context),
            'atomic_preconditions' => true,
            'precondition_version' => 2,
            'cas_required' => true,
        );
    }

    /**
     * Read the actual persisted CAS ledger, bypassing any xPDO object cache.
     * A single PHP process may perform many mutations; cached setting models
     * must not report stale revisions after a successful write.
     */
    /**
     * Read a consistent observed revision and target/dependency fingerprints
     * through MODX MCP. This is the only authoritative source for a CAS
     * read_set; clients must not guess or reconstruct hashes.
     */
    public static function observedState($context, array $arguments)
    {
        if (!array_key_exists('read_set', $arguments)) {
            return self::siteState($context);
        }
        $requested = $arguments['read_set'];
        if (!is_array($requested) || count($requested) > 32) {
            throw new \ModxMCPClientException(
                'Invalid CAS read_set snapshot request (max 32).'
            );
        }
        return self::withMutationLock($context, function () use ($context, $requested) {
            $state = self::siteState($context);
            $items = array();
            foreach ($requested as $item) {
                if (!is_array($item)
                    || !isset($item['type'])
                    || !in_array($item['type'], array(
                        'resource','template','chunk','snippet','plugin','tv',
                        'category','system_setting','user','user_group','role'
                    ), true)
                    || (empty($item['id']) && empty($item['name']) && empty($item['key']))) {
                    throw new \ModxMCPClientException(
                        'Invalid CAS fingerprint request selector.'
                    );
                }
                $role = isset($item['role']) && $item['role'] === 'dependency'
                    ? 'dependency' : 'target';
                // Field-scoped fingerprints are optional for dependency
                // tracking but NEVER valid for a write target.
                if ($role === 'target' && !empty($item['fields'])) {
                    throw new \ModxMCPClientException(
                        'CAS target fingerprint must cover complete state.'
                    );
                }
                $selector = array(
                    'role' => $role,
                    'type' => $item['type'],
                );
                foreach (array('id','name','key','fields') as $key) {
                    if (array_key_exists($key,$item)) {
                        $selector[$key] = $item[$key];
                    }
                }
                $selector['fingerprint'] = self::fingerprintReadItem(
                    $context, $selector
                );
                $items[] = $selector;
            }
            $state['read_set'] = $items;
            return $state;
        });
    }

    public static function revision($context)
    {
        $modx = $context->modx();
        $table = $modx->getTableName(
            $context->platform()->className('system_setting')
        );
        $statement = $modx->prepare(
            'SELECT `value` FROM ' . $table
            . ' WHERE `key` = ? LIMIT 1'
        );
        if (!$statement || !$statement->execute(array(self::REVISION_KEY))) {
            throw new \RuntimeException('Unable to read MODX MCP site revision.');
        }
        $value = $statement->fetchColumn();
        return $value === false ? 0 : max(0, (int)$value);
    }

    /**
     * Atomically increment the internal CAS ledger using the SAME xPDO
     * connection as any outer revision transaction. Unlike loading and
     * saving a cached modSystemSetting, this also works for repeated writes
     * inside a long-lived PHP process and keeps the first increment atomic.
     *
     * No site content or MODX business data is manipulated by this query:
     * it changes only the connector's own modxmcp.site_revision setting.
     */
    public static function incrementRevision($context)
    {
        $modx = $context->modx();
        $table = $modx->getTableName(
            $context->platform()->className('system_setting')
        );
        $sql = 'INSERT INTO ' . $table
            . ' (`key`, `value`, `xtype`, `namespace`, `area`)'
            . ' VALUES (?, ?, ?, ?, ?)'
            . ' ON DUPLICATE KEY UPDATE'
            . ' `value` = CAST(GREATEST(0, CAST(`value` AS SIGNED)) + 1 AS CHAR)';
        try {
            $statement = $modx->prepare($sql);
            if (!$statement || !$statement->execute(array(
                self::REVISION_KEY, '1', 'textfield', 'modxmcp', 'modxmcp:runtime'
            ))) {
                throw new \RuntimeException('Revision ledger write did not complete.');
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Failed to persist MODX MCP site revision.',
                0,
                $e
            );
        }
        return self::revision($context);
    }

    public static function withMutationLock($context, $callback)
    {
        $modx = $context->modx();
        $cachePath = (string)$modx->getOption(
            'cache_path',
            null,
            rtrim((string)$modx->getOption('core_path'), '/\\')
                . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR
        );
        $directory = rtrim($cachePath, '/\\')
            . DIRECTORY_SEPARATOR . 'modxmcp' . DIRECTORY_SEPARATOR;
        if (!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create MODX MCP mutation lock directory.');
        }
        $path = $directory . 'runtime-cas.lock';
        $handle = @fopen($path, 'c+');
        if (!$handle) {
            throw new \RuntimeException('Cannot open MODX MCP mutation lock.');
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Cannot acquire MODX MCP mutation lock.');
            }
            return call_user_func($callback);
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    }

    /**
     * Lock the existing resource row BEFORE taking the CAS snapshot in an
     * outer InnoDB transaction. MODX Manager does not honor MCP's flock;
     * FOR UPDATE forces a racing Manager write to finish first. The actual
     * business mutation remains delegated to MODX processors via MCP.
     *
     * Must be called from withRevisionTransaction(); never independently.
     */
    public static function lockResourceRowForCas($context, $id)
    {
        if ((!is_int($id) && !(is_string($id)
                && preg_match('/\A[1-9][0-9]*\z/D',$id)))
            || (int)$id < 1
            || (string)(int)$id !== (string)$id) {
            throw new \ModxMCPClientException(
                'CAS_REQUIRED: resource row lock requires numeric ID.'
            );
        }
        $modx=$context->modx();
        $table=$modx->getTableName(
            $context->platform()->className('resource')
        );
        $statement=$modx->prepare(
            'SELECT `id` FROM '.$table.' WHERE `id`=? FOR UPDATE'
        );
        if (!$statement || !$statement->execute(array((int)$id))
            || $statement->fetchColumn()===false) {
            throw new \ModxMCPClientException(
                'STALE_STATE: target resource disappeared before write.'
            );
        }
        return true;
    }

    /**
     * RU: Защита от внешней вставки членства (phantom) в границе InnoDB.
     * EN: Lock the group parent and its indexed membership range BEFORE
     * EN: CAS evaluation, under the same REPEATABLE READ transaction.
     * EN: This must run in withRevisionTransaction(), never standalone.
     */
    public static function lockGroupMembershipForCas($context, $id)
    {
        if (!self::inRevisionTransaction($context) || !is_int($id) || $id < 1) {
            throw new \ModxMCPClientException(
                'CAS_REQUIRED: group membership lock requires active transaction and numeric group id.'
            );
        }
        $modx = $context->modx();
        // MariaDB CI uses tx_isolation; do not claim next-key protection
        // at READ COMMITTED where locking reads allow phantom inserts.
        $isolation = $modx->query('SELECT @@tx_isolation');
        $level = $isolation ? strtoupper(str_replace('-', ' ', (string)$isolation->fetchColumn())) : '';
        if (str_replace(' ', '', $level) !== 'REPEATABLEREAD') {
            throw new \ModxMCPClientException(
                'CAS_REQUIRED: membership range locking needs REPEATABLE READ.'
            );
        }
        $groupTable = $modx->getTableName(
            $context->platform()->className('user_group')
        );
        $memberTable = $modx->getTableName(
            $context->platform()->className('user_group_member')
        );
        if (getenv('MODXMCP_DISPOSABLE_CI')==='1') {
            $t=$modx->query('SHOW TABLE STATUS LIKE '.$modx->quote(
                trim(str_replace('`','',$memberTable))
            ));
            $info=$t?$t->fetch(\PDO::FETCH_ASSOC):array();
            fwrite(STDERR,'ACL_LOCK_DIAG engine='.($info['Engine']??'unknown')
                .' member_table='.$memberTable.' isolation='.$level.PHP_EOL);
        }
        // A predictable indexed range is essential when no member exists.
        $indexStatement = $modx->query('SHOW INDEX FROM '.$memberTable);
        if (!$indexStatement) {
            throw new \ModxMCPClientException('CAS_REQUIRED: cannot validate member index.');
        }
        $hasIndex = false;
        foreach ($indexStatement->fetchAll(\PDO::FETCH_ASSOC) as $index) {
            if (isset($index['Column_name'],$index['Seq_in_index'])
                && $index['Column_name']==='user_group'
                && (int)$index['Seq_in_index']===1) {
                $hasIndex=true;
            }
        }
        if (!$hasIndex) {
            if (getenv('MODXMCP_DISPOSABLE_CI')==='1') {
                fwrite(STDERR,'ACL_LOCK_DIAG no leading user_group index'.PHP_EOL);
            }
            throw new \ModxMCPClientException(
                'CAS_REQUIRED: user_group_member needs leading user_group index.'
            );
        }
        $parent = $modx->prepare(
            'SELECT `id`,`name`,`description`,`parent`,`rank` FROM '
            .$groupTable.' WHERE `id`=? FOR UPDATE'
        );
        if (!$parent || !$parent->execute(array($id))) {
            throw new \ModxMCPClientException('CAS_REQUIRED: group row lock failed.');
        }
        $group = $parent->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($group)) {
            throw new \ModxMCPClientException('STALE_STATE group disappeared.');
        }
        $lockStart=microtime(true);
        $members = $modx->prepare(
            'SELECT `id`,`member`,`user_group`,`role` FROM '
            .$memberTable.' WHERE `user_group`=? FOR UPDATE'
        );
        if (!$members || !$members->execute(array($id))) {
            throw new \ModxMCPClientException('CAS_REQUIRED: membership range lock failed.');
        }
        if (getenv('MODXMCP_DISPOSABLE_CI')==='1') fwrite(STDERR,
            'ACL_LOCK_DIAG duration='.(microtime(true)-$lockStart).PHP_EOL);
        $rows = array();
        foreach ($members->fetchAll(\PDO::FETCH_ASSOC) as $record) {
            $rows[] = array('id'=>(int)$record['id'],
                'member'=>(int)$record['member'],
                'user_group'=>(int)$record['user_group'],
                'role'=>(int)$record['role']);
        }
        usort($rows, function ($a,$b) {return $a['id'] <=> $b['id'];});
        self::$lockedGroupSnapshots[spl_object_hash($modx).':'.$id] = array(
            'group'=>$group,'members'=>$rows
        );
    }

    /**
     * Database-only atomic mutation boundary for guarded writes. The caller
     * must hold the shared mutation lock. Never use this for operations with
     * external effects that cannot be rolled back by MariaDB.
     */
    public static function withRevisionTransaction($context, $callback)
    {
        $modx = $context->modx();
        $key = spl_object_hash($modx);
        if (isset(self::$transactionCallbacks[$key])) {
            throw new \RuntimeException('Nested CAS revision boundary not allowed.');
        }
        if ($modx->beginTransaction() === false) {
            throw new \RuntimeException('Cannot start CAS revision transaction.');
        }
        self::$transactionCallbacks[$key] = array();
        try {
            $result = call_user_func($callback);
            if ($modx->commit() === false) {
                throw new \RuntimeException('CAS revision transaction commit failed.');
            }
            $callbacks = self::$transactionCallbacks[$key];
            unset(self::$transactionCallbacks[$key]);
            foreach (array_keys(self::$lockedGroupSnapshots) as $lockedKey) {
                if (strpos($lockedKey,$key.':')===0)unset(self::$lockedGroupSnapshots[$lockedKey]);
            }
        } catch (\Throwable $e) {
            unset(self::$transactionCallbacks[$key]);
            foreach (array_keys(self::$lockedGroupSnapshots) as $lockedKey) {
                if (strpos($lockedKey,$key.':')===0)unset(self::$lockedGroupSnapshots[$lockedKey]);
            }
            $modx->rollback();
            throw $e;
        }
        // The DB commit is complete. Side effects must not falsely turn a
        // committed write into a reported transaction failure.
        foreach ($callbacks as $afterCommit) {
            try {
                call_user_func($afterCommit);
            } catch (\Throwable $e) {
                $modx->log(3, '[modxmcp] after-commit callback failed: ' . $e->getMessage());
            }
        }
        return $result;
    }

    private static function isListArray(array $value)
    {
        if (empty($value)) {
            return true;
        }
        return array_keys($value) === range(0, count($value) - 1);
    }

    private static function canonicalize($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        if (self::isListArray($value)) {
            $out = array();
            foreach ($value as $item) {
                $out[] = self::canonicalize($item);
            }
            return $out;
        }
        ksort($value, SORT_STRING);
        $out = array();
        foreach ($value as $key => $item) {
            $out[(string)$key] = self::canonicalize($item);
        }
        return $out;
    }

    public static function canonicalHash($value)
    {
        $json = json_encode(
            self::canonicalize($value),
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_PRESERVE_ZERO_FRACTION
        );
        if ($json === false) {
            throw new \RuntimeException('Cannot encode state fingerprint payload.');
        }
        return hash('sha256', $json);
    }

    private static function elementState($type, array $raw)
    {
        $state = array('exists' => true);
        if (array_key_exists('id', $raw) && $raw['id'] !== null) {
            $state['id'] = is_numeric($raw['id']) ? (int)$raw['id'] : $raw['id'];
        }

        if ($type === 'resource') {
            $state['name'] = (string)(
                isset($raw['pagetitle']) ? $raw['pagetitle'] : (
                    isset($raw['name']) ? $raw['name'] : ''
                )
            );
        } elseif ($type === 'template') {
            $state['name'] = (string)(
                isset($raw['templatename']) ? $raw['templatename'] : (
                    isset($raw['name']) ? $raw['name'] : ''
                )
            );
        } elseif ($type === 'category') {
            $state['name'] = (string)(
                isset($raw['category']) ? $raw['category'] : (
                    isset($raw['name']) ? $raw['name'] : ''
                )
            );
        } else {
            $state['name'] = isset($raw['name']) ? (string)$raw['name'] : '';
        }

        $safeFields = array(
            'chunk' => array(
                'description', 'category', 'static', 'static_file',
                'source', 'property_preprocess',
            ),
            'snippet' => array(
                'description', 'category', 'static', 'static_file',
                'source', 'property_preprocess',
            ),
            'template' => array(
                'description', 'category', 'static', 'static_file', 'source',
            ),
            'resource' => array(
                'longtitle', 'description', 'alias', 'parent', 'template',
                'published', 'context_key', 'class_key', 'isfolder',
                'hidemenu', 'introtext', 'menutitle', 'menuindex',
                'searchable', 'deleted', 'richtext', 'cacheable',
                'pub_date', 'unpub_date', 'content_type', 'content_dispo',
                'uri', 'uri_override', 'properties', 'publishedon',
            ),
            'tv' => array(
                'caption', 'description', 'category', 'type', 'elements',
                'display', 'default_text', 'rank', 'field_type', 'templates',
                'media_source', 'input_properties',
            ),
            'category' => array('parent', 'rank'),
            'plugin' => array(
                'description', 'category', 'disabled', 'static',
                'static_file', 'source', 'property_preprocess', 'events',
            ),
        );
        if (isset($safeFields[$type])) {
            foreach ($safeFields[$type] as $field) {
                if (array_key_exists($field, $raw)) {
                    $state[$field] = $raw[$field];
                }
            }
        }

        foreach (array('content', 'snippet', 'plugincode') as $field) {
            if (array_key_exists($field, $raw)) {
                $state['content_sha256'] = hash(
                    'sha256',
                    (string)$raw[$field]
                );
                break;
            }
        }
        return $state;
    }

    // RU: ACL CAS считает хеш наблюдаемого через штатный MCP чтения.
    // RU: Хеш пароля пользователя добавляется только ВНУТРИ fingerprint,
    // RU: но в get_site_state никакие значения/хеши паролей не возвращаются.
    // EN: Bind ACL fingerprints to MCP-readable models, including a private
    // EN: password digest for users without exposing credentials to clients.
    private static function aclTargetState($context, $type, array $item)
    {
        if (!isset($item['id']) || !is_int($item['id']) || $item['id'] < 1) {
            throw new \ModxMCPClientException(
                'STALE_STATE ACL target fingerprint requires numeric id.'
            );
        }
        $id = $item['id'];
        if ($type === 'user') {
            $raw = (new UserGetTool())->execute($context, array('id'=>$id));
        } elseif ($type === 'user_group') {
            $raw = (new UserGroupGetTool())->execute($context, array('id'=>$id));
        } else {
            $raw = (new RoleGetTool())->execute($context, array('id'=>$id));
        }
        if (!is_array($raw) || !isset($raw['id']) || (int)$raw['id'] !== $id) {
            throw new \ModxMCPClientException('STALE_STATE ACL target read failed.');
        }
        // Sensitive, derivative, and unstable processor fields are excluded.
        $safeFields = array(
            'user' => array('id','username','active','sudo','primary_group',
                'remote_key','remote_data','blocked','blockeduntil',
                'blockedafter','lastlogin','logincount','sessionid'),
            'user_group' => array('id','name','description','parent','rank'),
            'role' => array('id','name','description','authority'),
        );
        $state = array('exists'=>true, 'id'=>$id);
        $snapshotKey = spl_object_hash($context->modx()).':'.$id;
        $lockedGroup = ($type === 'user_group' && self::inRevisionTransaction($context)
            && isset(self::$lockedGroupSnapshots[$snapshotKey]))
            ? self::$lockedGroupSnapshots[$snapshotKey] : null;
        foreach ($safeFields[$type] as $field) {
            if ($lockedGroup !== null && array_key_exists($field,$lockedGroup['group'])) {
                $state[$field] = $lockedGroup['group'][$field];
            } elseif (array_key_exists($field, $raw)) {
                $state[$field] = $raw[$field];
            }
        }
        if ($type === 'user_group') {
            if ($lockedGroup !== null) {
                $state['membership']=$lockedGroup['members'];
            } else {
            // RU: Внешняя выдача членства меняет права, даже если сама группа
            // RU: не редактировалась. Снимок включает связанные членства.
            // EN: Include group membership rows so out-of-band Manager grants
            // EN: invalidate a protected group target snapshot.
            $members = $context->modx()->getCollection(
                $context->platform()->className('user_group_member'),
                array('user_group'=>$id)
            );
            if (!is_array($members)) {
                throw new \ModxMCPClientException(
                    'STALE_STATE unable to observe group membership.'
                );
            }
            $rows = array();
            foreach ($members as $member) {
                $row = $member->toArray();
                $rows[] = array(
                    'id'=>(int)$row['id'],
                    'member'=>(int)$row['member'],
                    'user_group'=>(int)$row['user_group'],
                    'role'=>(int)$row['role'],
                );
            }
            usort($rows,function($a,$b){return $a['id'] <=> $b['id'];});
            $state['membership'] = $rows;
            }
        }
        if ($type === 'user') {
            // User profile may be joined by processors; include relevant
            // persisted profile fields, but never return them individually.
            $profile = $context->modx()->getObject(
                $context->platform()->className('user_profile'),
                array('internalKey'=>$id)
            );
            if ($profile) {
                $profileData = $profile->toArray();
                foreach (array('fullname','email','phone','mobilephone',
                    'address','city','state','zip','country','gender',
                    'dob','extended') as $field) {
                    if (array_key_exists($field,$profileData)) {
                        $state['profile_'.$field] = $profileData[$field];
                    }
                }
            }
            $model = $context->modx()->getObject(
                $context->platform()->className('user'), $id
            );
            if (!$model) {
                throw new \ModxMCPClientException('STALE_STATE user model missing.');
            }
            foreach (array('password','salt','cachepwd') as $secret) {
                $state['secret_'.$secret.'_sha256'] = hash(
                    'sha256', (string)$model->get($secret)
                );
            }
        }
        return $state;
    }

    private static function systemSettingState($context, array $item)
    {
        $key = isset($item['key']) ? trim((string)$item['key']) : '';
        if ($key === '' && isset($item['name'])) {
            $key = trim((string)$item['name']);
        }
        if ($key === '') {
            throw new \ModxMCPClientException(
                'STALE_STATE system_setting precondition requires key.'
            );
        }
        $class = $context->platform()->className('system_setting');
        $setting = $context->modx()->getObject($class, array('key' => $key));
        if (!$setting) {
            return array('exists' => false, 'key' => $key);
        }
        $raw = $setting->toArray();
        $state = array(
            'exists' => true,
            'id' => isset($raw['id']) ? (int)$raw['id'] : null,
            'name' => $key,
        );
        foreach (array('key', 'namespace', 'area', 'xtype') as $field) {
            if (array_key_exists($field, $raw)) {
                $state[$field] = $raw[$field];
            }
        }
        if (array_key_exists('value', $raw)) {
            $state['value_sha256'] = hash(
                'sha256',
                (string)$raw['value']
            );
        }
        return $state;
    }

    public static function fingerprintReadItem($context, array $item)
    {
        $type = strtolower(trim((string)(
            isset($item['type']) ? $item['type'] : (
                isset($item['kind']) ? $item['kind'] : ''
            )
        )));
        if ($type === 'system_setting') {
            $state = self::systemSettingState($context, $item);
        } elseif (in_array($type,array('user','user_group','role'),true)) {
            $state = self::aclTargetState($context, $type, $item);
        } elseif (in_array(
            $type,
            array('resource', 'template', 'chunk', 'snippet', 'plugin', 'tv', 'category'),
            true
        )) {
            $args = array('type' => $type);
            if (isset($item['id']) && $item['id'] !== null) {
                $args['id'] = (int)$item['id'];
            } elseif (!empty($item['name'])) {
                $args['name'] = (string)$item['name'];
            } else {
                throw new \ModxMCPClientException(
                    'STALE_STATE element precondition requires id or name.'
                );
            }
            $raw = (new ElementGetTool())->execute($context, $args);
            if (!is_array($raw)) {
                throw new \ModxMCPClientException(
                    'STALE_STATE element precondition could not be read.'
                );
            }
            if ($type === 'resource') {
                // RU: PR #57, находка @AgelxNash: processor resource/get
                // RU: форматирует даты по настройкам Manager (теряет секунды).
                // RU: Для CAS перечитываем сырые даты через объект MODX,
                // RU: но только ПОСЛЕ штатной проверки прав в get_element.
                // EN: Reported by @AgelxNash, PR #57. The resource/get
                // EN: processor formats dates using Manager settings. CAS
                // EN: reads the unformatted model fields after the authorized
                // EN: processor read, preserving the public get_element format.
                $resourceId = isset($raw['id']) ? (int)$raw['id'] : 0;
                $resource = $resourceId > 0
                    ? $context->modx()->getObject(
                        $context->platform()->className('resource'), $resourceId
                    ) : null;
                if (!$resource) {
                    throw new \ModxMCPClientException(
                        'STALE_STATE resource raw date read failed.'
                    );
                }
                foreach (array('pub_date', 'unpub_date', 'publishedon') as $dateField) {
                    $raw[$dateField] = $resource->get($dateField);
                }
            }
            $state = self::elementState($type, $raw);
        } else {
            throw new \ModxMCPClientException(
                'STALE_STATE unsupported read-set type: ' . $type
            );
        }

        $fields = isset($item['fields']) && is_array($item['fields'])
            ? $item['fields']
            : array();
        if (!empty($fields)) {
            $selected = array();
            foreach ($fields as $field) {
                $field = (string)$field;
                $selected[$field] = array_key_exists($field, $state)
                    ? $state[$field]
                    : null;
            }
            $state = $selected;
        }
        return self::canonicalHash($state);
    }

    private static function identity(array $item)
    {
        $out = array();
        foreach (array('role', 'type', 'id', 'name', 'key', 'path') as $key) {
            if (isset($item[$key]) && $item[$key] !== '') {
                $out[$key] = $item[$key];
            }
        }
        return $out;
    }

    /**
     * Require explicit, strict CAS preconditions for every mutating call.
     * This validates the contract only: assertPreconditions() must still
     * execute UNDER the site mutation lock immediately before writes.
     *
     * Deliberately no legacy/default/opt-out mode: installing this staged
     * 1.2.8 version is an explicit opt-in to mandatory CAS.
     */
    public static function requireMutationPreconditions($action, $raw)
    {
        if (!is_array($raw)
            || !array_key_exists('observed_site_revision', $raw)
            || !array_key_exists('strict_site_revision', $raw)
            || $raw['strict_site_revision'] !== true
            || !array_key_exists('read_set', $raw)
            || !is_array($raw['read_set'])) {
            throw new \ModxMCPClientException(
                'CAS_REQUIRED: mutation ' . $action
                . ' requires observed_site_revision, strict_site_revision=true'
                . ' and read_set from a prior MCP state read.'
            );
        }
        $observed = $raw['observed_site_revision'];
        if ((!is_int($observed)
                && !(is_string($observed) && preg_match('/^(0|[1-9][0-9]*)$/D', $observed)))
            || (string)$observed !== (string)(int)$observed
            || (int)$observed < 0) {
            throw new \ModxMCPClientException(
                'CAS_REQUIRED: observed_site_revision must be a valid nonnegative integer.'
            );
        }
        foreach ($raw['read_set'] as $item) {
            if (!is_array($item)
                || !isset($item['type'])
                || !in_array($item['type'], array(
                    'resource','template','chunk','snippet','plugin','tv',
                    'category','system_setting','user','user_group','role'
                ), true)
                || !isset($item['fingerprint'])
                || !is_string($item['fingerprint'])
                || !preg_match('/^[a-fA-F0-9]{64}$/D', $item['fingerprint'])
                || (empty($item['id']) && empty($item['name']) && empty($item['key']))) {
                throw new \ModxMCPClientException(
                    'CAS_REQUIRED: malformed read_set entry.'
                );
            }
        }
        if ($action === 'update_resource_tvs') {
            if (!array_key_exists('expected_tvs', $raw)
                || !is_array($raw['expected_tvs'])) {
                throw new \ModxMCPClientException(
                    'CAS_REQUIRED: update_resource_tvs requires raw expected_tvs.'
                );
            }
        }
        return $raw;
    }

    /**
     * Protected-mode element updates/deletes cannot rely only on MCP site
     * revision: an administrator in MODX Manager may have edited the target
     * without incrementing that revision. Require a full server-issued
     * fingerprint of the EXACT element by numeric id, not a different
     * resource or an attacker-chosen subset of fields.
     */
    public static function requireProtectedTargetReadSet(
        $action, array $data, array $preconditions
    ) {
        $protectedTargets = array(
            'update_user' => 'user', 'delete_user' => 'user',
            'update_user_group' => 'user_group',
            'delete_user_group' => 'user_group',
            'update_role' => 'role', 'delete_role' => 'role',
            'add_user_to_group' => 'user_group',
            'update_group_member' => 'user_group',
            'remove_user_from_group' => 'user_group',
        );
        if (isset($protectedTargets[$action])) {
            $type = $protectedTargets[$action];
        } elseif (in_array($action, array('update_element','delete_element'), true)) {
            $type = isset($data['type']) ? (string)$data['type'] : '';
        } else {
            return $preconditions;
        }
        $id = isset($data['id']) ? $data['id'] : null;
        if (in_array($action,array(
            'add_user_to_group','update_group_member','remove_user_from_group'
        ),true)) {
            // RU: Для записи членства нужна снимок именно целевой группы.
            // EN: Membership mutations require their exact affected group.
            $id = isset($data['usergroup']) ? $data['usergroup'] : null;
        }
        if (!in_array($type, array(
                'resource','template','chunk','snippet','plugin','tv','category',
                'user','user_group','role'
            ), true)
            || !is_int($id) || $id < 1) {
            throw new \ModxMCPClientException(
                'CAS_REQUIRED: protected element write requires numeric id and type.'
            );
        }
        $readSet = isset($preconditions['read_set'])
            && is_array($preconditions['read_set'])
            ? $preconditions['read_set'] : array();
        foreach ($readSet as $item) {
            if (!is_array($item))continue;
            if (isset($item['role']) && $item['role']==='target'
                && isset($item['type'],$item['id'],$item['fingerprint'])
                && $item['type']===$type && $item['id']===$id
                && (!isset($item['fields']) || $item['fields']===array())
                && preg_match('/^[a-fA-F0-9]{64}$/D',(string)$item['fingerprint'])) {
                return $preconditions;
            }
        }
        throw new \ModxMCPClientException(
            'CAS_REQUIRED: protected element write requires complete target '
            . 'fingerprint from get_site_state.read_set for this id.'
        );
    }

    public static function assertPreconditions($context, array $preconditions)
    {
        $expectedRevision = isset($preconditions['observed_site_revision'])
            ? $preconditions['observed_site_revision']
            : null;
        $actualRevision = self::revision($context);
        $revisionChanged = (
            $expectedRevision !== null
            && (string)$expectedRevision !== (string)$actualRevision
        );

        $changedTargets = array();
        $changedDependencies = array();
        $readSet = isset($preconditions['read_set'])
            && is_array($preconditions['read_set'])
            ? $preconditions['read_set']
            : array();

        foreach ($readSet as $item) {
            if (!is_array($item)) {
                continue;
            }
            $expected = strtolower(trim((string)(
                isset($item['fingerprint']) ? $item['fingerprint'] : ''
            )));
            try {
                $actual = self::fingerprintReadItem($context, $item);
                $matches = ($expected !== '' && hash_equals($expected, $actual));
            } catch (\Throwable $e) {
                $matches = false;
            }
            if ($matches) {
                continue;
            }
            $identity = self::identity($item);
            if (isset($item['role']) && $item['role'] === 'target') {
                $changedTargets[] = $identity;
            } else {
                $changedDependencies[] = $identity;
            }
        }

        if (
            isset($preconditions['target_absent'])
            && is_array($preconditions['target_absent'])
        ) {
            $target = $preconditions['target_absent'];
            $targetType = strtolower(trim((string)(
                isset($target['type']) ? $target['type'] : ''
            )));
            if ($targetType !== '') {
                $selector = array('type' => $targetType);
                if (
                    isset($target['id'])
                    && $target['id'] !== null
                    && $target['id'] !== ''
                ) {
                    $selector['id'] = (int)$target['id'];
                } elseif (!empty($target['name'])) {
                    $selector['name'] = (string)$target['name'];
                }
                try {
                    $existingId = ElementSupport::resolveId(
                        $context,
                        $targetType,
                        $selector
                    );
                } catch (\Throwable $e) {
                    $existingId = 0;
                    $changedTargets[] = array(
                        'role' => 'target',
                        'type' => $targetType,
                        'name' => isset($target['name'])
                            ? (string)$target['name']
                            : null,
                        'reason' => 'target_absence_unverifiable',
                    );
                }
                if ($existingId > 0) {
                    $changedTargets[] = array(
                        'role' => 'target',
                        'type' => $targetType,
                        'id' => (int)$existingId,
                        'name' => isset($target['name'])
                            ? (string)$target['name']
                            : null,
                        'reason' => 'target_now_exists',
                    );
                }
            }
        }

        $strictRevision = !empty($preconditions['strict_site_revision']);
        if (
            !empty($changedTargets)
            || !empty($changedDependencies)
            || ($strictRevision && $revisionChanged)
        ) {
            $reason = !empty($changedTargets)
                ? 'target_changed'
                : (!empty($changedDependencies)
                    ? 'dependency_changed'
                    : 'site_revision_changed');
            $diagnostic = array(
                'status' => 'STALE_STATE',
                'reason' => $reason,
                'expected_site_revision' => $expectedRevision,
                'actual_site_revision' => $actualRevision,
                'site_revision_changed' => $revisionChanged,
                'changed_targets' => $changedTargets,
                'changed_dependencies' => $changedDependencies,
            );
            throw new \ModxMCPClientException(
                'STALE_STATE ' . json_encode(
                    $diagnostic,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                )
            );
        }

        return array(
            'expected_site_revision' => $expectedRevision,
            'actual_site_revision' => $actualRevision,
            'site_revision_changed' => $revisionChanged,
            'read_set_count' => count($readSet),
        );
    }
}
