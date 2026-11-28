<?php
/* MODX MCP SOURCE / ИСХОДНИК MODX MCP
 * RU: Добровольно включаемый журнал повторов поверх авторизованного диспетчера.
 * EN: Opt-in replay journal around independently authorized mutations.
 */
namespace ModxMcp\Security;

/**
 * Only signed, independently authorized mutations may use the journal.
 * No new mandatory runtime dependencies, table or settings on existing hosts.
 * Reissuing the exact signed action with a fresh permit is a status query:
 * it never executes again and returns a safe operation status in an error.
 */
final class MutationOperationGate
{
    private static function setting($modx, $key)
    {
        $version=method_exists($modx,'getVersionData')
            ? $modx->getVersionData() : array();
        $class=isset($version['version']) && (int)$version['version']>=3
            ? 'MODX\\Revolution\\modSystemSetting' : 'modSystemSetting';
        $table=$modx->getTableName($class);
        if (!is_string($table)
            || !preg_match('/\A`?[A-Za-z0-9_]+`?\z/D',$table)) {
            throw new \RuntimeException('Operation journal policy unavailable.');
        }
        $query=$modx->prepare('SELECT `value` FROM '.$table
            .' WHERE `key`=? LIMIT 1');
        if (!$query || !$query->execute(array($key))) {
            throw new \RuntimeException('Operation journal policy unavailable.');
        }
        $value=$query->fetchColumn();
        return $value===false ? null : (string)$value;
    }

    private static function table($modx)
    {
        $nonce=ProtectedAuthorization::ledgerTable($modx);
        $suffix='modxmcp_permit_nonces';
        if (substr($nonce,-strlen($suffix))!==$suffix) {
            throw new \RuntimeException('Operation journal table prefix unavailable.');
        }
        $table=substr($nonce,0,-strlen($suffix)).'modxmcp_operation_intents';
        if (!preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{0,63}\z/D',$table)) {
            throw new \RuntimeException('Invalid operation journal table name.');
        }
        return $table;
    }

    private static function connection($modx)
    {
        $dsn=(string)$modx->getOption('dsn',null,'');
        if ($dsn==='')$dsn=(string)$modx->getOption('database_dsn',null,'');
        $user=(string)$modx->getOption('username',null,'');
        if ($user==='')$user=(string)$modx->getOption('database_user',null,'');
        $password=(string)$modx->getOption('password',null,'');
        if ($password==='')$password=(string)$modx->getOption('database_password',null,'');
        if (strpos($dsn,'mysql:')!==0 || $user==='') {
            throw new \RuntimeException('Operation journal database connection unavailable.');
        }
        return new \PDO($dsn,$user,$password,array(
            \PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_EMULATE_PREPARES=>false,
            \PDO::ATTR_PERSISTENT=>false,
        ));
    }

    public static function dispatch(
        $modx,$action,$elementType,array $originalData,
        $operationId,$protected,callable $execute
    ) {
        if ($operationId===null) {
            // RU: Клиенты 1.2.7 и запросы без идентификатора не меняются.
            // EN: No change to preexisting requests without operation IDs.
            return $execute();
        }
        if ($protected !== true) {
            throw new \ModxMCPClientException(
                'OPERATION_JOURNAL_DENIED: signed authorization is required.'
            );
        }
        try {
            $policy=self::setting($modx,'modxmcp.operation_journal_enabled');
            if ($policy!=='1') {
                throw new \RuntimeException('Journal has not been enabled.');
            }
            $site=self::setting($modx,'modxmcp.permit_site_id');
            $contour=self::setting($modx,'modxmcp.permit_contour');
            if (!is_string($site) || $site==='' || !is_string($contour)
                || $contour==='') {
                throw new \RuntimeException('Journal site identity unavailable.');
            }
            $fingerprint=MutationOperationLedger::requestDigest(
                $site.'|'.$contour,$action,$elementType,$originalData
            );
            $ledger=new MutationOperationLedger(
                self::connection($modx),self::table($modx)
            );
            $claim=$ledger->claim($operationId,$fingerprint);
        } catch (\Throwable $error) {
            // No dispatch when opt-in journal is missing or cannot reserve.
            throw new \ModxMCPClientException(
                'OPERATION_JOURNAL_UNAVAILABLE: mutation not dispatched.'
            );
        }
        if ($claim['fresh']!==true) {
            // Authorization and nonce were already checked by dispatcher.
            // Duplicate PENDING can mean a live or crashed first request.
            throw new \ModxMCPClientException(
                'OPERATION_REPLAY_BLOCKED: prior state='.$claim['state']
                .'; do not re-execute; verify actual state before retry.'
            );
        }

        try {
            $result=$execute();
        } catch (\Throwable $error) {
            try {
                $ledger->finish($operationId,$fingerprint,'UNCERTAIN');
            } catch (\Throwable $journalFailure) {
                // Keep the PENDING claim durable and deny any automatic retry.
            }
            // Once the signed journal has claimed this operation, an error
            // cannot safely be interpreted as proof that no effect occurred.
            // A second signed permit must not turn an uncertain claim into
            // a blind repeat, and raw processor messages must not leak.
            throw MutationOutcomeGuard::uncertain($error);
        }
        try {
            $ledger->finish($operationId,$fingerprint,'SUCCEEDED');
        } catch (\Throwable $error) {
            throw new \ModxMCPClientException(
                'MUTATION_OUTCOME_UNCERTAIN: mutation finished but operation '
                .'journal confirmation failed; inspect before retry.'
            );
        }
        if (is_array($result)) {
            $result['_operation_status']='SUCCEEDED';
        }
        return $result;
    }
}
